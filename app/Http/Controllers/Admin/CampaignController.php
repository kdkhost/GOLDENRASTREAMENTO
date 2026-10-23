<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.campaigns.index', [
            'campanhas' => $this->query($request)->paginate(itens_por_pagina())->withQueryString(),
            'kpis' => [
                'total' => Campaign::count(),
                'ativas' => Campaign::where('ativa', true)->count(),
                'com_leads' => Campaign::has('leads')->count(),
                'receita' => (int) Lead::where('status', 'vendido')->sum('value'),
            ],
        ]);
    }

    /** A mesma régua da listagem e do filtro: quem filtra e quem pagina lêem isto. */
    private function query(Request $request)
    {
        return Campaign::withCount('leads')
            ->withCount(['leads as leads_vendidos' => fn ($q) => $q->where('status', 'vendido')])
            ->withCount(['leads as leads_novos' => fn ($q) => $q->where('status', 'novo')])
            ->withSum(['leads as leads_receita' => fn ($q) => $q->where('status', 'vendido')], 'value')
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)
                    ->orWhere('source', 'like', $t)
                    ->orWhere('medium', 'like', $t)
                    ->orWhere('content', 'like', $t));
            })
            ->when($request->filled('canal'), fn ($q) => $q->where('channel', $request->canal))
            ->when($request->filled('situacao'), fn ($q) => $q->where('ativa', $request->situacao === 'ativa'))
            ->orderByDesc('leads_count');
    }

    public function create(): View
    {
        return view('admin.campaigns.form', ['campanha' => new Campaign(['channel' => 'anuncio'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $data['ativa'] = $request->boolean('ativa');

        $c = Campaign::create($data);
        Activity::log('campanha_criada', "Campanha {$c->name} criada.", $c);

        return redirect()->route('admin.campanhas.index')->with('success', 'Campanha criada.');
    }

    public function edit(Campaign $campaign): View
    {
        return view('admin.campaigns.form', ['campanha' => $campaign]);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $campaign->fill($this->validar($request));
        $campaign->ativa = $request->boolean('ativa');
        $campaign->save();
        Activity::log('campanha_atualizada', "Campanha {$campaign->name} atualizada.", $campaign);

        return redirect()->route('admin.campanhas.index')->with('success', 'Campanha atualizada.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'source' => ['nullable', 'string', 'max:120'],
            'medium' => ['nullable', 'string', 'max:120'],
            'content' => ['nullable', 'string', 'max:190'],
            'channel' => ['required', 'string', Rule::in(array_keys(Campaign::canais()))],
        ]);
    }

    public function toggle(Campaign $campaign): RedirectResponse
    {
        $campaign->update(['ativa' => ! $campaign->ativa]);
        Activity::log('campanha_toggle', "Campanha {$campaign->name} ".($campaign->ativa ? 'reativada' : 'pausada').'.', $campaign);

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
