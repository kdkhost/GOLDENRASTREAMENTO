@extends('admin.layouts.app')
@section('title', 'Permissões e Perfis')
@section('page_title', 'Configurações de permissões e perfis de acesso')
@section('page_description', 'Defina exatamente o que cada perfil (Admin, Vendedor, Contador, Marketing, Suporte e Visualizador) pode ver, alterar ou deletar.')

@section('page_actions')
    <a href="{{ route('admin.usuarios.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-users me-1"></i> Gerenciar Usuários
    </a>
    <form method="POST" action="{{ route('admin.permissoes.restaurar') }}" class="d-inline"
        onsubmit="return confirm('Deseja restaurar as permissões de todos os perfis para o padrão de fábrica?');">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-warning">
            <i class="fas fa-rotate-left me-1"></i> Restaurar Padrões
        </button>
    </form>
@endsection

@section('content')
@if(($contagemPorPerfil['superadmin'] ?? 0) === 0 && ! auth()->user()->isSuperAdmin())
    <div class="alert alert-warning border-warning d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 shadow-sm">
        <div>
            <h6 class="fw-bold mb-1"><i class="fas fa-crown me-1"></i> Nenhum Super Administrador definido ainda (0 / 2)</h6>
            <p class="small mb-0">Para assumir o controle máximo do sistema e proteger esta conta contra alterações de terceiros, promova sua conta agora. O sistema permite no máximo <strong>2 Super Administradores</strong>.</p>
        </div>
        <form method="POST" action="{{ route('admin.usuarios.promover-superadmin', auth()->user()) }}"
            onsubmit="return confirm('Deseja promover sua conta atual ({{ auth()->user()->name }}) para Super Administrador?');">
            @csrf
            <button type="submit" class="btn btn-dark btn-sm fw-bold">
                <i class="fas fa-crown text-warning me-1"></i> Tornar-me Super Administrador
            </button>
        </form>
    </div>
@endif

