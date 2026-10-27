<?php

namespace App\Http\Controllers\Portal;

use App\Mail\RedefinirSenhaPortal;
use App\Models\Activity;
use App\Models\Customer;
use App\Support\PortalDoCliente;
use App\Support\Supervisao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Entrada do portal: login, auto-cadastro e recuperação de acesso.
 *
 * O cadastro não cria ficha nova por padrão — ele procura o cliente que já existe no CRM
 * (Admin → Clientes) pelo documento e pelo e-mail e entrega a senha a essa ficha. Só quem
 * nunca passou pela Golden ganha cadastro novo. Assim o pedido feito como convidado, o lead
 * antigo e o contrato da equipe aparecem na primeira tela, sem digitação.
 */
class PortalAuthController extends PortalBaseController
{
    /** Minutos de validade do link de recuperação. */
    private const VALIDADE_LINK = 60;

    public function entrar(): View|RedirectResponse
    {
        if (Auth::guard('cliente')->check()) {
            return redirect()->route('portal.inicio');
        }

        return view('portal.entrar');
    }

    public function autenticar(Request $request): RedirectResponse
    {
        $credenciais = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string'],
        ]);

        // O e-mail de acesso é a coluna própria do portal: o CRM aceita fichas com o mesmo
        // e-mail do vendedor, e a autenticação nunca pode escolher uma delas por engano.
        if (! Auth::guard('cliente')->attempt(
            ['email_portal' => mb_strtolower($credenciais['email']), 'password' => $credenciais['password'], 'status' => 'ativo'],
            $request->boolean('lembrar')
        )) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Não encontramos uma conta ativa do portal com esse e-mail e senha.']);
        }

        $request->session()->regenerate();
        // Login de verdade apaga qualquer janela de supervisão: a partir daqui quem escreve é o
        // dono da conta, e um marcador velho deixaria o `AcessoSupervisionado` travando um
        // formulário de quem acabou de se autenticar com a própria senha.
        Supervisao::limpar();
        $cliente = $this->cliente();
        $cliente->registrarAcesso();
        Activity::log('portal_login', 'Acesso ao portal.', $cliente, por: $cliente->nome);

        return redirect()->intended(route('portal.inicio'));
    }

    public function cadastro(): View|RedirectResponse
    {
        if (Auth::guard('cliente')->check()) {
            return redirect()->route('portal.inicio');
        }

        return view('portal.cadastro');
    }

    public function registrar(Request $request): RedirectResponse
    {
        if ($request->filled('website')) {
            // Honeypot: o campo é escondido do humano e preenchido só por robô.
            return redirect()->route('portal.cadastro');
        }

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'telefone' => ['required', 'string', 'max:32'],
            'documento' => ['required', 'string', 'max:24'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'consent' => ['required', 'accepted'],
        ]);

        $documento = PortalDoCliente::documento($dados['documento']);

        if (! in_array(strlen($documento), [11, 14], true)) {
            throw ValidationException::withMessages([
                'documento' => 'Informe o CPF (11 dígitos) ou o CNPJ (14 dígitos), com ou sem pontos.',
            ]);
        }

        $email = mb_strtolower($dados['email']);

        if (PortalDoCliente::emailEmUso($email)) {
            throw ValidationException::withMessages([
                'email' => 'Este e-mail já tem conta no portal. Entre com a senha ou use "esqueci minha senha".',
            ]);
        }

        $cliente = DB::transaction(function () use ($dados, $email, $documento): Customer {
            $ficha = PortalDoCliente::candidatas($documento, $email)->first();
            $nova = $ficha === null;

            $criada = PortalDoCliente::vincular($dados + ['email' => $email, 'documento' => $documento], $ficha);
            $criada->vincularPedidosSemFicha();

            Activity::log(
                $nova ? 'portal_criou_ficha' : 'portal_autocadastro',
                $nova ? 'Ficha criada pelo auto-cadastro do portal.' : 'Ficha do CRM ganhou acesso ao portal.',
                $criada,
                por: $criada->nome
            );

            return $criada;
        });

        Auth::guard('cliente')->login($cliente);
        $request->session()->regenerate();
        Supervisao::limpar();
        $cliente->registrarAcesso();

        return redirect()->route('portal.inicio')->with(
            'success',
            'Conta criada. Seus pedidos e contratos já estão aqui — bem-vindo ao portal Golden.'
        );
    }

    public function esquecer(): View|RedirectResponse
    {
        if (Auth::guard('cliente')->check()) {
            return redirect()->route('portal.inicio');
        }

        return view('portal.esquecer');
    }

    public function esquecerSenha(Request $request): RedirectResponse
    {
        $dados = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $cliente = PortalDoCliente::porEmailDeAcesso($dados['email']);

        if ($cliente !== null) {
            $link = URL::temporarySignedRoute(
                'portal.senha.redefinir',
                now()->addMinutes(self::VALIDADE_LINK),
                ['cliente' => $cliente->id]
            );

            try {
                Mail::to($cliente->emailDeAcesso())->queue(new RedefinirSenhaPortal($cliente, $link, self::VALIDADE_LINK));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // A resposta é a mesma com ou sem conta: quem digita um e-mail aqui não descobre
        // quais endereços pertencem a clientes da Golden.
        return back()->withInput($request->only('email'))->with('success',
            'Se este e-mail tiver conta no portal, enviamos o link de redefinição. Ele expira em '.self::VALIDADE_LINK.' minutos.');
    }

    public function redefinir(Request $request): View|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('portal.login')->with('error', 'Este link de recuperação expirou. Peça um novo.');
        }

        return view('portal.redefinir', ['cliente' => $this->destinoDaRecuperacao($request)]);
    }

    public function salvarSenhaNova(Request $request): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('portal.login')->with('error', 'Este link de recuperação expirou. Peça um novo.');
        }

        $dados = $request->validate(['password' => ['required', 'string', 'min:8', 'confirmed']]);
        $cliente = $this->destinoDaRecuperacao($request);

        $cliente->password = $dados['password'];
        $cliente->save();

        Activity::log('portal_senha_redefinida', 'Senha do portal alterada pelo link de recuperação.', $cliente, por: $cliente->nome);

        return redirect()->route('portal.login')->with('success', 'Senha nova cadastrada. Entre com ela.');
    }

    public function sair(Request $request): RedirectResponse
    {
        if ($cliente = Auth::guard('cliente')->user()) {
            Activity::log('portal_sair', 'Sessão do portal encerrada.', $cliente, por: $cliente->nome);
        }

        // Só este guard sai: a sessão da equipe, se estiver aberta no mesmo navegador, continua.
        Auth::guard('cliente')->logout();
        Supervisao::limpar();

        return redirect()->route('site.home')->with('info', 'Você saiu do portal.');
    }

    /** A ficha dona do link assinado. Sem ela a tela não tem a quem responder. */
    private function destinoDaRecuperacao(Request $request): Customer
    {
        return Customer::whereNotNull('password')->findOrFail($request->integer('cliente'));
    }
}
