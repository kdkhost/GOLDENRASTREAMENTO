<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ControleAcesso
{
    /**
     * Restringe o acesso por papel. Uso: ->middleware('role:admin') ou 'role:admin,vendedor'
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Sessão expirada.', 'redirect' => route('login')], 401);
            }

            return redirect()->route('login')->with('warning', 'Acesso restrito. Faça login para continuar.');
        }

        $user = auth()->user();

        if ($user->status !== 'ativo') {
            auth()->logout();
            $request->session()->invalidate();
            return redirect()->route('login')->with('error', 'Sua conta está inativa. Contate o administrador.');
        }

        if (!empty($roles) && !in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Acesso negado.'], 403);
            }

            abort(403, 'Você não tem permissão para acessar esta área.');
        }

        return $next($request);
    }
}
