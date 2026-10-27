<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\FunnelPage;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\User;
use App\Services\LeadService;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $leads = $this->query($request)->paginate(itens_por_pagina())->withQueryString();

        return view('admin.leads.index', [
            'leads' => $leads,
            'filtros' => $request->only(['status', 'campanha', 'inicio', 'fim', 'busca', 'responsavel', 'pagina']),
            'campanhas' => Campaign::orderBy('name')->pluck('name', 'id'),
            'paginasFunil' => FunnelPage::orderBy('nome')->pluck('nome', 'id'),
            'responsaveis' => User::whereIn('role', ['admin', 'vendedor'])->orderBy('name')->pluck('name', 'id'),
            'contagem' => Lead::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(Lead $lead): View
    {
        $lead->load(['campaign', 'owner', 'histories.user', 'visit', 'jornada']);

        return view('admin.leads.show', [
            'lead' => $lead,
            'campanhas' => Campaign::orderBy('name')->get(),
            'responsaveis' => User::whereIn('role', ['admin', 'vendedor'])->orderBy('name')->get(),
        ]);
    }

    public function atualizarStatus(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Lead::STATUSES)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        app(LeadService::class)->atribuirStatus($lead, $data['status'], $data['note'] ?? null);
        $lead->refresh();

        if ($data['status'] === 'vendido' && ! $lead->customer_id) {
            $cliente = Customer::deLead($lead);
            Activity::log('lead_convertido', "Lead {$lead->name} convertido em cliente ao fechar a venda.", $cliente);

            return redirect()->route('admin.clientes.show', $cliente)
                ->with('success', 'Venda registrada e lead convertido em cliente no CRM.');
        }

        return back()->with('success', 'Funil atualizado.');
    }

    public function atualizar(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:90'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:160'],
            'profile' => ['nullable', 'string', 'max:40'],
            'fleet_size' => ['nullable', 'string', 'max:16'],
            'kit' => ['nullable', 'string', 'max:80'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $lead->update($data);
        Activity::log('lead_editado', "Lead {$lead->name} atualizado.", $lead);

        return back()->with('success', 'Cadastro do lead atualizado.');
    }

    public function nota(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => $lead->status,
            'to_status' => $lead->status,
            'note' => $data['note'],
            'user_id' => auth()->id(),
        ]);
        Activity::log('lead_nota', "Nota adicionada ao lead {$lead->name}.", $lead);

        return back()->with('success', 'Nota registrada.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $nome = $lead->name;
        $lead->delete();
        Activity::log('lead_excluido', "Lead {$nome} excluído.");

        return redirect()->route('admin.leads.index')->with('success', 'Lead excluído.');
    }

    public function converter(Request $request, Lead $lead): RedirectResponse
    {
        if ($lead->customer_id && Customer::find($lead->customer_id)) {
            return redirect()->route('admin.clientes.show', $lead->customer_id)
                ->with('info', 'Este lead já possui um cliente vinculado.');
        }

        $cliente = Customer::deLead($lead);
        Activity::log('lead_convertido', "Lead {$lead->name} convertido em cliente.", $cliente);

        return redirect()->route('admin.clientes.show', $cliente)
            ->with('success', 'Lead convertido em cliente do CRM.');
    }

    public function export(Request $request): StreamedResponse
    {
        $leads = $this->query($request)->with('campaign')->get();

        Activity::log('lead_exportado', 'Exportação de leads ('.$leads->count().' registros).', null, $request->only(['status', 'campanha', 'inicio', 'fim']));

        // Os rótulos abaixo são o que aparece na primeira linha; a ordem alfabética é do Csv.
        return Csv::baixar('golden-leads', [
            'ID' => fn (Lead $lead) => $lead->id,
            'Nome' => fn (Lead $lead) => $lead->name,
            'WhatsApp' => fn (Lead $lead) => $lead->phone,
            'E-mail' => fn (Lead $lead) => $lead->email,
            'Perfil' => fn (Lead $lead) => $lead->profile,
            'Frota' => fn (Lead $lead) => $lead->fleet_size,
            'Kit' => fn (Lead $lead) => $lead->kit,
            'Status' => fn (Lead $lead) => Lead::label($lead->status),
            'Campanha' => fn (Lead $lead) => $lead->campaign?->name,
            'Origem' => fn (Lead $lead) => $lead->utm_source,
            'UTM campanha' => fn (Lead $lead) => $lead->utm_campaign,
            'Valor (R$)' => fn (Lead $lead) => Csv::moeda($lead->value),
            'Criado em' => fn (Lead $lead) => Csv::data($lead->created_at),
            'Responsável' => fn (Lead $lead) => $lead->owner?->name,
        ], $leads);
    }

    private function query(Request $request)
    {
        return Lead::query()
            ->with(['campaign', 'owner'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('campanha'), fn ($q) => $q->where('campaign_id', $request->campanha))
            ->when($request->filled('pagina'), fn ($q) => $q->where('funnel_page_id', $request->pagina))
            ->when($request->filled('canal'), fn ($q) => $q->where('canal', $request->canal))
            ->when($request->filled('responsavel'), fn ($q) => $q->where('owner_id', $request->responsavel))
            ->when($request->filled('inicio'), fn ($q) => $q->whereDate('created_at', '>=', $request->inicio))
            ->when($request->filled('fim'), fn ($q) => $q->whereDate('created_at', '<=', $request->fim))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('phone', 'like', $t)->orWhere('email', 'like', $t));
            })
            ->latest();
    }
}
