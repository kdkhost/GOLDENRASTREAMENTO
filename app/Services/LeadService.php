<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeadService
{
    /**
     * Monta um lead a partir da requisição pública, preservando UTMs,
     * identificadores de clique (gclid/fbclid) e o identificador da visita.
     */
    public function capturar(Request $request, array $data): Lead
    {
        $attribution = $this->atribuicao($request);

        $lead = Lead::create([
            ...$data,
            ...$attribution,
            'status' => 'novo',
            'consent_given' => (bool) ($data['consent'] ?? false),
        ]);

        $this->vincularCampanha($lead, $request);

        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => null,
            'to_status' => 'novo',
            'note' => 'Lead recebido pelo site.',
        ]);

        Activity::log('lead_criado', "Lead {$lead->name} capturado (campanha: {$lead->campaign?->name})", $lead);

        return $lead;
    }

    public function atribuirStatus(Lead $lead, string $status, ?string $note, bool $allowAdmin = true): void
    {
        $from = $lead->status;
        if ($from === $status) {
            return;
        }

        $lead->status = $status;
        if ($status === 'vendido' && !$lead->won_at) {
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

    private function atribuicao(Request $request): array
    {
        return [
            'utm_source' => Str::limit($request->input('utm_source'), 120, ''),
            'utm_medium' => Str::limit($request->input('utm_medium'), 120, ''),
            'utm_campaign' => Str::limit($request->input('utm_campaign'), 190, ''),
            'utm_content' => Str::limit($request->input('utm_content'), 190, ''),
            'utm_term' => Str::limit($request->input('utm_term'), 190, ''),
            'gclid' => Str::limit($request->input('gclid'), 120, ''),
            'fbclid' => Str::limit($request->input('fbclid'), 120, ''),
            'visitor_id' => $request->cookie('golden_visitor') ?: Str::random(24),
            'landing_page' => Str::limit($request->header('referer'), 255, ''),
            'referrer' => Str::limit($request->header('referer'), 255, ''),
            'ip_hash' => hash('sha256', ($request->ip() ?? '') . config('app.key')),
            'user_agent' => Str::limit($request->userAgent(), 255, ''),
        ];
    }

    private function vincularCampanha(Lead $lead, Request $request): void
    {
        $source = $lead->utm_source ?: ($lead->gclid ? 'google' : ($lead->fbclid ? 'facebook' : null));
        $medium = $lead->utm_medium ?: ($source ? 'cpc' : 'direto');
        $name = $lead->utm_campaign ?: ($source ? ucfirst($source) : 'Direto / Orgânico');

        $campaign = Campaign::firstOrCreate(
            ['name' => $name, 'source' => $source, 'medium' => $medium],
            ['content' => $lead->utm_content, 'channel' => $medium === 'direto' ? 'direto' : 'pago']
        );

        $lead->campaign_id = $campaign->id;
        $lead->save();
    }
}
