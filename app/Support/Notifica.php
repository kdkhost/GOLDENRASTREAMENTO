<?php

namespace App\Support;

use App\Mail\AlertaEmpresaMail;
use App\Models\Notificacao;
use App\Models\NotificacaoPreferencia;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * A única porta de entrada da central de notificações.
 *
 * Um evento do sistema avisa por aqui e não precisa saber quem é a equipe, quantas pessoas têm
 * acesso ao painel, o que cada uma delas desligou no sino, nem quando o aviso vence. Tudo isso é
 * regra desta camada, escrita uma vez: os pontos de disparo ficam com uma linha só, e a
 * consequência — "mudou o catálogo, mudou quem é avisado" — vale para todos ao mesmo tempo.
 *
 * A decisão de design é que notificar nunca quebra o que notificou. Cada disparo devolve o
 * número de avisos criados em vez de atirar exceção: um aviso que falha é um incômodo, e o
 * cadastro do cliente que gerou aquele aviso é a receita de uma tela quebrada no meio do
 * formulário por causa de um sino cheio.
 *
 * O e-mail continua sendo responsabilidade de quem dispara. Esta camada não envia nada — ela é o
 * canal de dentro do painel, e um canal não substitui o outro.
 */
class Notifica
{
    /**
     * Abre o aviso para a audiência do tipo e devolve quantas linhas entraram.
     *
     * `chave` é o freio de repetição: o mesmo fato para a mesma pessoa não entra duas vezes.
     * Quem passa a chave decide o que é "o mesmo fato" — o lead 44 é sempre o mesmo lead; uma
     * falha de cron só é a mesma falha dentro do mesmo dia.
     */
    public static function disparar(
        string $tipo,
        string $titulo,
        ?string $texto = null,
        ?string $rotaParam = null,
        array $dados = [],
        ?string $chave = null,
    ): int {
        if (! static::centralPronta()) {
            return 0;
        }

        $item = (array) (config("notificacoes.tipos.{$tipo}") ?? []);

        if ($item === []) {
            return 0;
        }

        $teto = (int) config('notificacoes.texto_maximo', 500);
        $expiraEm = static::expiracaoDoTipo($tipo, $item);
        $criadas = 0;

        foreach (static::audiencia((string) ($item['audiencia'] ?? 'equipe')) as $usuario) {
            if (! NotificacaoPreferencia::emPainel($usuario, $tipo)) {
                continue;
            }

            $atributos = [
                'tipo' => $tipo,
                'titulo' => mb_substr($titulo, 0, 190),
                'texto' => $texto === null ? null : mb_substr($texto, 0, $teto),
                'rota' => $item['rota'] ?? null,
                'rota_param' => $rotaParam,
                'dados_json' => $dados === [] ? null : $dados,
                'user_id' => $usuario->id,
                'chave' => $chave,
                'expira_em' => $expiraEm,
            ];

            // Com chave, a escrita é "se ainda não existe"; sem chave, é sempre uma linha nova.
            // O try/catch é a última barreira: duas requisições concorrentes podem chegar juntas
            // no mesmo `exists`, e quem perde leva o erro de unicidade do banco.
            try {
                $aviso = $chave === null
                    ? Notificacao::create($atributos)
                    : Notificacao::firstOrCreate(
                        ['user_id' => $usuario->id, 'chave' => $chave],
                        $atributos
                    );

                // A contagem é do que entrou agora: o aviso que já estava lá não é um aviso novo,
                // e devolver 1 para ele faria o chamador achar que notificou de novo.
                if ($aviso->wasRecentlyCreated) {
                    $criadas++;
                }
            } catch (\Throwable $falha) {
                report($falha);
            }
        }

        // Dispara para canais externos (E-mail e Celular / Telegram / Webhook)
        if ($chave === null || $criadas > 0) {
            static::notificarCanaisExternos(
                $tipo,
                $titulo,
                $texto,
                $item['rota'] ?? null,
                $rotaParam,
                $dados
            );
        }

        return $criadas;
    }

