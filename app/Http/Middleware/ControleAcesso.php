<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ControleAcesso
{
    /**
     * Restringe o acesso por perfil e pela matriz dinâmica de permissões (ver, editar, deletar).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! auth()->check()) {
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

        if ($user->role === 'superadmin') {
            return $next($request);
        }

        $routeName = optional($request->route())->getName();
        $permService = app(PermissionService::class);
        $modulo = $permService->moduloParaRota($routeName);

        if ($modulo !== null) {
            $acao = $permService->acaoParaRequisicao($request->method(), $routeName);

            if (! $permService->temPermissao($user, $modulo, $acao)) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Seu perfil não possui permissão para esta ação.'], 403);
                }

                abort(403, 'Você não tem permissão para acessar ou alterar esta área.');
            }

            return $next($request);
        }

        if (! empty($roles)) {
            $rolesPermitidos = $roles;
            if (in_array('admin', $roles, true)) {
                $rolesPermitidos[] = 'superadmin';
            }

            if (in_array('vendedor', $roles, true) && $user->podeGerir()) {
                return $next($request);
            }

            if (! in_array($user->role, $rolesPermitidos, true)) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Acesso negado.'], 403);
                }

                abort(403, 'Você não tem permissão para acessar esta área.');
            }
        }

        return $next($request);
    }
}
