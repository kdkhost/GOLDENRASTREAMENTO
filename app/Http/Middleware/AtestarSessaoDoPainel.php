<?php

namespace App\Http\Middleware;

use App\Models\Activity;
use App\Models\User;
use App\Services\Seguranca\VigiaDeAcesso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Atesta, a cada pedido da equipe, que a janela aberta ainda é uma janela autorizada.
 *
 * Login seguro não é só a porta: é o que acontece depois. Duas coisas caem aqui, e nenhuma delas
 * valia nada antes:
 *
 * 1. **Uma janela encerrada pelo painel está encerrada de verdade.** "Sair dos outros aparelhos" e o
 *    teto de janelas abertas apagam a linha em `sessoes_do_painel`; sem este atestado, o notebook do
 *    escritório continuaria trabalhando por cima do botão, porque a cookie do navegador dele estava
 *    lá e nada consultava a lista.
 * 2. **A aba esquecida fecha.** O tempo de inatividade da tela de Segurança vale por janela, não só
 *    pela renovação da cookie: um painel aberto num computador do estoque às 8h continua aberto às
 *    20h se ninguém conferir.
 *
 * A troca de senha tem outro dono: `$middleware->authenticateSessions()` no `bootstrap/app.php`, que
 * é o mecanismo da própria framework para derrubar a sessão quando a cifra da conta muda.
 *
 * Quem entrou antes desta versão — e o `actingAs` dos testes, que monta o usuário sem abrir sessão —
 * não tem carimbo na sessão: a janela é adotada, não expulsa. É o `VigiaDeAcesso` quem decide.
 */
class AtestarSessaoDoPainel
{
    public function __construct(private VigiaDeAcesso $vigia) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Só a equipe passa por aqui. O portal do cliente é outro guarda (`cliente`, sobre `Customer`)
        // e outra lista de janelas: o painel não tem nada a cobrar de quem nunca entrou nele.
        if (! $usuario instanceof User) {
            return $next($request);
        }

        if ($this->vigia->podeContinuar($usuario, $request)) {
            return $next($request);
        }

        Activity::log(
            'sessao_encerrada',
            'Janela encerrada: a sessão não estava mais na lista de janelas abertas.',
            $usuario,
            ['ip' => $request->ip()],
        );

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $mensagem = 'Sua sessão foi encerrada por segurança. Entre de novo para continuar.';

        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'mensagens' => [$mensagem], 'redirecionar' => route('login')], 401);
        }

        return redirect()->route('login')->with('warning', $mensagem);
    }
}
