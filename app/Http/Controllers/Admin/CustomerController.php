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
            'financeiro' => $this->financeiroDoCliente($cliente),
            // As listas da ficha paginam por nomes próprios: um link não mexe na outra.
            'interacoes' => $cliente->interactions()->with('user')->paginate(itens_por_pagina(), ['*'], 'historico')->withQueryString(),
            'pedidos' => $cliente->orders()->paginate(itens_por_pagina(), ['*'], 'pedidos')->withQueryString(),
            'chamados' => $cliente->tickets()->paginate(itens_por_pagina(), ['*'], 'chamados')->withQueryString(),
            'contratos' => $cliente->contracts()->paginate(itens_por_pagina(), ['*'], 'contratos')->withQueryString(),
            'tipos' => Interaction::TIPOS,
        ]);
    }

    /**
     * O retrato que o financeiro tem desta ficha, lido na hora em que a tela abre.
     *
     * Nada disto é copiado para a base local: o financeiro é a fonte, e a equipe precisa do dado
     * dele agora — não de uma cópia que envelhece. Também não passa pelos filtros de veiculação:
     * o contrato encerrado e a fatura paga são justamente o histórico que se consulta ao atender,
     * e a vitrine do cliente não manda aqui.
     *
     * As três leituras são sequenciais e dependem da primeira: sem o cadastro não há motivo para
     * perguntar por serviços e faturas. Com o financeiro mudo, a página espera um timeout — não
     * três.
     *
     * @return array{situacao: string, cadastro: array<string, mixed>|null, servicos: array|null, faturas: array|null}
     */
    private function financeiroDoCliente(Customer $cliente): array
    {
        $financeiro = app(Financeiro::class);

        if (! $financeiro->configurado()) {
            return ['situacao' => 'sem_configuracao', 'cadastro' => null, 'servicos' => null, 'faturas' => null];
        }

        if (blank($cliente->whmcs_id)) {
            return ['situacao' => 'sem_conta', 'cadastro' => null, 'servicos' => null, 'faturas' => null];
        }

        $id = (int) $cliente->whmcs_id;
        $cadastro = $financeiro->cadastro($id);

        if ($cadastro === null) {
            return ['situacao' => 'fora_do_ar', 'cadastro' => null, 'servicos' => null, 'faturas' => null];
        }

        return [
            'situacao' => 'ok',
            'cadastro' => $cadastro,
            'servicos' => $financeiro->servicos($id, null, false),
            'faturas' => $financeiro->faturas($id, null, false),
        ];
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
     * confirmou.** `ok` grava o id que ele devolveu; `sem_conta` abre uma pendência — o ponteiro
     * velho continua de pé até a pessoa confirmar na tela, porque a resposta "não acho ficha para
     * este e-mail" chega tanto de quem apagou a conta quanto de um e-mail digitado errado, e
     * desfazer no mesmo clique do botão seria apagar o vínculo de quem só quis conferir; e
     * qualquer falha de conversa não muda nada — vínculo nenhum se desfaz porque o servidor ficou
     * mudo por doze segundos.
     */
    public function revincular(Request $request, Customer $cliente): RedirectResponse
    {
        if ($request->boolean('desfazer')) {
            return $this->desfazerVinculo($cliente);
        }

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

        // A cada verificação refeita a pendência anterior perde o sentido: ela é a resposta de
        // uma chamada antiga, e confirmar dois cliques depois um "não tem conta" que já foi
        // superado por outro resultado desfaria o vínculo errado.
        $request->session()->forget('revinculo_pendente');

        if ($sonda['estado'] === 'ok') {
            return $this->gravarVinculo($cliente, (int) $sonda['id'], $antes, $email);
        }

        if ($sonda['estado'] === 'sem_conta') {
            if ($antes === null) {
                return back()->with('warning', $sonda['texto'].' Enquanto o e-mail for este, as telas de financeiro vão dizer que não há conta — e é o cadastro de uma das pontas que precisa ser conferido.');
            }

            $request->session()->put('revinculo_pendente', [
                'cliente' => $cliente->id,
                'antes' => $antes,
                'email' => $email,
            ]);

            return back()->with('warning', $sonda['titulo'].' O financeiro não confirma conta nenhuma para '.$email.', mas o vínculo com a conta #'.$antes.' continua de pé: nada foi desfeito sem a sua confirmação.');
        }

        return back()->with('error', $sonda['titulo'].' '.$sonda['texto'].' Nada foi alterado nesta ficha.');
    }

    /**
     * Completa a ficha com o que o financeiro sabe e ela ainda não tem.
     *
     * O vínculo é a porta: sem `whmcs_id` não há de qual conta puxar, e procurar pelo e-mail aqui
     * seria refazer a decisão do revínculo — dois caminhos para a mesma pergunta divergem no
     * primeiro e-mail corrigido. Só o que está em branco é gravado; o que a equipe digitou fica,
     * mesmo que o financeiro discorde, porque a ficha é da casa e o financeiro é a fonte do que
     * ninguém preencheu.
     */
    public function puxar(Customer $cliente): RedirectResponse
    {
        $financeiro = app(Financeiro::class);

        if (! $financeiro->configurado()) {
            return back()->with('error', 'O financeiro ainda não tem as três credenciais em Sistema → Configurações → Chaves de integração → WHMCS. Sem elas não há de onde puxar os dados.');
        }

        if (blank($cliente->whmcs_id)) {
            return back()->with('error', "A ficha de {$cliente->nome} ainda não está ligada ao financeiro. Use \"Revincular ao financeiro\" primeiro — é o vínculo que diz de qual conta os dados vêm.");
        }

        $dados = $financeiro->ficha((int) $cliente->whmcs_id);

        if ($dados === null) {
            return back()->with('error', 'O financeiro não respondeu à consulta da conta #'.$cliente->whmcs_id.'. Nada foi alterado nesta ficha.');
        }

        $resultado = $cliente->absorverDoFinanceiro($dados);
        $conta = 'conta #'.$cliente->whmcs_id;
        $mantidos = $resultado['mantidos'] !== []
            ? ' Mantidos por já estarem escritos: '.implode(', ', $resultado['mantidos']).'.'
            : '';
        $ausentes = $this->lacunasDoFinanceiro($resultado['ausentes']);

        if ($resultado['preenchidos'] === []) {
            return back()->with('info', 'Consultei a '.$conta.' do financeiro agora: não há dado novo para trazer.'.$mantidos.$ausentes);
        }

        Activity::log(
            'cliente_dados_do_financeiro',
            "Completou a ficha com a conta #{$cliente->whmcs_id} do financeiro: ".implode(', ', $resultado['preenchidos']).'.',
            $cliente
        );

        return back()->with('success', 'Preenchido pelo financeiro ('.$conta.'): '.implode(', ', $resultado['preenchidos']).'.'.$mantidos.$ausentes);
    }

    /**
     * A frase que explica por que um campo da ficha continua em branco depois do clique.
     *
     * Sem ela, "não puxou nada" é a leitura natural de quem vê o campo vazio — e puxar de novo
     * não muda o resultado, porque o dado nunca esteve no financeiro. Dizer isso aqui é o que
     * manda a próxima ação para o lugar certo: escrever na ficha ou corrigir a outra ponta.
     *
     * @param  array<int, string>  $ausentes
     */
    private function lacunasDoFinanceiro(array $ausentes): string
    {
        if ($ausentes === []) {
            return '';
        }

        if (count($ausentes) === count(Customer::CAMPOS_DO_FINANCEIRO)) {
            return ' O financeiro não tem nenhum dado de cadastro para esta conta — os campos seguem em branco e puxar de novo não muda isso.';
        }

        $campos = implode(', ', $ausentes);
        $seguem = count($ausentes) === 1 ? 'esse campo segue em branco' : 'esses campos seguem em branco';

        return " O financeiro não tem {$campos} nesta conta — {$seguem}, e puxar de novo não muda isso.";
    }

    /**
     * Desfaz o ponteiro que a verificação de cima deixou pendente. Os dois guardas existem porque
     * o botão que chega aqui é um segundo clique, e entre um e outro a ficha pode ter mudado de
     * mãos: a pendência precisa ser desta ficha e apontar para o número que ainda está gravado.
     * Sem pendência, ou com pendência velha, nada se desfaz — a tela refaz o caminho de volta.
     */
    private function desfazerVinculo(Customer $cliente): RedirectResponse
    {
        $pendente = session('revinculo_pendente');

        if (! is_array($pendente) || (int) $pendente['cliente'] !== $cliente->id) {
            return back()->with('error', 'Não há o que desfazer nesta ficha: a verificação que abriu esta pendência já passou. Aperte "Revincular ao financeiro" de novo para perguntar ao financeiro.');
        }

        if ((int) $pendente['antes'] !== (int) $cliente->whmcs_id) {
            session()->forget('revinculo_pendente');

            return back()->with('error', 'A pendência apontava para a conta #'.$pendente['antes'].' e esta ficha já aponta para '.($cliente->whmcs_id ? '#'.$cliente->whmcs_id : 'nenhuma conta').'. Refaça a verificação antes de desfazer.');
        }

        $antes = (int) $cliente->whmcs_id;
        $email = (string) $pendente['email'];

        $cliente->forceFill(['whmcs_id' => null])->saveQuietly();
        session()->forget('revinculo_pendente');
        Activity::log('cliente_desvinculado', "Desfez, depois de conferir, o vínculo com a conta #{$antes} — o e-mail {$email} não tem conta no financeiro.", $cliente);

        return back()->with('success', 'O vínculo com a conta #'.$antes.' foi desfeito, como você confirmou. As telas de financeiro ficam mudas até o e-mail de uma das pontas bater — corrija e revincule.');
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
            'plataforma' => ['nullable', 'string', 'max:60'],
            'usuario_plataforma' => ['nullable', 'string', 'max:190'],
            'status' => ['required', 'in:'.implode(',', array_keys(Customer::STATUS))],
            'owner_id' => ['nullable', 'integer'],
            'valor_anual' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ]) + ['valor_anual_cents' => $request->filled('valor_anual') ? (int) round(((float) $request->valor_anual) * 100) : null];
    }
}