    /**
     * Envia a notificação para canais externos: E-mail do administrador e Notificação Direta no Celular.
     */
    public static function notificarCanaisExternos(
        string $tipo,
        string $titulo,
        ?string $texto = null,
        ?string $rota = null,
        ?string $rotaParam = null,
        array $dados = []
    ): void {
        try {
            $url = null;
            if ($rota && Route::has($rota)) {
                $url = $rotaParam !== null ? route($rota, $rotaParam) : route($rota);
            }

            // 1. Canal E-mail da Empresa
            static::enviarEmailAlerta($tipo, $titulo, $texto, $url, $dados);

            // 2. Canal Celular (Telegram Bot / Webhook Móvel / WhatsApp)
            static::enviarNotificacaoCelular($tipo, $titulo, $texto, $url, $dados);
        } catch (\Throwable $e) {
            Log::warning('Falha ao despachar canais externos de notificação.', ['erro' => $e->getMessage()]);
        }
    }

    /**
     * Dispara e-mail de alerta para os destinatários cadastrados.
     */
    public static function enviarEmailAlerta(
        string $tipo,
        string $titulo,
        ?string $texto,
        ?string $url,
        array $dados
    ): void {
        if (Setting::get('notificacoes_email_ativo', '1') !== '1') {
            return;
        }

        // Se for venda (pedido_novo), StoreController::avisar() já envia o PedidoNovoEquipe dedicado
        if ($tipo === 'pedido_novo') {
            return;
        }

        $destinatarios = static::destinatariosDeEmail();
        if (empty($destinatarios)) {
            return;
        }

        try {
            Mail::to($destinatarios)->queue(
                new AlertaEmpresaMail($tipo, $titulo, $texto, $url, $dados)
            );
        } catch (\Throwable $e) {
            Log::warning('Erro ao enfileirar e-mail de alerta.', ['erro' => $e->getMessage()]);
        }
    }

