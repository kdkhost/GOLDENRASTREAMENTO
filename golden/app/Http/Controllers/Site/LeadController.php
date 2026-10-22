<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function store(Request $request, LeadService $service): JsonResponse|RedirectResponse
    {
        // Honeypot anti-spam: campos preenchidos por bots são descartados silenciosamente.
        if ($request->filled('website') || $request->filled('company_url')) {
            return $this->responder($request, true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:90'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:160'],
            'profile' => ['nullable', 'string', 'max:40'],
            'fleet_size' => ['nullable', 'string', 'max:16'],
            'kit' => ['nullable', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:1000'],
            'consent' => ['required', 'accepted'],
        ]);

        $lead = $service->capturar($request, $data);

        \Illuminate\Support\Facades\Log::info('Novo lead capturado', ['id' => $lead->id, 'campanha' => $lead->campaign?->name]);

        return $this->responder($request, true, $lead);
    }

    private function responder(Request $request, bool $ok, $lead = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => $ok,
                'message' => 'Recebemos sua solicitação. Em breve a Golden entra em contato.',
                'whatsapp_url' => $lead ? $this->waUrl($lead) : null,
            ], 201);
        }

        return back()->with('lead_ok', true)->with('lead_whatsapp', $lead ? $this->waUrl($lead) : null);
    }

    private function waUrl($lead): string
    {
        $wa = preg_replace('/\D/', '', \App\Models\Setting::get('contato_whatsapp', config('golden.whatsapp')));
        $texto = "Olá, Golden! Meu nome é {$lead->name}. Tenho interesse em rastreamento" . ($lead->kit ? " ({$lead->kit})" : '') . '.';
        return 'https://wa.me/' . $wa . '?text=' . rawurlencode($texto);
    }
}
