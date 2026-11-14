<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function index(PermissionService $permissions): View
    {
        $contagemPorPerfil = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->all();

        return view('admin.permissoes.index', [
            'perfis' => PermissionService::PERFIS,
            'modulos' => PermissionService::MODULOS,
            'matriz' => $permissions->matriz(),
            'contagemPorPerfil' => $contagemPorPerfil,
        ]);
    }

    public function update(Request $request, PermissionService $permissions): RedirectResponse
    {
        $permissions->salvarMatriz((array) $request->input('permissoes', []));

        Activity::log(
            'permissoes_atualizadas',
            'Atualizou a matriz de permissões (ver, editar e deletar) dos perfis de acesso.',
            $request->user()
        );

        return redirect()
            ->route('admin.permissoes.index')
            ->with('success', 'Configurações de permissões salvas e aplicadas imediatamente a todos os perfis!');
    }

    public function restaurar(Request $request, PermissionService $permissions): RedirectResponse
    {
        $permissions->restaurarPadroes();

        Activity::log(
            'permissoes_restauradas',
            'Restaurou todas as permissões de acesso aos padrões de fábrica.',
            $request->user()
        );

        return redirect()
            ->route('admin.permissoes.index')
            ->with('success', 'Permissões restauradas para o padrão de fábrica com sucesso!');
    }
}
