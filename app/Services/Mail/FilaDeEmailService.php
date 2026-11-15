<?php

namespace App\Services\Mail;

use App\Mail\TemplateCustomizadoMail;
use App\Models\EmailFila;
use App\Models\Setting;
use App\Services\Fatura\FaturaPdfService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fila cadenciada (anti-spam / anti-bloqueio) para disparos transacionais e faturas em PDF.
 *
 * Em vez de enviar rajadas instantâneas que acionam filtros de spam ou limites de SMTP,
 * cada mensagem recebe um horário mínimo escalonado e o worker respeita tetos por
 * minuto, por hora e retentativas com backoff exponencial.
 */
class FilaDeEmailService
{
    public const CONFIG_SETTING_KEY = 'email_fila_config';

    public function __construct(
        private EmailTemplateService $templateService,
        private FaturaPdfService $faturaPdfService,
    ) {}

    /**
     * Configurações padrão de proteção anti-spam.
     *
     * @return array{
     *   ativo: bool,
     *   intervalo_entre_envios_seg: int,
     *   limite_por_minuto: int,
     *   limite_por_hora: int,
     *   max_tentativas: int
     * }
     */
    public function configuracoesPadrao(): array
    {
        return [
            'ativo' => true,
            'intervalo_entre_envios_seg' => 15,
            'limite_por_minuto' => 4,
            'limite_por_hora' => 80,
            'max_tentativas' => 4,
        ];
    }

    /**
     * Lê as configurações atuais da fila anti-spam.
     *
     * @return array{
     *   ativo: bool,
     *   intervalo_entre_envios_seg: int,
     *   limite_por_minuto: int,
     *   limite_por_hora: int,
     *   max_tentativas: int
     * }
     */
    public function configuracoes(): array
    {
        $padrao = $this->configuracoesPadrao();
        $salvo = Setting::get(self::CONFIG_SETTING_KEY);

        if (! is_string($salvo) || trim($salvo) === '') {
            return $padrao;
        }

        $decodificado = json_decode($salvo, true);
        if (! is_array($decodificado)) {
            return $padrao;
        }

        return [
            'ativo' => array_key_exists('ativo', $decodificado) ? (bool) $decodificado['ativo'] : $padrao['ativo'],
            'intervalo_entre_envios_seg' => max(0, min(600, (int) ($decodificado['intervalo_entre_envios_seg'] ?? $padrao['intervalo_entre_envios_seg']))),
            'limite_por_minuto' => max(1, min(120, (int) ($decodificado['limite_por_minuto'] ?? $padrao['limite_por_minuto']))),
            'limite_por_hora' => max(1, min(2000, (int) ($decodificado['limite_por_hora'] ?? $padrao['limite_por_hora']))),
            'max_tentativas' => max(1, min(10, (int) ($decodificado['max_tentativas'] ?? $padrao['max_tentativas']))),
        ];
    }

    /**
     * Persiste ajustes de cadência e proteção da fila anti-spam.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public function salvarConfiguracoes(array $dados): array
    {
        $atual = $this->configuracoesPadrao();
        $limpo = [
            'ativo' => (bool) ($dados['ativo'] ?? false),
            'intervalo_entre_envios_seg' => max(0, min(600, (int) ($dados['intervalo_entre_envios_seg'] ?? $atual['intervalo_entre_envios_seg']))),
            'limite_por_minuto' => max(1, min(120, (int) ($dados['limite_por_minuto'] ?? $atual['limite_por_minuto']))),
            'limite_por_hora' => max(1, min(2000, (int) ($dados['limite_por_hora'] ?? $atual['limite_por_hora']))),
            'max_tentativas' => max(1, min(10, (int) ($dados['max_tentativas'] ?? $atual['max_tentativas']))),
        ];

        Setting::set(self::CONFIG_SETTING_KEY, json_encode($limpo, JSON_UNESCAPED_UNICODE));

        return $limpo;
    }

    /**
     * Calcula o próximo slot seguro para enfileirar sem gerar burst SMTP.
     */
    public function calcularProximoHorarioDisponivel(?Carbon $referencia = null): Carbon
    {
        $agora = $referencia?->copy() ?? now();
        $cfg = $this->configuracoes();

        if (! $cfg['ativo'] || $cfg['intervalo_entre_envios_seg'] === 0) {
            return $agora;
        }

        $intervalo = $cfg['intervalo_entre_envios_seg'];

        $ultimoAgendado = EmailFila::where('status', EmailFila::PENDENTE)
            ->orderByDesc('agendado_para')
            ->value('agendado_para');

        $ultimoEnviado = EmailFila::where('status', EmailFila::ENVIADO)
            ->orderByDesc('enviado_em')
            ->value('enviado_em');

        $proximo = $agora->copy();

        if ($ultimoAgendado !== null) {
            $candidato = Carbon::parse($ultimoAgendado)->addSeconds($intervalo);
            if ($candidato->greaterThan($proximo)) {
                $proximo = $candidato;
            }
        }

        if ($ultimoEnviado !== null) {
            $candidato = Carbon::parse($ultimoEnviado)->addSeconds($intervalo);
            if ($candidato->greaterThan($proximo)) {
                $proximo = $candidato;
            }
        }

        $naHora = EmailFila::whereIn('status', [EmailFila::PENDENTE, EmailFila::ENVIADO])
            ->whereBetween('agendado_para', [$proximo->copy()->startOfHour(), $proximo->copy()->endOfHour()])
            ->count();

        if ($naHora >= $cfg['limite_por_hora']) {
            $proximo = $proximo->copy()->addHour()->startOfHour();
        }

        return $proximo;
    }

