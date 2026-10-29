<?php

namespace App\Http\Middleware;

use App\Services\Seguranca\DoisFatores;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A porta que não abre sem a segunda chave, quando a casa decidiu que ela é obrigatória.
 *
 * A política mora em Configurações → Segurança do acesso (`ninguem`, `administradores`, `equipe`),
 * e esta classe é o que transforma a escolha em fato: sem ela, "exigir o dois fatores" seria uma
 * frase escrita num campo de texto.
 *
 * Quem ainda não tem a chave acesa não é expulsado nem fica preso num beco sem saída — vai para a
 * própria tela de acendimento, onde a pessoa configura o aplicativo e entra no painel com o mesmo
 * login que já fez. A senha continuou correta o tempo todo; o que faltava era a segunda prova.
 *
 * A rota de acendimento fica fora do prefixo `/admin`, então não há laço: para qualquer outra rota
 * do painel a resposta é um redirecionamento, nunca um `abort`.
 */
class GarantirDoisFatores
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario !== null
            && ! $usuario->doisFatoresAtivo()
            && DoisFatores::exige($usuario)) {
            return redirect()->route('dois-fatores.configurar')
                ->with('warning', 'A casa exige verificação em duas etapas para o seu perfil. Acenda a sua agora: leva um minuto no celular.');
        }

        return $next($request);
    }
}
