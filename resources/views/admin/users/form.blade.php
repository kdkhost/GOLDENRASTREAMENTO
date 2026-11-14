@extends('admin.layouts.app')
@php
    $editando = $usuario->exists;
    $euMesmo = $usuario->id === auth()->id();
    $foto = $usuario->avatarUrl();
    $duasChaves = $usuario->doisFatoresAtivo();
@endphp
@section('title', $editando ? 'Editar usuário' : 'Novo usuário')
@section('page_title', $editando ? 'Editar: '.$usuario->name : 'Novo usuário')
@section('page_description', 'O perfil define o que a pessoa enxerga no painel: vendedor atende leads, visualizador só consulta e administrador controla tudo.')

@section('page_actions')
<a href="{{ route('admin.usuarios.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
{{-- A ficha inteira é um formulário só, com as seções em abas: acesso, perfil, segurança e
     atividade. O avatar mora na coluna da direita, visível em qualquer aba, e o dropzone precisa
     estar dentro deste <form> — fora dele, a foto enviada nunca chegaria ao servidor. As abas de
     segurança e atividade só existem depois que a pessoa já tem cadastro. --}}
<form method="POST" action="{{ $editando ? route('admin.usuarios.update', $usuario) : route('admin.usuarios.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="abas" data-abas="usuario">
                <div class="abas-barra">
                    <ul class="nav abas-nav" role="tablist" aria-label="Seções do cadastro">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" type="button" role="tab" id="aba-acesso-botao"
                                data-bs-toggle="tab" data-bs-target="#aba-acesso" data-aba="acesso"
                                aria-controls="aba-acesso" aria-selected="true">
                                <i class="fas fa-key" aria-hidden="true"></i><span>Acesso</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" type="button" role="tab" id="aba-perfil-botao"
                                data-bs-toggle="tab" data-bs-target="#aba-perfil" data-aba="perfil"
                                aria-controls="aba-perfil" aria-selected="false">
                                <i class="fas fa-id-card" aria-hidden="true"></i><span>Perfil</span>
                            </button>
                        </li>
                        @if($editando)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" type="button" role="tab" id="aba-seguranca-botao"
                                    data-bs-toggle="tab" data-bs-target="#aba-seguranca" data-aba="seguranca"
                                    aria-controls="aba-seguranca" aria-selected="false">
                                    <i class="fas fa-shield-halved" aria-hidden="true"></i><span>Segurança</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" type="button" role="tab" id="aba-atividade-botao"
                                    data-bs-toggle="tab" data-bs-target="#aba-atividade" data-aba="atividade"
                                    aria-controls="aba-atividade" aria-selected="false">
                                    <i class="fas fa-clock-rotate-left" aria-hidden="true"></i><span>Atividade</span>
                                </button>
                            </li>
                        @endif
                    </ul>
                </div>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="aba-acesso" data-aba-painel="acesso" role="tabpanel" aria-labelledby="aba-acesso-botao">
                        <x-admin.card title="Acesso ao painel" icon="fa-key"
                            descricao="Quem entra, com qual perfil e por qual senha. O perfil é a régua do que a pessoa enxerga.">
                            <div class="row g-3">
                                <x-admin.field name="name" label="Nome" :value="$usuario->name" :required="true" maxlength="120" autocomplete="off" />
                                <x-admin.field name="email" type="email" label="E-mail de acesso" :value="$usuario->email" :required="true" col="col-12 col-md-6" maxlength="160" autocomplete="off" />
                                <x-admin.field name="telefone" label="Telefone" :value="$usuario->telefone" col="col-12 col-md-6" maxlength="32" :opcional="true" />
                                <x-admin.field name="role" type="select" label="Perfil" :value="$usuario->role ?: 'vendedor'" :options="$perfis" :col="$editando ? 'col-12 col-md-4' : 'col-12 col-md-6'" :required="true" :travado="$euMesmo" />
                                @if($editando)
                                    <x-admin.field name="status" type="select" label="Situação" :value="$usuario->status" :options="$situacoes" col="col-12 col-md-4" :required="true" :travado="$euMesmo" />
                                    <x-admin.field name="password" type="password" label="Nova senha" col="col-12 col-md-4" :opcional="true" minlength="8" autocomplete="new-password" />
                                @else
                                    <x-admin.field name="password" type="password" label="Senha inicial" col="col-12 col-md-6" :required="true" minlength="8" autocomplete="new-password" />
                                @endif
                            </div>
                            {{-- A explicação fica fora dos campos: dica acima do controle empurra o
                                 select e o campo de senha para baixo e a linha perde o alinhamento. --}}
                            <p class="form-hint mb-0">
                                @if($editando)
                                    Deixe a nova senha em branco para manter a atual.
                                    @if(!empty($podePromoverProprioSuperadmin))
                                        <span class="text-warning fw-semibold">Nenhum Super Administrador foi definido ainda — você pode alterar seu perfil para <strong>Super Administrador</strong> (máximo de {{ $maxSuperadmins ?? 2 }} no sistema).</span>
                                    @elseif($euMesmo)
                                        Perfil e situação ficam travados para você não tirar o próprio acesso.
                                    @endif
                                @else
                                    A senha precisa de pelo menos 8 caracteres; a pessoa pode trocá-la depois em Segurança do acesso.
                                @endif
                                @if(isset($totalSuperadmins, $maxSuperadmins))
                                    <span class="d-block mt-1">Super Administradores ativos: <strong>{{ $totalSuperadmins }} / {{ $maxSuperadmins }}</strong>.</span>
                                @endif
                            </p>
                        </x-admin.card>
                    </div>

                    <div class="tab-pane fade" id="aba-perfil" data-aba-painel="perfil" role="tabpanel" aria-labelledby="aba-perfil-botao">
                        <x-admin.card title="Perfil da pessoa" icon="fa-id-card"
                            descricao="O que a equipe lê sobre quem atende. Só a foto aparece fora do painel — o resto é da ficha interna.">
                            <div class="row g-3">
                                <x-admin.field name="cargo" label="Cargo" :value="$usuario->cargo" col="col-12 col-md-6" maxlength="80" :opcional="true" placeholder="Gerente comercial" />
                                <x-admin.field name="setor" label="Setor" :value="$usuario->setor" col="col-12 col-md-6" maxlength="80" :opcional="true" placeholder="Comercial" />
                                <x-admin.field name="admissao" type="date" label="Admissão" :value="$usuario->admissao?->format('Y-m-d')" col="col-12 col-md-6" :opcional="true" hint="A data em que a pessoa passou a fazer parte da equipe." />
                                <x-admin.field name="bio" type="textarea" label="Apresentação" :value="$usuario->bio" :rows="4" maxlength="500" :opcional="true"
                                    placeholder="Uma ou duas frases sobre o que esta pessoa atende e como falar com ela."
                                    hint="Até 500 caracteres. Aparece na ficha, ao lado do nome." />
                            </div>
                        </x-admin.card>
                    </div>

                    @if($editando)
                        <div class="tab-pane fade" id="aba-seguranca" data-aba-painel="seguranca" role="tabpanel" aria-labelledby="aba-seguranca-botao">
                            <x-admin.card title="Segurança do acesso" icon="fa-shield-halved"
                                descricao="A segunda chave, a última porta aberta e as janelas em nome desta pessoa.">
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <div class="perfil-fato">
                                            <span class="perfil-fato-rotulo">Segundo fator</span>
                                            <x-admin.status :rotulo="$duasChaves ? 'Ligado' : 'Desligado'" :tom="tom_por_flag($duasChaves)" />
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="perfil-fato">
                                            <span class="perfil-fato-rotulo">E-mail verificado</span>
                                            <x-admin.status :rotulo="$usuario->email_verified_at ? 'Verificado em '.$usuario->email_verified_at->format('d/m/Y') : 'Ainda não verificado'" :tom="tom_por_flag((bool) $usuario->email_verified_at)" />
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="perfil-fato">
                                            <span class="perfil-fato-rotulo">Último acesso</span>
                                            <span class="perfil-fato-valor">
                                                @if($ultimoAcesso)
                                                    {{ $ultimoAcesso->format('d/m/Y \à\s H:i') }}
                                                    @if($ultimoAcessoIp)<span class="text-secondary"> · {{ $ultimoAcessoIp }}</span>@endif
                                                @else
                                                    <span class="text-secondary">Desde que a ficha existe, esta pessoa ainda não abriu o painel.</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="perfil-fato">
                                            <span class="perfil-fato-rotulo">Janelas abertas</span>
                                            <span class="perfil-fato-valor">{{ $janelas->count() }} {{ $janelas->count() === 1 ? 'aparelho conectado' : 'aparelhos conectados' }}</span>
                                        </div>
                                    </div>
                                </div>

                                @if($janelas->isNotEmpty())
                                    <ul class="perfil-janelas">
                                        @foreach($janelas as $janela)
                                            <li>
                                                <i class="fas fa-desktop text-gold" aria-hidden="true"></i>
                                                <span class="fw-semibold">{{ $janela->dispositivo() }}</span>
                                                <span class="text-secondary small">{{ $janela->ip ?: 'rede não identificada' }} · {{ $janela->vistasHa() }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                <p class="form-hint mb-0">
                                    @if($euMesmo)
                                        Senha, segundo fator e encerramento de janelas ficam em
                                        <a href="{{ route('admin.seguranca.index') }}">Segurança do acesso</a>.
                                    @else
                                        A pessoa troca a própria senha e liga o segundo fator em Segurança do acesso.
                                    @endif
                                    Para redefinir a senha agora, use o campo <strong>Nova senha</strong> na aba Acesso.
                                </p>
                            </x-admin.card>
                        </div>

                        <div class="tab-pane fade" id="aba-atividade" data-aba-painel="atividade" role="tabpanel" aria-labelledby="aba-atividade-botao">
                            <x-admin.card title="Atividade" icon="fa-clock-rotate-left"
                                descricao="Os últimos movimentos desta pessoa na auditoria da casa.">
                                <div class="row g-3 mb-3">
                                    <div class="col-6 col-md-4">
                                        <div class="perfil-fato">
                                            <span class="perfil-fato-rotulo">Na casa desde</span>
                                            <span class="perfil-fato-valor">{{ $usuario->created_at?->format('d/m/Y') ?? '—' }}</span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <div class="perfil-fato">
                                            <span class="perfil-fato-rotulo">Leads sob cuidado</span>
                                            <span class="perfil-fato-valor">{{ $totalLeads }}</span>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <div class="perfil-fato">
                                            <span class="perfil-fato-rotulo">Ficha atualizada</span>
                                            <span class="perfil-fato-valor">{{ $usuario->updated_at?->format('d/m/Y H:i') ?? '—' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <ul class="perfil-linha-tempo">
                                    @forelse($atividade as $ato)
                                        <li>
                                            <span class="perfil-ponto" aria-hidden="true"></span>
                                            <div>
                                                <div class="small">{{ $ato->description ?: $ato->action }}</div>
                                                <div class="form-hint">{{ $ato->created_at?->format('d/m/Y H:i') }}@if($ato->ip) · {{ $ato->ip }}@endif</div>
                                            </div>
                                        </li>
                                    @empty
                                        <li class="text-secondary small">Nenhum movimento registrado ainda.</li>
                                    @endforelse
                                </ul>

                                <a href="{{ route('admin.activities.index', ['usuario' => $usuario->id]) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-list-check me-1"></i> Ver a auditoria completa
                                </a>
                            </x-admin.card>
                        </div>
                    @endif
                </div>
            </div>

            <div class="abas-salvar">
                <p class="abas-salvar-note mb-0">
                    {{ $editando
                        ? 'Acesso e Perfil são gravados juntos. Segurança e Atividade são leitura.'
                        : 'Depois de criar, a ficha ganha as abas de Segurança e Atividade.' }}
                </p>
                <button class="btn btn-golden"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar usuário' : 'Criar usuário' }}</button>
            </div>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Foto do usuário" icon="fa-image"
                descricao="É o rosto da pessoa no topo do painel e na lista da equipe. Sem foto, as iniciais do nome são desenhadas.">
                @include('partials.upload', [
                    'campo' => 'usuario_avatar',
                    'name' => 'avatar',
                    'valor' => old('avatar', $usuario->avatar),
                    'dica' => 'Quadrado e de frente: o painel recorta a foto em círculo.',
                ])

                <div class="perfil-previa">
                    @if($foto)
                        <img src="{{ $foto }}" alt="" width="48" height="48" class="thumb-avatar">
                    @else
                        <span class="avatar-letter avatar-letter-lg">{{ $usuario->iniciais() }}</span>
                    @endif
                    <div class="min-w-0">
                        <strong>{{ $usuario->name ?: 'Nome da pessoa' }}</strong>
                        <div class="small text-secondary">
                            {{ $usuario->cargo ?: ($perfis[$usuario->role] ?? 'Sem cargo definido') }}@if($usuario->setor) · {{ $usuario->setor }}@endif
                        </div>
                    </div>
                </div>
            </x-admin.card>

            @if($editando)
                <x-admin.card title="Vínculo" icon="fa-briefcase" class="mt-3">
                    <div class="perfil-fatos">
                        <div>
                            <span class="perfil-fato-rotulo">Perfil</span>
                            <x-admin.status :rotulo="$perfis[$usuario->role] ?? ucfirst($usuario->role)" tom="neutral" />
                        </div>
                        <div>
                            <span class="perfil-fato-rotulo">Situação</span>
                            <x-admin.status :rotulo="ucfirst($usuario->status)" :tom="tom_por_status('usuario', $usuario->status)" />
                        </div>
                    </div>
                    @if($usuario->bio)
                        <p class="small mb-0 mt-3">{{ $usuario->bio }}</p>
                    @endif
                </x-admin.card>
            @endif
        </div>
    </div>
</form>

@if($editando && ! $usuario->isSuperAdmin() && ($totalSuperadmins ?? 0) < ($maxSuperadmins ?? 2) && (($totalSuperadmins ?? 0) === 0 || auth()->user()->isSuperAdmin()))
    <div class="card border-warning-subtle shadow-sm mt-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h6 class="fw-bold mb-1 text-warning">
                    <i class="fas fa-crown me-1"></i> Promover para Super Administrador ({{ $totalSuperadmins ?? 0 }} / {{ $maxSuperadmins ?? 2 }})
                </h6>
                <p class="small text-secondary mb-0">
                    @if(($totalSuperadmins ?? 0) === 0)
                        Nenhum Super Administrador foi definido ainda no sistema. Promova esta conta com 1 clique.
                    @else
                        Resta 1 vaga para Super Administrador (máximo de {{ $maxSuperadmins ?? 2 }} contas no sistema).
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('admin.usuarios.promover-superadmin', $usuario) }}"
                onsubmit="return confirm('Confirmar promoção de {{ $usuario->name }} para Super Administrador?');">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm fw-bold">
                    <i class="fas fa-crown me-1"></i> Tornar Super Administrador
                </button>
            </form>
        </div>
    </div>
@endif
@endsection
