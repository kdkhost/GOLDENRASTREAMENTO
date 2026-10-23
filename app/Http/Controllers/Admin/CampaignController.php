<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        $campanhas = Campaign::withCount('leads')
            ->withCount(['leads as leads_vendidos' => fn ($q) => $q->where('status', 'vendido')])
            ->withCount(['leads as leads_novos' => fn ($q) => $q->where('status', 'novo')])
            ->withSum(['leads as leads_receita' => fn ($q) => $q->where('status', 'vendido')], 'value')
            ->orderByDesc('leads_count')
            ->get();

        return view('admin.campaigns.index', ['campanhas' => $campanhas]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'source' => ['nullable', 'string', 'max:120'],
            'medium' => ['nullable', 'string', 'max:120'],
            'content' => ['nullable', 'string', 'max:190'],
            'channel' => ['required', 'in:pago,organico,direto,referencia'],
        ]);

        $c = Campaign::create($data);
        Activity::log('campanha_criada', "Campanha {$c->name} criada.", $c);

        return back()->with('success', 'Campanha criada.');
    }

    public function toggle(Campaign $campaign): RedirectResponse
    {
        $campaign->update(['ativa' => !$campaign->ativa]);
        Activity::log('campanha_toggle', "Campanha {$campaign->name} " . ($campaign->ativa ? 'reativada' : 'pausada') . '.', $campaign);

        return back()->with('success', 'Status da campanha atualizado.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $name = $campaign->name;
        $campaign->delete();
        Activity::log('campanha_excluida', "Campanha {$name} excluída.");

        return back()->with('success', 'Campanha excluída.');
    }
}
