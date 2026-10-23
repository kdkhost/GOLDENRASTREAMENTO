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
    public function index(): View
    {
        return view('admin.users.index', [
            'usuarios' => User::withCount('leads')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['usuario' => new User(['role' => 'vendedor', 'status' => 'ativo'])]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['usuario' => $user]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'telefone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', Rule::in(['admin', 'vendedor', 'visualizador'])],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['status'] = 'ativo';
        $user = User::create($data);

        Activity::log('usuario_criado', "Usuário {$user->name} ({$user->role}) criado.", $user);

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuário criado.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users')->ignore($user->id)],
            'telefone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', Rule::in(['admin', 'vendedor', 'visualizador'])],
            'status' => ['required', Rule::in(['ativo', 'inativo'])],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

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
