<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use App\Services\Seguranca\DoisFatores;
use App\Services\Seguranca\VigiaDeAcesso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * A porta do painel, e agora com porteiro.
 *
 * Três camadas, cada uma com um trabalho que a anterior não faz:
 *
 * - `throttle:10,1` da rota segura o **servidor** contra um atirador esbanjador;
 * - o `VigiaDeAcesso` segura a **conta**: conta por e-mail e por rede, trava, deixa rastro na
 *   auditoria, bate no sino da equipe e escreve para o dono da conta;
 * - o `DoisFatores` é a **segunda chave**: senha certa sozinha não abre mais a sala de quem tem o
 *   aplicativo ligado, e um telefone com a lista de senhas vazadas vira uma lista de endereços sem
 *   valor nenhum contra um código que nasce no bolso da pessoa.
 *
 * Nada aqui grita o motivo do erro para quem não é da casa. A resposta é sempre a mesma frase —
 * "credenciais inválidas ou conta inativa" — porque a tela de login é também o lugar onde se
 * descobre, de graça, quais endereços têm conta ativa no sistema.
 */
class AuthController extends Controller
{
    /** A chave da sessão que guarda a meia-porta: senha certa, segundo fator ainda não dado. */
    private const DESAFIO = 'dois_fatores_pendente';

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request, VigiaDeAcesso $vigia): RedirectResponse
    {
        $credenciais = $request->validate([
            'email' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:500'],
        ]);

        $email = trim($credenciais['email']);

        if ($segundos = $vigia->bloqueadoPor($email, (string) $request->ip())) {
            return $this->recusado($request, 'Foram tentativas demais. Esta porta está fechada e reabre em '.$this->prazo($segundos).'.');
        }

        $usuario = User::where('email', $email)->first();
        $abriu = Auth::attempt(
            ['email' => $email, 'password' => $credenciais['password'], 'status' => 'ativo'],
            $request->boolean('remember'),
        );

        if (! $abriu) {
            $vigia->falhou($usuario, $email, $request);

            return $this->recusado($request, 'Credenciais inválidas ou conta inativa.');
        }

        $vigia->acertou($email, $request);
        $request->session()->regenerate();

        if (! $usuario->doisFatoresAtivo()) {
            $this->abrirJanela($request, $usuario, $vigia);

            return redirect()->intended(route('admin.dashboard'));
        }

        // Meia-porta: a senha abriu, o segundo fator ainda não. A pessoa sai da autenticação de
        // propósito — uma sessão já logada seria uma sessão logada sem o fator que acabamos de
        // exigir. O que fica na sessão é a identidade à espera do código, com prazo.
        // A cookie "lembrar" também não atravessa: ela é uma porta que se abre sozinha, e abriria
        // justamente a que decidimos não abrir antes do código.
        $lembre = $request->boolean('remember');
        Auth::logout();
        $request->session()->put(self::DESAFIO, [
            'usuario' => $usuario->id,
            'lembre' => $lembre,
            'desde' => now()->timestamp,
        ]);

        return redirect()->route('login.dois-fatores');
    }

    /** A tela do código de seis dígitos — ou a porta de novo, para quem não está no meio disto. */
    public function showDesafio(Request $request, VigiaDeAcesso $vigia): RedirectResponse|View
    {
        $usuario = $this->desafiado($request);

        if ($usuario === null) {
            return redirect()->route('login');
        }

        return view('auth.dois-fatores', [
            'nome' => $usuario->name,
            'restam' => max(0, $vigia->limite() - $vigia->tentativasDoCodigo($usuario)),
        ]);
    }

    /**
     * O código (ou um dos oito de recuperação) abre a porta.
     *
     * Os dois caminhos entram aqui, porque um código de recuperação é justamente o plano para o
     * celular que não está na mão. A diferença é o rastro: o queima-se o código de recuperação e a
     * casa registra que foi por ele — se a conta do chefe abre um dia por um código que ele perdeu,
     * a auditoria diz a hora.
     */
    public function desafio(Request $request, VigiaDeAcesso $vigia, DoisFatores $dois): RedirectResponse
    {
        $pedido = $request->validate([
            'codigo' => ['required', 'string', 'max:32'],
        ]);

        $pendente = $request->session()->get(self::DESAFIO);
        $usuario = $this->desafiado($request, $pendente);

        if ($usuario === null) {
            return redirect()->route('login')->with('warning', 'Este acesso expirou. Comece de novo.');
        }

        $lembre = (bool) (is_array($pendente) ? ($pendente['lembre'] ?? false) : false);

        if ($espera = $vigia->codigoBloqueadoPor($usuario)) {
            return back()->withErrors(['codigo' => 'Códigos demais tentados. Espere '.$this->prazo($espera).' e tente de novo.']);
        }

        $codigo = trim($pedido['codigo']);
        $recuperacao = Str::contains($codigo, '-');
        $segredo = (string) $usuario->two_factor_secret;
        $ok = $recuperacao
            ? $dois->queimarCodigoDeRecuperacao($usuario, $codigo)
            : ($segredo !== '' && $dois->codigoValido($segredo, $codigo));

        if (! $ok) {
            $vigia->codigoFalhou($usuario);

            Activity::log(
                'dois_fatores_falhou',
                'Segundo fator recusado para '.$usuario->email.'.',
                $usuario,
                ['ip' => $request->ip(), 'recuperacao' => $recuperacao],
            );

            return back()->withErrors(['codigo' => 'Código inválido ou já usado.']);
        }

        $vigia->codigoAcertou($usuario);
        $request->session()->forget(self::DESAFIO);

        Auth::login($usuario, $lembre);
        $request->session()->regenerate();

        Activity::log(
            $recuperacao ? 'login_codigo_de_recuperacao' : 'login',
            $recuperacao
                ? 'Entrou no painel com um código de recuperação do dois fatores.'
                : 'Login realizado no painel com segundo fator.',
            $usuario,
            ['ip' => $request->ip()],
        );

        $vigia->abriuSessao($usuario, $request);

        return redirect()->intended(route('admin.dashboard'));
    }

    /** Abandona o meio do caminho: fecha a meia-porta e devolve a pessoa para o login. */
    public function abandonarDesafio(Request $request): RedirectResponse
    {
        $request->session()->forget(self::DESAFIO);

        return redirect()->route('login')->with('info', 'Acesso cancelado.');
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($user = Auth::user()) {
            Activity::log('logout', 'Sessão encerrada.', null, ['email' => $user->email]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('site.home')->with('info', 'Você saiu do painel.');
    }

    /** A conta que ficou na meia-porta, se a meia-porta ainda vale. */
    private function desafiado(Request $request, ?array $pendente = null): ?User
    {
        $dados = $pendente ?? $request->session()->get(self::DESAFIO);

        if (! is_array($dados) || ! isset($dados['usuario'], $dados['desde'])) {
            return null;
        }

        $validade = max(1, (int) config('seguranca.desafio_minutos', 10)) * 60;

        if ((int) $dados['desde'] + $validade < now()->timestamp) {
            $request->session()->forget(self::DESAFIO);

            return null;
        }

        $usuario = User::find($dados['usuario']);

        if ($usuario === null || $usuario->status !== 'ativo' || ! $usuario->doisFatoresAtivo()) {
            $request->session()->forget(self::DESAFIO);

            return null;
        }

        return $usuario;
    }

    /** Senha certa, segundo fator certo (ou dispensado): a janela é registrada e anunciada. */
    private function abrirJanela(Request $request, User $usuario, VigiaDeAcesso $vigia): void
    {
        Activity::log('login', 'Login realizado no painel.', $usuario, ['ip' => $request->ip()]);

        $vigia->abriuSessao($usuario, $request);
    }

    private function recusado(Request $request, string $mensagem): RedirectResponse
    {
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $mensagem]);
    }

    /** O prazo em linguagem de gente: "40 segundos" ou "12 minutos", nunca "743". */
    private function prazo(int $segundos): string
    {
        if ($segundos < 60) {
            return 'menos de um minuto';
        }

        $minutos = (int) ceil($segundos / 60);

        return $minutos.($minutos === 1 ? ' minuto' : ' minutos');
    }
}