    /**
     * Enfileira um e-mail já renderizado respeitando cadência e prioridades.
     *
     * @param  array<string, mixed>  $opcoes
     */
    public function enfileirar(
        string $email,
        string $assunto,
        string $corpoHtml,
        array $opcoes = []
    ): EmailFila {
        $cfg = $this->configuracoes();
        $agendado = isset($opcoes['agendado_para']) && $opcoes['agendado_para'] instanceof Carbon
            ? $opcoes['agendado_para']
            : $this->calcularProximoHorarioDisponivel();

        return EmailFila::create([
            'destinatario_email' => mb_strtolower(trim($email)),
            'destinatario_nome' => isset($opcoes['nome']) ? trim((string) $opcoes['nome']) : null,
            'template_chave' => isset($opcoes['template_chave']) ? (string) $opcoes['template_chave'] : null,
            'assunto' => $assunto,
            'corpo_html' => $corpoHtml,
            'corpo_texto' => isset($opcoes['corpo_texto']) ? (string) $opcoes['corpo_texto'] : null,
            'anexos' => $opcoes['anexos'] ?? null,
            'metadados' => $opcoes['metadados'] ?? null,
            'status' => EmailFila::PENDENTE,
            'prioridade' => max(1, min(10, (int) ($opcoes['prioridade'] ?? 5))),
            'tentativas' => 0,
            'max_tentativas' => max(1, (int) ($opcoes['max_tentativas'] ?? $cfg['max_tentativas'])),
            'agendado_para' => $agendado,
            'lote_id' => isset($opcoes['lote_id']) ? (string) $opcoes['lote_id'] : Str::ulid()->toBase32(),
        ]);
    }

    /**
     * Renderiza um template salvo pelo painel (com layout responsivo) e enfileira na fila anti-spam.
     *
     * @param  array<string, mixed>  $variaveis
     * @param  array<string, mixed>  $opcoes
     */
    public function enfileirarTemplate(
        string $chaveTemplate,
        string $email,
        array $variaveis,
        array $opcoes = []
    ): EmailFila {
        $renderizado = $this->templateService->renderizarPorChave($chaveTemplate, $variaveis);

        return $this->enfileirar(
            $email,
            $opcoes['assunto_override'] ?? $renderizado['assunto'],
            $renderizado['html'],
            array_merge($opcoes, [
                'template_chave' => $chaveTemplate,
                'corpo_texto' => $renderizado['texto'],
            ])
        );
    }

    /**
     * Processa um lote seguro respeitando intervalo e limites por minuto e por hora.
     *
     * @return array{processados: int, enviados: int, falhas: int, ignorados_limite: int, motivo: ?string}
     */
    public function processarLote(int $limite = 20, bool $respeitarJanela = true): array
    {
        $cfg = $this->configuracoes();
        $enviadosUltimoMinuto = EmailFila::where('status', EmailFila::ENVIADO)
            ->where('enviado_em', '>=', now()->subMinute())
            ->count();
        $enviadosUltimaHora = EmailFila::where('status', EmailFila::ENVIADO)
            ->where('enviado_em', '>=', now()->subHour())
            ->count();

        if ($respeitarJanela && $cfg['ativo']) {
            if ($enviadosUltimaHora >= $cfg['limite_por_hora']) {
                return [
                    'processados' => 0,
                    'enviados' => 0,
                    'falhas' => 0,
                    'ignorados_limite' => EmailFila::prontosParaEnvio()->count(),
                    'motivo' => 'Limite por hora atingido (proteção anti-spam ativa).',
                ];
            }

            if ($enviadosUltimoMinuto >= $cfg['limite_por_minuto']) {
                return [
                    'processados' => 0,
                    'enviados' => 0,
                    'falhas' => 0,
                    'ignorados_limite' => EmailFila::prontosParaEnvio()->count(),
                    'motivo' => 'Limite por minuto atingido (aguardando próxima janela).',
                ];
            }

            $cotaMinuto = max(1, $cfg['limite_por_minuto'] - $enviadosUltimoMinuto);
            $cotaHora = max(1, $cfg['limite_por_hora'] - $enviadosUltimaHora);
            $limite = min($limite, $cotaMinuto, $cotaHora);
        }

        $itens = EmailFila::prontosParaEnvio()->limit(max(1, $limite))->get();
        $enviados = 0;
        $falhas = 0;

        foreach ($itens as $item) {
            if ($this->despacharItem($item)) {
                $enviados++;
            } else {
                $falhas++;
            }
        }

        return [
            'processados' => $itens->count(),
            'enviados' => $enviados,
            'falhas' => $falhas,
            'ignorados_limite' => 0,
            'motivo' => null,
        ];
    }