    /**
     * Resolve a lista de e-mails para notificações da empresa.
     *
     * @return array<int, string>
     */
    public static function destinatariosDeEmail(): array
    {
        $raw = Setting::get('notificacoes_email_destinatarios', '');
        if (empty($raw)) {
            $raw = Setting::get('loja_email_equipe', '') ?: Setting::get('contato_email', '');
        }

        if (empty($raw)) {
            $emails = User::where('role', 'admin')->where('status', 'ativo')->pluck('email')->all();

            return array_values(array_filter($emails));
        }

        $emails = array_map('trim', explode(',', (string) $raw));

        return array_values(array_filter($emails, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    }

    /**
     * Envia notificação instantânea para o celular do administrador.
     * Suporta:
     * - Telegram Bot (notificação push nativa com som e vibração no smartphone)
     * - Webhook Móvel (WhatsApp / Gateway / N8N / Zapier)
     */
    public static function enviarNotificacaoCelular(
        string $tipo,
        string $titulo,
        ?string $texto,
        ?string $url,
        array $dados
    ): void {
        if (Setting::get('notificacoes_celular_ativo', '1') !== '1') {
            return;
        }

        // 1. Envio para Telegram (Push no smartphone)
        $telegramToken = trim((string) Setting::get('notificacoes_telegram_token', ''));
        $telegramChatId = trim((string) Setting::get('notificacoes_telegram_chat_id', ''));

        if ($telegramToken !== '' && $telegramChatId !== '') {
            static::enviarTelegram($telegramToken, $telegramChatId, $tipo, $titulo, $texto, $url, $dados);
        }

        // 2. Envio para Webhook Móvel / WhatsApp
        $webhookUrl = trim((string) Setting::get('notificacoes_webhook_celular', ''));
        if ($webhookUrl !== '' && filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            static::enviarWebhookCelular($webhookUrl, $tipo, $titulo, $texto, $url, $dados);
        }
    }

    /**
     * Envia mensagem formatada com alerta direto para o Telegram no celular.
     */
    public static function enviarTelegram(
        string $token,
        string $chatId,
        string $tipo,
        string $titulo,
        ?string $texto,
        ?string $url,
        array $dados
    ): bool {
        $icone = match ($tipo) {
            'lead_novo' => '🎯',
            'pedido_novo' => '💰',
            'depoimento_novo' => '⭐',
            'coleta_solicitada' => '🚚',
            'entrega_atualizada' => '📦',
            'chamado_aberto', 'chamado_respondido' => '🎫',
            'acesso_suspeito' => '🛡️',
            default => '🔔',
        };

        $mensagem = "<b>{$icone} GOLDEN RASTREAMENTO</b>\n\n";
        $mensagem .= '<b>'.htmlspecialchars($titulo)."</b>\n";
        if ($texto) {
            $mensagem .= htmlspecialchars($texto)."\n\n";
        }

        if (! empty($dados)) {
            foreach ($dados as $k => $v) {
                if (is_scalar($v) && $v !== '' && ! in_array($k, ['lead_id', 'pedido_id', 'depoimento_id'], true)) {
                    $label = ucwords(str_replace('_', ' ', (string) $k));
                    $mensagem .= "• <b>{$label}:</b> ".htmlspecialchars((string) $v)."\n";
                }
            }
            $mensagem .= "\n";
        }

        if ($url) {
            $mensagem .= "🔗 <a href=\"{$url}\">Abrir no Painel</a>\n";
        }

        $mensagem .= '⏱ <i>'.now()->format('d/m/Y H:i:s').'</i>';

        try {
            $res = Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $mensagem,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => false,
            ]);

            return $res->successful();
        } catch (\Throwable $e) {
            Log::warning('Erro ao enviar alerta para o Telegram.', ['erro' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Envia payload JSON para webhook móvel (WhatsApp, Gateway ou Discord).
     */
    public static function enviarWebhookCelular(
        string $webhookUrl,
        string $tipo,
        string $titulo,
        ?string $texto,
        ?string $url,
        array $dados
    ): bool {
        try {
            $res = Http::timeout(5)->post($webhookUrl, [
                'app' => 'Golden Rastreamento',
                'tipo' => $tipo,
                'titulo' => $titulo,
                'texto' => $texto,
                'url' => $url,
                'dados' => $dados,
                'data_hora' => now()->toIso8601String(),
            ]);

            return $res->successful();
        } catch (\Throwable $e) {
            Log::warning('Erro ao enviar webhook móvel.', ['erro' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Para quem este tipo de aviso vai.
     *
     * `equipe` são os papéis de painel listados na config; `admin` é só quem configura a
     * plataforma. A conta inativa fica de fora: notificar quem não pode entrar no painel é
     * encher o banco de aviso que ninguém vai ler — e a poda ainda teria que varrer depois.
     */
    public static function audiencia(string $audiencia): Collection
    {
        $papeis = $audiencia === 'admin'
            ? ['admin']
            : (array) config('notificacoes.papeis_equipe', ['admin', 'vendedor']);

        return User::query()
            ->whereIn('role', $papeis)
            ->where('status', 'ativo')
            ->orderBy('id')
            ->get();
    }

    /**
     * Até quando um aviso deste tipo aguenta sem ser lido. O painel manda (a chave geral fica no
     * banco); o catálogo define a exceção por tipo; o config é o plano de fundo de instalação.
     */
    private static function expiracaoDoTipo(string $tipo, array $item): ?CarbonInterface
    {
        $dias = (int) ($item['expiracao_dias']
            ?? Setting::get('notificacoes_expiracao_dias', config('notificacoes.expiracao_dias_padrao', 15)));

        return $dias > 0 ? now()->addDays($dias) : null;
    }

    /**
     * A central existe neste banco?
     *
     * O `hasTable` não é preciosismo: todo disparo desta camada acontece em caminho de pedido —
     * captura de lead, checkout, chamado do portal — e numa instalação que acabou de clonar o
     * código e ainda não rodou `migrate` esses caminhos existem antes das tabelas. Cair fora em
     * silêncio é o comportamento certo: o cadastro do cliente não pode falhar porque o sino ainda
     * não foi construído.
     */
    public static function centralPronta(): bool
    {
        try {
            return Schema::hasTable('notificacoes');
        } catch (\Throwable) {
            return false;
        }
    }
}
