<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Visit;
use App\Models\VisitEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Escreve a jornada do visitante: cada page view abre (ou continua) uma Visit e cada
 * clique relevante vira um VisitEvent na visita corrente. Toda a regra de "de onde isso
 * veio" fica no Atribuidor; aqui é só o registro.
 */
class Rastreio
{
    /**
     * A visita em curso para esta requisição. Continua a janela de atividade existente
     * ou abre uma nova quando o tempo passou — ou quando chegou um tráfego de origem
     * diferente, porque um novo clique de campanha é uma visita nova, não um page view.
     */
    public function registrarVisita(Request $request): ?Visit
    {
        $visitorId = $request->cookie(config('golden.rastreio.cookie'));
        if (! $visitorId) {
            return null;
        }

        $agora = Carbon::now();
        $sinais = Atribuidor::sinais($request);
        $atual = $this->visitaAberta($visitorId, $agora);
        $nova = Atribuidor::classificar(
            $sinais['utm_source'],
            $sinais['utm_medium'],
            $sinais['origem_host'],
            $sinais['clique_id'],
            $sinais['user_agent'],
        );

        if ($atual && ! $this->origemMudou($atual, $nova, $request)) {
            $atual->visualizacoes = min($atual->visualizacoes + 1, 9999);
            $atual->ultima_em = $agora;
            $atual->save();

            return $atual;
        }

        $dispositivo = Atribuidor::dispositivo($sinais['user_agent']);

        return Visit::create([
            'visitor_id' => Str::limit($visitorId, 64, ''),
            'sessao' => $request->session()->getId(),
            'canal' => $nova['canal'],
            'rede' => $nova['rede'],
            'pago' => $nova['pago'],
            'motivo' => $nova['motivo'],
            'utm_source' => Str::limit($sinais['utm_source'], 120, ''),
            'utm_medium' => Str::limit($sinais['utm_medium'], 120, ''),
            'utm_campaign' => Str::limit($sinais['utm_campaign'], 190, ''),
            'utm_content' => Str::limit($sinais['utm_content'], 190, ''),
            'utm_term' => Str::limit($sinais['utm_term'], 190, ''),
            'clique_id' => $sinais['clique_id'],
            'clique_valor' => $sinais['clique_valor'],
            'referer' => $sinais['referer'],
            'origem_host' => $sinais['origem_host'],
            'origem_path' => $sinais['origem_path'],
            'landing_path' => Str::limit('/'.ltrim($request->path(), '/'), 190, ''),
            'dispositivo' => $dispositivo['dispositivo'],
            'navegador' => $dispositivo['navegador'],
            'sistema' => $dispositivo['sistema'],
            'ip_hash' => hash('sha256', ($request->ip() ?? '').config('app.key')),
            'bot' => $nova['canal'] === 'indexador',
            'visualizacoes' => 1,
            'eventos' => 0,
            'aberta_em' => $agora,
            'ultima_em' => $agora,
        ]);
    }

    /**
     * Registra um passo da jornada (clique, formulário, cadastro). Reabre a visita se a
     * janela fechou, e respeita o teto de eventos por visita.
     */
    public function registrarEvento(Request $request, string $tipo, ?string $path, ?string $destino, ?string $detalhe): ?VisitEvent
    {
        return $this->registrarNaVisita($this->registrarVisita($request), $tipo, $path, $destino, $detalhe);
    }

    public function registrarNaVisita(?Visit $visita, string $tipo, ?string $path, ?string $destino, ?string $detalhe): ?VisitEvent
    {
        if (! $visita || $visita->bot || $visita->eventos >= config('golden.rastreio.max_eventos')) {
            return null;
        }

        $evento = VisitEvent::create([
            'visit_id' => $visita->id,
            'visitor_id' => $visita->visitor_id,
            'tipo' => $tipo,
            'path' => $path ? Str::limit($path, 190, '') : null,
            'destino' => $destino ? Str::limit($destino, 255, '') : null,
            'detalhe' => $detalhe ? Str::limit($detalhe, 120, '') : null,
        ]);

        // 'view' é a tela que passou, e já tem contador próprio (visualizacoes). O campo
        // 'eventos' é o que o painel anuncia como "cliques medidos" — misturar os dois
        // inflaria o número de interação em toda tela do painel.
        if ($tipo !== 'view') {
            $visita->eventos = $visita->eventos + 1;
        }
        $visita->ultima_em = Carbon::now();
        $visita->save();

        return $evento;
    }

