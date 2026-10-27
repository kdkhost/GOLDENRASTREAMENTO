<?php

namespace App\Http\Middleware;

use App\Models\Activity;
use App\Support\Supervisao;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * O cadeado da janela de supervisão.
 *
 * Roda em toda requisição do portal autenticado, depois de `cliente`. Sem marcador na sessão ele
 * não existe: o dono da conta escreve como sempre escreveu. Com marcador, o portal vira vitrine —
 * nenhuma POST, PUT, PATCH ou DELETE passa, e as duas únicas escritas permitidas são as saídas,
 * que desfazem a própria janela.
 *
 * A barreira mora no meio da estrada, e não nos botões das telas, de propósito. Esconder o
 * "Enviar" em cada formulário do portal seriam cinco promessas para manter — e a sexta, a que
 * alguém esquece, é a que grava. Aqui não há tela nova nem formulário a lembrar de cobrir: o
 * pedido chega, o método é de escrita, a resposta é não.
 *
 * Marcador presente e janela inválida (venceu, trocou de ficha, trocou de login no painel, ou
 * alguém fechou o interruptor em Configurações) não devolve a conta ao dono: derruba o guard
 * `cliente` e manda para a entrada. Sessão que expira e continua navegando é a falha que este
 * middleware existe para não ter.
 */
class AcessoSupervisionado
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Supervisao::haMarcador()) {
            return $next($request);
        }

        $janela = Supervisao::dados();

        if ($janela === null) {
            Auth::guard('cliente')->logout();
            Supervisao::limpar();

            return redirect()->route('portal.login')
                ->with('warning', 'A janela de supervisão se encerrou. Entre com a sua própria conta, se for o cliente.');
        }

        if ($this->soLeitura($request)) {
            return $next($request);
        }

        Activity::log(
            'portal_supervisao_bloqueado',
            'Tentou gravar na conta de outro cliente durante a supervisão.',
            Auth::guard('cliente')->user(),
            ['rota' => $request->route()?->getName(), 'metodo' => $request->getMethod()]
        );

        return redirect()->back()
            ->with('error', 'Você está dentro da conta de outra pessoa: a supervisão é somente leitura e nada aqui foi gravado.');
    }

    /** O que a janela aceita: o que não muda nada, e as duas saídas. */
    private function soLeitura(Request $request): bool
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return true;
        }

        return in_array((string) $request->route()?->getName(), Supervisao::PODE_ESCREVER, true);
    }
}
