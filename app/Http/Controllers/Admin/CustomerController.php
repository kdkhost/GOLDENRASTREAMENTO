<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\User;
use App\Services\Whmcs\Cliente as Financeiro;
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

    /**
     * Refaz o vínculo desta ficha com o financeiro, pelo e-mail de acesso.
     *
     * O vínculo nasce sozinho na primeira visita ao portal e, depois disso, é o número gravado
     * que manda: nenhuma visita volta a conferir e-mail. É o que torna a tela rápida, e é também
     * o que deixa o ponteiro envelhecer — conta apagada e recriada no WHMCS, ou e-mail corrigido
     * numa das pontas, e a ficha segue apontando para um número que já não é daquela pessoa.
     * Este botão é o único caminho de volta que não passa pelo banco por fora.
     *
     * Os três desfechos abaixo obedecem a uma regra só: **só se escreve aqui o que o financeiro
     * confirmou.** `ok` grava o id que ele devolveu; `sem_conta` desfaz o ponteiro velho, porque
     * uma ficha ligada a uma conta que o próprio e-mail da pessoa não confirma é a porta de
     * entrada da fatura alheia; e qualquer falha de conversa não muda nada — vínculo nenhum se
     * desfaz porque o servidor ficou mudo por doze segundos.
     */
    public function revincular(Customer $cliente): RedirectResponse
    {
        $financeiro = app(Financeiro::class);

        if (! $financeiro->configurado()) {
            return back()->with('error', 'O financeiro ainda não tem as três credenciais em Sistema → Configurações → Chaves de integração → WHMCS. Sem elas não há com quem falar.');
        }

        $email = (string) $cliente->emailDeAcesso();

        if ($email === '') {
            return back()->with('error', "A ficha de {$cliente->nome} não tem e-mail nenhum — é pelo e-mail de acesso que o financeiro reconhece a conta.");
        }

        $sonda = $financeiro->sonda($email);
        $antes = $cliente->whmcs_id;

        if ($sonda['estado'] === 'ok') {
            return $this->gravarVinculo($cliente, (int) $sonda['id'], $antes, $email);
        }

        if ($sonda['estado'] === 'sem_conta') {
            if ($antes === null) {
                return back()->with('warning', $sonda['texto'].' Enquanto o e-mail for este, as telas de financeiro vão dizer que não há conta — e é o cadastro de uma das pontas que precisa ser conferido.');
            }

            $cliente->forceFill(['whmcs_id' => null])->saveQuietly();
            Activity::log('cliente_desvinculado', "Revinculou ao financeiro: o e-mail {$email} não tem conta lá, e o vínculo com a conta #{$antes} foi desfeito.", $cliente);

            return back()->with('warning', "O financeiro não confirma conta nenhuma para {$email}, e o vínculo com a conta #{$antes} foi desfeito. Se a pessoa tem conta lá, é o e-mail de uma das pontas que está outro — corrija e revincule de novo.");
        }

        return back()->with('error', $sonda['titulo'].' '.$sonda['texto'].' Nada foi alterado nesta ficha.');
    }

    /**
     * Grava o id que o financeiro confirmou, depois de checar que aquela conta não é de outra
     * ficha. A coluna é `unique` de propósito — uma conta lá, uma cá — e deixar a violação subir
     * como exceção devolveria uma página de erro no meio de um atendimento.
     */
    private function gravarVinculo(Customer $cliente, int $id, ?int $antes, string $email): RedirectResponse
    {
        $dona = Customer::where('whmcs_id', $id)->whereKeyNot($cliente->id)->first();

        if ($dona !== null) {
            return back()->with('error', "O financeiro confirma {$email} como a conta #{$id}, mas essa conta já está vinculada à ficha de {$dona->nome}. Desfaça um dos dois antes de gravar: uma conta do financeiro só pode ser uma ficha aqui.");
        }

        if ($antes === $id) {
            return back()->with('info', "Nada mudou: {$email} já é a conta #{$id}, e era esse o número gravado.");
        }

        $cliente->forceFill(['whmcs_id' => $id])->saveQuietly();
        Activity::log(
            'cliente_revinculado',
            $antes === null
                ? "Vinculou ao financeiro pelo e-mail {$email}: conta #{$id}."
                : "Revinculou ao financeiro pelo e-mail {$email}: a ficha apontava para #{$antes} e passou para #{$id}.",
            $cliente
        );

        return back()->with('success', $antes === null
            ? "A ficha está ligada à conta #{$id} do financeiro ({$email})."
            : "Vínculo trocado: a ficha apontava para a conta #{$antes} e passou para #{$id}, que é o que o financeiro confirma para {$email}.");
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