    /**
     * O que o lead herda da jornada: o último toque (o que fechou), o primeiro toque
     * (como a pessoa conheceu a Golden) e quantas visitas separaram os dois.
     *
     * @return array<string, mixed>
     */
    public function atribuicaoParaLead(Request $request, Lead $lead): array
    {
        $visitorId = $lead->visitor_id ?: $request->cookie(config('golden.rastreio.cookie'));
        $visita = $this->visitaAberta($visitorId, Carbon::now());

        if (! $visita) {
            // Sem visita registrada (navegador sem cookie, cadastro por API, POST com as
            // UTMs no corpo): resolve das marcações que o próprio lead carrega, porque o
            // formulário já reuniu o que a URL e a jornada sabiam. O lead nunca fica cego.
            $sinais = Atribuidor::sinais($request);
            $clique = match (true) {
                (bool) $lead->gclid => 'gclid',
                (bool) $lead->fbclid => 'fbclid',
                default => $sinais['clique_id'],
            };
            $resolvida = Atribuidor::classificar(
                $lead->utm_source ?: $sinais['utm_source'],
                $lead->utm_medium ?: $sinais['utm_medium'],
                $sinais['origem_host'] ?: Atribuidor::hostDe($lead->referrer),
                $clique,
                $sinais['user_agent'],
            );
            $visita = new Visit([
                'visitor_id' => $visitorId ? Str::limit($visitorId, 64, '') : null,
                'canal' => $resolvida['canal'],
                'rede' => $resolvida['rede'],
                'pago' => $resolvida['pago'],
                'landing_path' => '/'.ltrim($request->path(), '/'),
                'referer' => $sinais['referer'] ?: $lead->referrer,
                'aberta_em' => Carbon::now(),
            ] + Atribuidor::dispositivo($sinais['user_agent']));
        }

        $primeira = Visit::pessoas()
            ->where('visitor_id', $visita->visitor_id)
            ->when($visita->exists, fn ($q) => $q->where('id', '!=', $visita->id))
            ->oldest('aberta_em')
            ->first();

        $toques = $visita->exists
            ? Visit::pessoas()->where('visitor_id', $visita->visitor_id)->count()
            : 1;

        return [
            'visit_id' => $visita->id ?: null,
            'canal' => $visita->canal,
            'rede' => $visita->rede,
            'pago' => (bool) $visita->pago,
            'canal_primeiro' => ($primeira ?? $visita)->canal,
            'rede_primeiro' => ($primeira ?? $visita)->rede,
            'primeiro_toque_em' => ($primeira ?? $visita)->aberta_em,
            'toques' => max(1, $toques),
            'dispositivo' => $visita->dispositivo,
            'navegador' => $visita->navegador,
            'landing_page' => $visita->landing_path ?: $lead->landing_page,
            // Referer da própria casa não é origem de nada: é a página anterior do site.
            'referrer' => ($visita->origem_host ? $visita->referer : null) ?: $lead->referrer,
        ];
    }

    /** A visita em curso deste visitante, sem escrever nada (usado pelo cadastro de lead). */
    public function visitaAtual(Request $request): ?Visit
    {
        return $this->visitaAberta($request->cookie(config('golden.rastreio.cookie')), Carbon::now());
    }

    /** A visita ainda aberta deste visitante dentro da janela de atividade. */
    private function visitaAberta(?string $visitorId, Carbon $agora): ?Visit
    {
        if (! $visitorId) {
            return null;
        }

        return Visit::where('visitor_id', $visitorId)
            ->where('ultima_em', '>=', $agora->copy()->subMinutes(config('golden.rastreio.sessao_minutos')))
            ->orderByDesc('ultima_em')
            ->first();
    }

    /**
     * A requisição trouxe marcação de campanha e ela aponta para outra origem? Então é
     * outra visita: é assim que o painel consegue mostrar "conheceu no Instagram,
     * voltou pelo anúncio, cadastrou na segunda".
     */
    private function origemMudou(Visit $atual, array $nova, Request $request): bool
    {
        $marcada = $request->filled('utm_source') || $request->filled('utm_medium')
            || $request->filled('utm_campaign') || Atribuidor::clique($request->query()) !== null;

        return $marcada && ($atual->canal !== $nova['canal'] || (string) $atual->rede !== (string) $nova['rede']);
    }
}