    /**
     * Executa o envio real de um único item da fila e atualiza estado/backoff.
     */
    public function despacharItem(EmailFila $item): bool
    {
        $item->update(['status' => EmailFila::PROCESSANDO]);

        try {
            $anexos = $this->resolverAnexosBinarios($item->anexos);

            Mail::to($item->destinatario_email, $item->destinatario_nome)
                ->send(new TemplateCustomizadoMail(
                    $item->assunto,
                    $item->corpo_html,
                    $item->corpo_texto,
                    $anexos
                ));

            $item->update([
                'status' => EmailFila::ENVIADO,
                'tentativas' => $item->tentativas + 1,
                'enviado_em' => now(),
                'ultimo_erro' => null,
            ]);

            return true;
        } catch (Throwable $e) {
            $tentativas = $item->tentativas + 1;
            $esgotou = $tentativas >= max(1, $item->max_tentativas);

            $item->update([
                'status' => $esgotou ? EmailFila::FALHOU : EmailFila::PENDENTE,
                'tentativas' => $tentativas,
                'agendado_para' => $esgotou ? $item->agendado_para : now()->addSeconds(180 * $tentativas),
                'ultimo_erro' => mb_strimwidth($e->getMessage(), 0, 500, '...'),
            ]);

            return false;
        }
    }

    /**
     * Métricas agregadas para a central da fila anti-spam no admin.
     *
     * @return array{
     *   pendentes: int,
     *   enviados_hora: int,
     *   enviados_hoje: int,
     *   falhas: int,
     *   proximo_envio: ?string
     * }
     */
    public function estatisticas(): array
    {
        $proximo = EmailFila::where('status', EmailFila::PENDENTE)
            ->orderBy('agendado_para')
            ->value('agendado_para');

        return [
            'pendentes' => EmailFila::where('status', EmailFila::PENDENTE)->count(),
            'enviados_hora' => EmailFila::where('status', EmailFila::ENVIADO)->where('enviado_em', '>=', now()->subHour())->count(),
            'enviados_hoje' => EmailFila::where('status', EmailFila::ENVIADO)->whereDate('enviado_em', today())->count(),
            'falhas' => EmailFila::where('status', EmailFila::FALHOU)->count(),
            'proximo_envio' => $proximo ? Carbon::parse($proximo)->format('d/m/Y H:i:s') : null,
        ];
    }

    /**
     * Converte os metadados de anexos da fila em binários prontos para o Laravel Mail.
     *
     * @param  array<int, array<string, mixed>>|null  $anexosMeta
     * @return array<int, array{nome: string, mime: string, conteudo: string}>
     */
    private function resolverAnexosBinarios(?array $anexosMeta): array
    {
        if (! is_array($anexosMeta) || $anexosMeta === []) {
            return [];
        }

        $lista = [];
        foreach ($anexosMeta as $meta) {
            if (! is_array($meta)) {
                continue;
            }

            if (($meta['tipo'] ?? '') === 'fatura_pdf') {
                $dadosFatura = is_array($meta['dados'] ?? null) ? $meta['dados'] : [];
                $pdfBytes = $this->faturaPdfService->gerarPdfBinario($dadosFatura);
                $numero = (string) ($dadosFatura['numero'] ?? $dadosFatura['id'] ?? 'fatura');
                $lista[] = [
                    'nome' => (string) ($meta['nome'] ?? 'fatura-'.$numero.'.pdf'),
                    'mime' => 'application/pdf',
                    'conteudo' => $pdfBytes,
                ];
            } elseif (! empty($meta['base64']) && ! empty($meta['nome'])) {
                $decodificado = base64_decode((string) $meta['base64'], true);
                if ($decodificado !== false) {
                    $lista[] = [
                        'nome' => (string) $meta['nome'],
                        'mime' => (string) ($meta['mime'] ?? 'application/octet-stream'),
                        'conteudo' => $decodificado,
                    ];
                }
            }
        }

        return $lista;
    }
}
