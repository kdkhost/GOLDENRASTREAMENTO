<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Campaign;
use App\Models\FunnelPage;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\Visit;
use App\Services\Assistente\Atendedor;
use App\Support\Notifica;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeadService
{
    public function __construct(private readonly Rastreio $rastreio) {}

    /**
     * Monta um lead a partir da requisição pública. A origem não vem do POST — vem da
     * jornada que o site registrou antes (visita + cliques), que é onde estão o referer
     * verdadeiro e as UTMs do primeiro acesso.
     */
    public function capturar(Request $request, array $data): Lead
    {
        $visita = $this->rastreio->visitaAtual($request);

        $lead = new Lead([
            ...$data,
            ...$this->atribuicao($request, $visita),
            'status' => 'novo',
            'consent_given' => (bool) ($data['consent'] ?? false),
        ]);
        $lead->save();

        $lead->fill($this->rastreio->atribuicaoParaLead($request, $lead))->save();

        $this->vincularCampanha($lead);

        // Havia uma conversa aberta no mesmo navegador? O assistente entrega o histórico para a
        // equipe junto do cadastro — atendimento e venda ficam na mesma ficha.
        Atendedor::fezContato($lead, $request);

        // O cadastro é o último passo da jornada — entra na linha do tempo da visita.
        $this->rastreio->registrarNaVisita($visita, 'lead', '/leads', null, $lead->kit);

        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => null,
            'to_status' => 'novo',
            'note' => 'Lead recebido pelo site.',
        ]);

        Activity::log('lead_criado', "Lead {$lead->name} capturado (".Atribuidor::rotuloCanal($lead->canal).')', $lead);
        Notifica::disparar(
            'lead_novo',
            'Lead novo: '.($lead->name ?: 'sem nome').($lead->phone ? ' · '.$lead->phone : ''),
            'Canal: '.Atribuidor::rotuloCanal($lead->canal)
                .($lead->utm_source ? ' · origem: '.$lead->utm_source : '')
                .($lead->message ? ' · assunto: '.Str::limit($lead->message, 90, '') : ''),
            (string) $lead->id,
            [
                'lead_id' => $lead->id,
                'nome' => $lead->name,
                'email' => $lead->email,
                'telefone' => $lead->phone,
                'canal' => $lead->canal,
                'utm_source' => $lead->utm_source,
                'campanha' => $lead->campaign?->name,
            ],
            'lead_novo:'.$lead->id,
        );

        return $lead;
    }

    public function atribuirStatus(Lead $lead, string $status, ?string $note, bool $allowAdmin = true): void
    {
        $from = $lead->status;
        if ($from === $status) {
            return;
        }

        $lead->status = $status;
        if ($status === 'vendido' && ! $lead->won_at) {
            $lead->won_at = now();
        }
        if ($status === 'perdido') {
            $lead->lost_at = now();
        }
        if ($status !== 'perdido') {
            $lead->lost_at = null;
        }
        $lead->save();

        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => $from,
            'to_status' => $status,
            'note' => $note,
            'user_id' => auth()->id(),
        ]);

        Activity::log('lead_status', "Lead {$lead->name}: {$from} → {$status}", $lead);
    }

    /**
     * Os UTMs e identificadores que o lead carrega. O formulário manda o que o
     * navegador guardou; o que faltar vem da visita registrada no servidor.
     *
     * @return array<string, ?string>
     */
    private function atribuicao(Request $request, ?Visit $visita): array
    {
        $utm = fn (string $campo): ?string => Str::limit(
            (string) ($request->input($campo) ?: ($visita?->{$campo} ?? '')),
            $campo === 'utm_source' || $campo === 'utm_medium' ? 120 : 190,
            ''
        ) ?: null;

        $caminho = $visita?->landing_path ?: '/'.ltrim($request->path(), '/');

        return [
            'utm_source' => $utm('utm_source'),
            'utm_medium' => $utm('utm_medium'),
            'utm_campaign' => $utm('utm_campaign'),
            'utm_content' => $utm('utm_content'),
            'utm_term' => $utm('utm_term'),
            'gclid' => Str::limit((string) ($request->input('gclid') ?: ($visita?->clique_id === 'gclid' ? $visita?->clique_valor : '')), 120, '') ?: null,
            'fbclid' => Str::limit((string) ($request->input('fbclid') ?: ($visita?->clique_id === 'fbclid' ? $visita?->clique_valor : '')), 120, '') ?: null,
            // Todo identificador de clique, de qualquer plataforma, chega no par genérico:
            // o formulário envia o que a URL trouxe e a visita guarda o primeiro deles.
            'clique_id' => $this->clica($request, $visita)[0],
            'clique_valor' => $this->clica($request, $visita)[1],
            'funnel_page_id' => FunnelPage::idPublicado((int) $request->input('funnel_page_id'))
                ?: FunnelPage::idPeloCaminho($caminho),
            'visitor_id' => Str::limit((string) ($visita?->visitor_id ?: $request->cookie(config('golden.rastreio.cookie')) ?: Str::random(24)), 64, ''),
            'landing_page' => $visita?->landing_path ?: Str::limit('/'.ltrim($request->path(), '/'), 255, ''),
            'referrer' => $this->veioDeFora($visita, $request),
            'ip_hash' => hash('sha256', ($request->ip() ?? '').config('app.key')),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, '') ?: null,
        ];
    }

    /**
     * O par [identificador, valor] do clique que originou o cadastro. O POST vem do
     * formulário, que copia a URL; o que faltar vem da visita registrada no servidor —
     * é onde o identificador continua depois que a pessoa navega para outra página.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function clica(Request $request, ?Visit $visita): array
    {
        $id = Str::limit((string) $request->input('clique_id'), 40, '');
        $valor = Str::limit((string) $request->input('clique_valor'), 120, '');

        if ($id !== '' && $valor !== '') {
            return [$id, $valor];
        }

        // Nem toda plataforma usa o sufixo "clid": o Instagram cola igsh, o LinkedIn li_fat_id.
        $naUrl = collect(config('golden.ids_clique'))
            ->keys()
            ->first(fn (string $chave) => $request->filled($chave));

        return [
            $id ?: ($naUrl ?? (string) $visita?->clique_id) ?: null,
            $valor ?: ($naUrl ? (string) $request->input($naUrl) : (string) $visita?->clique_valor) ?: null,
        ];
    }

    /**
     * A origem externa do lead: o referer da visita, mas só quando ele aponta para fora
     * da casa. Navegar do /planos para o /contato não "veio de" lugar nenhum.
     */
    private function veioDeFora(?Visit $visita, Request $request): ?string
    {
        if ($visita) {
            return $visita->origem_host ? $visita->referer : null;
        }

        $referer = $request->header('referer');

        return Atribuidor::eCasa(Atribuidor::hostDe($referer), $request) ? null : Str::limit((string) $referer, 255, '');
    }

    /**
     * Agrupa o lead numa campanha usando a origem já resolvida pelo classificador.
     * Antes tudo que não era 'direto' virava 'pago' — busca orgânica e rede social
     * apareciam como anúncio. Agora o canal vem do Atribuidor.
     */
    private function vincularCampanha(Lead $lead): void
    {
        $canal = array_key_exists((string) $lead->canal, (array) config('golden.canais')) ? $lead->canal : 'desconhecido';
        // Robô não abre campanha no painel: a linha vai existir, mas como origem não identificada.
        if ($canal === 'indexador') {
            $canal = 'desconhecido';
        }
        $rede = $lead->rede;
        $meio = $lead->utm_medium ?: match ($canal) {
            'anuncio' => 'cpc',
            'rede_social' => 'social',
            'busca_ia' => 'ai-search',
            'busca_organica' => 'organic',
            'email' => 'email',
            'indicacao', 'parceiro' => 'referral',
            default => 'direto',
        };
        $nome = $lead->utm_campaign
            ?: ($rede ? Atribuidor::rotuloRede($rede).' · '.Atribuidor::rotuloCanal($canal) : Atribuidor::rotuloCanal($canal));

        $campanha = Campaign::firstOrCreate(
            ['name' => Str::limit($nome, 190, ''), 'source' => $lead->utm_source ?: $rede, 'medium' => Str::limit($meio, 120, '')],
            ['content' => $lead->utm_content, 'channel' => $canal]
        );

        // Campanha criada antes desta versão guardava o canal no vocabulário antigo
        // ('pago', 'organico'...): o primeiro lead novo conserta a régua daquela linha.
        if (! array_key_exists((string) $campanha->channel, (array) config('golden.canais'))) {
            $campanha->update(['channel' => $canal]);
        }

        $lead->campaign_id = $campanha->id;
        $lead->save();
    }
}
