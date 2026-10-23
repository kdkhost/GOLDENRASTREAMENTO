<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /** O que o select oferece é o que a validação aceita: filtro, cadastro e regra num lugar só. */
    private const PERFIS = ['vendedor' => 'Vendedor', 'visualizador' => 'Visualizador', 'admin' => 'Administrador'];

    private const SITUACOES = ['ativo' => 'Ativo', 'inativo' => 'Inativo'];

    public function index(Request $request): View
    {
        return view('admin.users.index', [
            'usuarios' => $this->query($request)->paginate(itens_por_pagina())->withQueryString(),
            'perfis' => self::PERFIS,
            'situacoes' => self::SITUACOES,
        ]);
    }

    /** A mesma régua da listagem e do filtro: quem filtra e quem pagina lêem isto. */
    private function query(Request $request)
    {
        return User::withCount('leads')
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%' . $request->busca . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('email', 'like', $t));
            })
            ->when($request->filled('perfil'), fn ($q) => $q->where('role', $request->perfil))
            ->when($request->filled('situacao'), fn ($q) => $q->where('status', $request->situacao))
            ->orderBy('name');
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'usuario' => new User(['role' => 'vendedor', 'status' => 'ativo']),
            'perfis' => self::PERFIS,
            'situacoes' => self::SITUACOES,
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'usuario' => $user,
            'perfis' => self::PERFIS,
            'situacoes' => self::SITUACOES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);

        $data['password'] = Hash::make($data['password']);
        $data['status'] = 'ativo';
        $user = User::create($data);

        Activity::log('usuario_criado', "Usuário {$user->name} ({$user->role}) criado.", $user);

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuário criado.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validar($request, $user);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Impede o admin de se rebaixar/desativar e ficar sem acesso
        if ($user->id === auth()->id()) {
            $data['role'] = $user->role;
            $data['status'] = 'ativo';
        }

        $user->update($data);
        Activity::log('usuario_editado', "Usuário {$user->name} atualizado.", $user);

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuário atualizado.');
    }

    /** Uma lista só: o que o select oferece é exatamente o que a validação aceita. */
    private function validar(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160',
                $user ? Rule::unique('users')->ignore($user->id) : 'unique:users,email',
            ],
            'telefone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', Rule::in(array_keys(self::PERFIS))],
            'status' => [$user ? 'required' : 'nullable', Rule::in(array_keys(self::SITUACOES))],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
        ]);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Você não pode excluir a si mesmo.');
        }

        $user->delete();
        Activity::log('usuario_excluido', "Usuário {$user->name} excluído.");

        return back()->with('success', 'Usuário excluído.');
    }
}