{{-- Visão Geral dos Perfis --}}
<div class="row g-3 mb-4">
    @foreach($perfis as $chavePerfil => $meta)
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-dark border border-secondary-subtle text-warning px-2 py-1">
                                <i class="fas {{ $meta['icone'] }} me-1"></i> {{ strtoupper($chavePerfil) }}
                            </span>
                            <span class="small text-secondary fw-semibold">
                                {{ $contagemPorPerfil[$chavePerfil] ?? 0 }} {{ ($contagemPorPerfil[$chavePerfil] ?? 0) === 1 ? 'usuário' : 'usuários' }}
                            </span>
                        </div>
                        <h6 class="fw-bold mb-1">{{ $meta['nome'] }}</h6>
                        <p class="small text-secondary mb-0">{{ $meta['descricao'] }}</p>
                    </div>
                    @if(!empty($meta['protegido']))
                        <div class="mt-2 pt-2 border-top border-secondary-subtle small text-danger fw-semibold">
                            <i class="fas fa-lock me-1"></i> Acesso Total Protegido
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Matriz de Permissões por Perfil --}}
<form method="POST" action="{{ route('admin.permissoes.update') }}">
    @csrf
    @method('PUT')

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom pt-3 px-4">
            <ul class="nav nav-tabs card-header-tabs" id="tabsPerfis" role="tablist">
                @php $primeiro = true; @endphp
                @foreach($perfis as $chavePerfil => $meta)
                    @if($chavePerfil === 'superadmin')
                        @continue
                    @endif
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $primeiro ? 'active fw-bold' : '' }}"
                            id="tab-{{ $chavePerfil }}"
                            data-bs-toggle="tab"
                            data-bs-target="#pane-{{ $chavePerfil }}"
                            type="button"
                            role="tab">
                            <i class="fas {{ $meta['icone'] }} me-1"></i>
                            {{ $meta['nome'] }}
                        </button>
                    </li>
                    @php $primeiro = false; @endphp
                @endforeach
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="tabsPerfisContent">
                @php
                    $primeiroPane = true;
                    $grupos = collect($modulos)->groupBy('grupo');
                @endphp
                @foreach($perfis as $chavePerfil => $meta)
                    @if($chavePerfil === 'superadmin')
                        @continue
                    @endif
                    <div class="tab-pane fade {{ $primeiroPane ? 'show active' : '' }}"
                        id="pane-{{ $chavePerfil }}"
                        role="tabpanel">

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-3 border-bottom">
                            <div>
                                <h5 class="fw-bold mb-1">
                                    <i class="fas {{ $meta['icone'] }} text-warning me-2"></i>{{ $meta['nome'] }}
                                </h5>
                                <p class="small text-secondary mb-0">{{ $meta['descricao'] }}</p>
                            </div>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-success" onclick="rbacMarcarPerfil('{{ $chavePerfil }}', 'tudo')">
                                    <i class="fas fa-check-double me-1"></i> Liberar Tudo
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="rbacMarcarPerfil('{{ $chavePerfil }}', 'leitura')">
                                    <i class="fas fa-eye me-1"></i> Somente Ver
                                </button>
                                <button type="button" class="btn btn-outline-danger" onclick="rbacMarcarPerfil('{{ $chavePerfil }}', 'nada')">
                                    <i class="fas fa-ban me-1"></i> Bloquear Tudo
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 46%;">Módulo / Área do Sistema</th>
                                        <th class="text-center" style="width: 18%;">
                                            <i class="fas fa-eye text-info me-1"></i> Ver / Acessar
                                        </th>
                                        <th class="text-center" style="width: 18%;">
                                            <i class="fas fa-pen-to-square text-warning me-1"></i> Criar / Alterar
                                        </th>
                                        <th class="text-center" style="width: 18%;">
                                            <i class="fas fa-trash text-danger me-1"></i> Deletar
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($grupos as $nomeGrupo => $itensGrupo)
                                        <tr class="table-secondary">
                                            <td colspan="4" class="fw-bold small text-uppercase py-2">
                                                {{ $nomeGrupo }}
                                            </td>
                                        </tr>
                                        @foreach($modulos as $chaveModulo => $modMeta)
                                            @if($modMeta['grupo'] !== $nomeGrupo)
                                                @continue
                                            @endif
                                            @php
                                                $permAtual = $matriz[$chavePerfil][$chaveModulo] ?? [];
                                                $acoesDisponiveis = $modMeta['acoes'];
                                            @endphp
                                            <tr>
                                                <td class="fw-semibold">{{ $modMeta['nome'] }}</td>

                                                @foreach(['ver', 'editar', 'deletar'] as $acao)
                                                    <td class="text-center">
                                                        @if(in_array($acao, $acoesDisponiveis, true))
                                                            <div class="form-check form-switch d-inline-flex justify-content-center m-0">
                                                                <input class="form-check-input rbac-check rbac-{{ $chavePerfil }} rbac-acao-{{ $acao }}"
                                                                    type="checkbox"
                                                                    role="switch"
                                                                    name="permissoes[{{ $chavePerfil }}][{{ $chaveModulo }}][{{ $acao }}]"
                                                                    value="1"
                                                                    id="perm_{{ $chavePerfil }}_{{ $chaveModulo }}_{{ $acao }}"
                                                                    {{ !empty($permAtual[$acao]) ? 'checked' : '' }}>
                                                            </div>
                                                        @else
                                                            <span class="text-secondary small">—</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @php $primeiroPane = false; @endphp
                @endforeach
            </div>
        </div>

        <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2 py-3 px-4">
            <span class="small text-secondary">
                <i class="fas fa-shield-halved text-warning me-1"></i>
                O perfil <strong>Super Administrador</strong> possui acesso irrestrito garantido e não pode ser bloqueado.
            </span>
            <button type="submit" class="btn btn-golden fw-bold px-4">
                <i class="fas fa-save me-2"></i> Salvar Configurações de Permissões
            </button>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<script>
    function rbacMarcarPerfil(perfil, modo) {
        const checkboxes = document.querySelectorAll('.rbac-' + perfil);
        checkboxes.forEach(function (el) {
            if (modo === 'tudo') {
                el.checked = true;
            } else if (modo === 'nada') {
                el.checked = false;
            } else if (modo === 'leitura') {
                el.checked = el.classList.contains('rbac-acao-ver');
            }
        });
    }
</script>
@endsection
