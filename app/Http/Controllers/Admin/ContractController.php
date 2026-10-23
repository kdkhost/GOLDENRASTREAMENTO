<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Contract;
use App\Models\Customer;
use App\Support\Envio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Contratos da carteira. A equipe cadastra o documento, anexa o PDF assinado e é isso que
 * aparece no portal do cliente — rascunho fica fora da leitura dele.
 */
class ContractController extends Controller
{
    public function index(Request $request): View
    {
        $contratos = Contract::with('customer')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('numero', 'like', $t)->orWhere('titulo', 'like', $t)
                    ->orWhereHas('customer', fn ($c) => $c->where('nome', 'like', $t)));
            })
            ->latest()
            ->paginate(itens_por_pagina())
            ->withQueryString();

        return view('admin.contracts.index', [
            'contratos' => $contratos,
            'statuses' => Contract::STATUS,
            'tipos' => Contract::TIPOS,
            'kpis' => [
                'total' => Contract::count(),
                'vigentes' => Contract::whereIn('status', ['assinado', 'vigente'])->count(),
                'vencendo' => Contract::whereNotNull('vigencia_ate')
                    ->whereBetween('vigencia_ate', [now(), now()->addDays(30)])->count(),
                'sem_arquivo' => Contract::whereNull('arquivo')->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $contrato = new Contract([
            'customer_id' => $request->integer('cliente') ?: null,
            'tipo' => 'adesao',
            'status' => 'rascunho',
            'celebrado_em' => now()->toDateString(),
        ]);

        return $this->form($contrato);
    }

    public function edit(Contract $contrato): View
    {
        $contrato->load('customer');

        return $this->form($contrato);
    }

    private function form(Contract $contrato): View
    {
        return view('admin.contracts.form', [
            'contrato' => $contrato,
            'clientes' => Customer::orderBy('nome')->get(['id', 'nome', 'documento']),
            'statuses' => Contract::STATUS,
            'tipos' => Contract::TIPOS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request);
        $contrato = Contract::create($dados);

        Activity::log('contrato_criado', "Contrato {$contrato->numero}: {$contrato->titulo}.", $contrato);

        return redirect()->route('admin.contratos.edit', $contrato)->with('success', 'Contrato cadastrado.');
    }

    public function update(Request $request, Contract $contrato): RedirectResponse
    {
        $dados = $this->validar($request);
        $anterior = $contrato->arquivo;
        $contrato->update($dados);

        if ($anterior && $anterior !== $dados['arquivo']) {
            Envio::remover($anterior, Contract::CAMPO_ENVIO);
        }

        Activity::log('contrato_atualizado', "Contrato {$contrato->numero} atualizado.", $contrato);

        return back()->with('success', 'Contrato atualizado.');
    }

    public function destroy(Contract $contrato): RedirectResponse
    {
        $numero = $contrato->numero;
        Envio::remover($contrato->arquivo, Contract::CAMPO_ENVIO);
        $contrato->delete();

        Activity::log('contrato_excluido', "Contrato {$numero} excluído.");

        return redirect()->route('admin.contratos.index')->with('success', 'Contrato excluído.');
    }

    /**
     * Regras do cadastro. O PDF já está no disco quando chega aqui (entrou pelo dropzone),
     * então o que se valida é o caminho: precisa ser de `contratos/` e existir.
     */
    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'customer_id' => ['required', 'integer'],
            'titulo' => ['required', 'string', 'max:180'],
            'tipo' => ['required', 'in:'.implode(',', array_keys(Contract::TIPOS))],
            'status' => ['required', 'in:'.implode(',', array_keys(Contract::STATUS))],
            'arquivo' => ['nullable', 'string', 'max:255'],
            'celebrado_em' => ['nullable', 'date'],
            'vigencia_ate' => ['nullable', 'date'],
            'valor_mensal' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! Customer::find($dados['customer_id'])) {
            throw ValidationException::withMessages(['customer_id' => 'Escolha um cliente cadastrado.']);
        }

        $arquivo = $dados['arquivo'] ?? null;

        if ($arquivo !== null && ! Envio::caminhoConfere($arquivo, Contract::CAMPO_ENVIO)) {
            throw ValidationException::withMessages([
                'arquivo' => 'Este arquivo não foi carregado por aqui. Solte-o no campo de novo.',
            ]);
        }

        return [
            'customer_id' => (int) $dados['customer_id'],
            'titulo' => $dados['titulo'],
            'tipo' => $dados['tipo'],
            'status' => $dados['status'],
            'arquivo' => $arquivo,
            'celebrado_em' => $dados['celebrado_em'] ?? null,
            'vigencia_ate' => $dados['vigencia_ate'] ?? null,
            'valor_mensal_cents' => isset($dados['valor_mensal']) && $dados['valor_mensal'] > 0
                ? (int) round(((float) $dados['valor_mensal']) * 100)
                : null,
            'observacoes' => $dados['observacoes'] ?? null,
        ];
    }
}
