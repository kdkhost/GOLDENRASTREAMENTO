<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\SessaoDoPainel;
use App\Models\User;
use App\Support\Envio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
                $q->where(fn ($w) => $w->where('name', 'like', $t)
                    ->orWhere('email', 'like', $t)
                    ->orWhere('cargo', 'like', $t)
                    ->orWhere('setor', 'like', $t));
            })
            ->when($request->filled('perfil'), fn ($q) => $q->where('role', $request->perfil))
            ->when($request->filled('situacao'), fn ($q) => $q->where('status', $request->situacao))
            ->orderBy('name');
    }

    public function create(): View
    {
        return view('admin.users.form', $this->dadosDaFicha(
            new User(['role' => 'vendedor', 'status' => 'ativo'])
        ));
    }

    public function edit(User $user): View
    {
        // Quem entrou antes desta coluna existir não fica sem a informação: a auditoria já
        // registrava cada login, e é dela que sai o último acesso enquanto a coluna está vazia.
        $ultimoLogin = Activity::where('user_id', $user->id)
            ->whereIn('action', ['login', 'login_codigo_de_recuperacao'])
            ->latest()->first();

        return view('admin.users.form', $this->dadosDaFicha($user) + [
            'ultimoAcesso' => $user->ultimo_acesso_em ?? $ultimoLogin?->created_at,
            'ultimoAcessoIp' => $user->ultimo_acesso_ip ?? ($ultimoLogin?->meta['ip'] ?? null),
            'janelas' => SessaoDoPainel::where('user_id', $user->id)->orderByDesc('ultimo_visto_em')->get(),
            'atividade' => Activity::where('user_id', $user->id)->latest()->limit(12)->get(),
            'totalLeads' => $user->leads()->count(),
        ]);
    }

    /** O que as duas telas (novo e editar) leem igual; a de editar acrescenta o histórico. */
    private function dadosDaFicha(User $usuario): array
    {
        return [
            'usuario' => $usuario,
            'perfis' => self::PERFIS,
            'situacoes' => self::SITUACOES,
        ];
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
        $avatarAnterior = $user->avatar;
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

        // Trocar a foto não deixa a antiga ocupando disco em silêncio.
        if ($avatarAnterior !== null && $avatarAnterior !== $user->avatar) {
            Envio::remover($avatarAnterior, 'usuario_avatar');
        }

        Activity::log('usuario_editado', "Usuário {$user->name} atualizado.", $user);

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuário atualizado.');
    }

    /** Uma lista só: o que o select oferece é exatamente o que a validação aceita. */
    private function validar(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160',
                $user ? Rule::unique('users')->ignore($user->id) : 'unique:users,email',
            ],
            'telefone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', Rule::in(array_keys(self::PERFIS))],
            'status' => [$user ? 'required' : 'nullable', Rule::in(array_keys(self::SITUACOES))],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'cargo' => ['nullable', 'string', 'max:80'],
            'setor' => ['nullable', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:500'],
            'admissao' => ['nullable', 'date'],
            'avatar' => ['nullable', 'string', 'max:255'],
        ]);

        // Só se aceita a foto que o dropzone gravou na pasta do campo: o valor chega como texto,
        // e um caminho forjado não pode virar imagem da ficha.
        $data['avatar'] = $data['avatar'] ?? null;

        if ($data['avatar'] !== null && ! Envio::caminhoConfere($data['avatar'], 'usuario_avatar')) {
            throw ValidationException::withMessages([
                'avatar' => 'Envie a foto pelo campo de imagem da ficha.',
            ]);
        }

        return $data;
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Você não pode excluir a si mesmo.');
        }

        Envio::remover($user->avatar, 'usuario_avatar');
        $user->delete();
        Activity::log('usuario_excluido', "Usuário {$user->name} excluído.");

        return back()->with('success', 'Usuário excluído.');
    }
}
