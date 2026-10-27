<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $clientes = Customer::withCount(['orders', 'interactions'])
            ->with('owner')
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('nome', 'like', $t)->orWhere('telefone', 'like', $t)->orWhere('email', 'like', $t));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(itens_por_pagina())
            ->withQueryString();

        return view('admin.customers.index', [
            'clientes' => $clientes,
            'kpis' => [
                'ativos' => Customer::where('status', 'ativo')->count(),
                'prospectos' => Customer::where('status', 'prospecto')->count(),
                'base' => Customer::count(),
                'comPedidos' => Customer::has('orders')->count(),
            ],
            'responsaveis' => $this->responsaveis(),
        ]);
    }

    /**
     * Cadastro avulso. Aceita pré-preenchimento pela query string: é assim que as
     * outras páginas (pedido, lead) oferecem "criar cliente" sem duplicar o formulário.
     */
    public function create(Request $request): View
    {
        $cliente = new Customer(
            $request->only([
                'nome', 'telefone', 'email', 'documento', 'tipo',
                'endereco', 'cidade', 'uf', 'cep', 'segmento', 'status',
            ]) + ['tipo' => 'fisica', 'status' => 'prospecto']
        );

        if ($request->filled('valor_anual')) {
            $cliente->valor_anual_cents = (int) round((float) $request->valor_anual * 100);
        }

        return $this->form($cliente);
    }

    public function edit(Customer $cliente): View
    {
        return $this->form($cliente);
    }

    private function form(Customer $cliente): View
    {
        return view('admin.customers.form', [
            'cliente' => $cliente,
            'responsaveis' => $this->responsaveis(),
        ]);
    }

    public function show(Customer $cliente): View
    {
        $cliente->load(['owner', 'lead']);

        return view('admin.customers.show', [
            'cliente' => $cliente,
            // As listas da ficha paginam por nomes próprios: um link não mexe na outra.
            'interacoes' => $cliente->interactions()->with('user')->paginate(itens_por_pagina(), ['*'], 'historico')->withQueryString(),
            'pedidos' => $cliente->orders()->paginate(itens_por_pagina(), ['*'], 'pedidos')->withQueryString(),
            'chamados' => $cliente->tickets()->paginate(itens_por_pagina(), ['*'], 'chamados')->withQueryString(),
            'contratos' => $cliente->contracts()->paginate(itens_por_pagina(), ['*'], 'contratos')->withQueryString(),
            'tipos' => Interaction::TIPOS,
        ]);
    }

    private function responsaveis(): array
    {
        return User::query()
            ->whereIn('role', ['admin', 'vendedor'])
            ->where('status', 'ativo')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $c = Customer::create($data);
        Activity::log('cliente_criado', "Cliente {$c->nome} cadastrado.", $c);

        return redirect()->route('admin.clientes.show', $c)->with('success', 'Cliente cadastrado.');
    }

    public function update(Request $request, Customer $cliente): RedirectResponse
    {
        $cliente->update($this->validateData($request));
        Activity::log('cliente_atualizado', "Cliente {$cliente->nome} atualizado.", $cliente);

        return redirect()->route('admin.clientes.show', $cliente)->with('success', 'Cliente atualizado.');
    }

    public function interaction(Request $request, Customer $cliente): RedirectResponse
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:'.implode(',', array_keys(Interaction::TIPOS))],
            'assunto' => ['nullable', 'string', 'max:190'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'proxima_acao_em' => ['nullable', 'date'],
        ]);
        $data['user_id'] = $request->user()->id;
        Interaction::create($data + ['customer_id' => $cliente->id]);
        Activity::log('interacao_criada', "Interação registrada para {$cliente->nome}.", $cliente);

        return back()->with('success', 'Interação registrada.');
    }

    public function destroy(Customer $cliente): RedirectResponse
    {
        $nome = $cliente->nome;
        $cliente->delete();
        Activity::log('cliente_excluido', "Cliente {$nome} removido.");

        return redirect()->route('admin.clientes.index')->with('success', 'Cliente removido.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:190'],
            'documento' => ['nullable', 'string', 'max:24'],
            'nascimento' => ['nullable', 'date'],
            'placas' => ['nullable', 'string', 'max:190'],
            'tipo' => ['required', 'in:fisica,juridica'],
            'telefone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:160'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:96'],
            'uf' => ['nullable', 'string', 'max:4'],
            'cep' => ['nullable', 'string', 'max:12'],
            'segmento' => ['nullable', 'string', 'max:40'],
            'frota_tamanho' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:'.implode(',', array_keys(Customer::STATUS))],
            'owner_id' => ['nullable', 'integer'],
            'valor_anual' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ]) + ['valor_anual_cents' => $request->filled('valor_anual') ? (int) round(((float) $request->valor_anual) * 100) : null];
    }
}
