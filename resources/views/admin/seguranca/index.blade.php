@extends('admin.layouts.app')
@section('title', 'Segurança do acesso')
@section('page_title', 'Segurança do acesso')
@section('page_description', 'Gerencie suas sessões ativas, autenticação em duas etapas (2FA), políticas de bloqueio e auditoria de login.')

@section('page_actions')
@if(auth()->user()->isAdmin())
    <a href="{{ route('admin.activities.index', ['acao' => 'login_falhou']) }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-list-check me-1" aria-hidden="true"></i> Auditoria de acessos
    </a>
    <a href="{{ route('admin.settings.index').'#chaves-seguranca' }}" class="btn btn-sm btn-golden">
        <i class="fas fa-sliders me-1" aria-hidden="true"></i> Ajustar limites
    </a>
@endif
@endsection

@php
    $pessoa = auth()->user();
    $exige = \App\Services\Seguranca\DoisFatores::exige($pessoa);
    $rotuloPolitica = data_get(\App\Support\ChavesDeIntegracao::campo('seguranca_dois_fatores'), 'opcoes.'.$politica, Str::ucfirst($politica));
    $travaEm = $limites['tentativas'].' tentativas / '.$limites['janela'].' min';
    $doisFatoresAtivo = $pessoa->doisFatoresAtivo();
@endphp

@section('content')
<div class="row g-3">
    {{-- Alertas prioritários --}}
    @if($travada_por || ($exige && !$doisFatoresAtivo))
        <div class="col-12 d-flex flex-column gap-2">
            @if($travada_por)
                <div class="alert alert-warning d-flex flex-wrap align-items-center gap-3 mb-0 border-0 shadow-sm">
                    <div class="rounded-circle bg-warning-subtle text-warning d-grid place-items-center" style="width:38px;height:38px;flex:none">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                    </div>
                    <div class="flex-grow-1 small">
                        <strong class="d-block">Conta temporariamente bloqueada por excesso de tentativas</strong>
                        Restam {{ (int) ceil($travada_por / 60) }} minuto(s) para o desbloqueio automático neste endereço.
                    </div>
                </div>
            @endif

            @if($exige && !$doisFatoresAtivo)
                <div class="alert alert-warning d-flex flex-wrap align-items-center gap-3 mb-0 border-0 shadow-sm">
                    <div class="rounded-circle bg-warning-subtle text-warning d-grid place-items-center" style="width:38px;height:38px;flex:none">
                        <i class="fas fa-shield-halved" aria-hidden="true"></i>
                    </div>
                    <div class="flex-grow-1 small">
                        <strong class="d-block">Autenticação em duas etapas (2FA) obrigatória para o seu perfil</strong>
                        Ative seu aplicativo autenticador agora para evitar bloqueio no próximo login.
                    </div>
                    <a href="{{ route('dois-fatores.configurar') }}" class="btn btn-sm btn-golden">Ativar 2FA agora</a>
                </div>
            @endif
        </div>
    @endif

    {{-- Faixa de KPIs --}}
    <div class="col-12">
        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <x-admin.stat label="Janelas abertas" :value="$janelas->count()" icon="fa-window-maximize" tone="blue"
                    :hint="$limites['simultaneas'] > 0 ? 'Máximo permitido: '.$limites['simultaneas'] : 'Sem limite simultâneo'" />
            </div>
            <div class="col-6 col-lg-3">
                <x-admin.stat label="Tentativas recentes" :value="$tentativas_na_janela" icon="fa-keyboard" :tone="$tentativas_na_janela > 0 ? 'amber' : 'green'"
                    :hint="'Bloqueia em '.$travaEm" />
            </div>
            <div class="col-6 col-lg-3">
                <x-admin.stat label="Segunda chave (2FA)" :value="$doisFatoresAtivo ? 'Acesa' : 'Desligada'"
                    icon="fa-shield-halved" :tone="$doisFatoresAtivo ? 'green' : ($exige ? 'red' : 'neutral')"
                    :hint="$codigos_restantes > 0 ? $codigos_restantes.' código(s) reserva' : 'Sem códigos reserva'" />
            </div>
            <div class="col-6 col-lg-3">
                <x-admin.stat label="Expiração por inatividade" :value="$limites['sessao_minutos'].' min'" icon="fa-hourglass-half" tone="violet"
                    hint="Tempo máximo sem atividade" />
            </div>
        </div>
    </div>

    {{-- Bloco Pessoal: 2FA + Sessões Ativas --}}
    <div class="col-lg-5 d-flex flex-column">
        <x-admin.card title="Autenticação em 2 Etapas (2FA)" icon="fa-shield-halved" class="h-100"
            descricao="Proteção adicional via código TOTP de 6 dígitos gerado no celular.">
            <div class="d-flex align-items-center justify-content-between gap-2 p-3 rounded-3 mb-3 {{ $doisFatoresAtivo ? 'bg-success-subtle border border-success-subtle' : 'bg-body-tertiary border' }}">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas {{ $doisFatoresAtivo ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-warning' }} fs-5" aria-hidden="true"></i>
                    <div>
                        <div class="fw-semibold small">{{ $doisFatoresAtivo ? 'Verificação ativa' : 'Verificação desativada' }}</div>
                        <div class="text-secondary" style="font-size:.76rem">
                            {{ $doisFatoresAtivo ? 'Desde '.$pessoa->two_factor_confirmed_at?->format('d/m/Y') : 'Proteja seu login contra vazamento de senha' }}
                        </div>
                    </div>
                </div>
                <x-admin.status :rotulo="$doisFatoresAtivo ? 'acesa' : 'desligada'" :tom="$doisFatoresAtivo ? 'green' : ($exige ? 'amber' : 'neutral')" />
            </div>

            @if($doisFatoresAtivo)
                <div class="d-flex align-items-center justify-content-between small text-secondary mb-3 px-1">
                    <span>Códigos de recuperação disponíveis:</span>
                    <span class="badge bg-body-secondary text-body fw-semibold">{{ $codigos_restantes }} restante(s)</span>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <form method="POST" action="{{ route('dois-fatores.codigos.renovar') }}" class="d-inline" data-atualiza=".app-content"
                        data-confirmar="Gerar oito códigos novos? Os códigos atuais perderão a validade imediatamente.">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-rotate me-1" aria-hidden="true"></i> Renovar códigos</button>
                    </form>
                    @if($codigos_restantes > 0)
                        <a href="{{ route('dois-fatores.codigos') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-key me-1" aria-hidden="true"></i> Ver códigos atuais
                        </a>
                    @endif
                </div>

                @if(!$exige)
                    <hr class="my-3">
                    <form method="POST" action="{{ route('dois-fatores.desligar') }}" class="row g-2 align-items-end" data-atualiza=".app-content"
                        data-confirmar="Desligar a verificação em duas etapas? A conta voltará a exigir apenas a senha.">
                        @csrf
                        <x-admin.field name="codigo" label="Confirmar código para desativar" col="col-12"
                            :required="true" autocomplete="one-time-code" maxlength="32" placeholder="000000 ou código de recuperação" />
                        <div class="col-12">
                            <button class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-power-off me-1" aria-hidden="true"></i> Desligar segunda chave</button>
                        </div>
                    </form>
                @else
                    <div class="alert alert-secondary small py-2 mb-0 border-0">
                        <i class="fas fa-lock me-1" aria-hidden="true"></i>
                        A política atual (<strong>{{ $rotuloPolitica }}</strong>) exige 2FA obrigatório para o seu perfil.
                    </div>
                @endif
            @else
                <ul class="small text-secondary ps-3 mb-3 d-flex flex-column gap-1">
                    <li>Compatível com Google Authenticator, Authy, 1Password e Bitwarden.</li>
                    <li>Inclui 8 códigos de recuperação de uso único para emergências.</li>
                    <li>Política atual da empresa: <strong>{{ $rotuloPolitica }}</strong>.</li>
                </ul>
                <a href="{{ route('dois-fatores.configurar') }}" class="btn btn-golden w-100">
                    <i class="fas fa-qrcode me-1" aria-hidden="true"></i> Ativar autenticação em 2 etapas
                </a>
            @endif
        </x-admin.card>
    </div>

    <div class="col-lg-7 d-flex flex-column">
        <x-admin.table title="Janelas abertas nesta conta" icon="fa-laptop" class="h-100"
            descricao="Sessões conectadas com o seu login. Encerre dispositivos desconhecidos imediatamente."
            :headers="['Dispositivo', 'Endereço IP', 'Última atividade', 'end:Ação']"
            empty="Nenhuma sessão registrada no momento.">
            <x-slot:actions>
                @if($janelas->count() > 1)
                    <form method="POST" action="{{ route('admin.seguranca.janelas.outras') }}" class="d-inline" data-atualiza=".app-content"
                        data-confirmar="Encerrar todas as outras sessões? Elas voltarão para a tela de login.">
                        @csrf
                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-door-closed me-1" aria-hidden="true"></i> Encerrar outras</button>
                    </form>
                @endif
            </x-slot:actions>
            @foreach($janelas as $janela)
                <tr>
                    <td class="small">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-semibold">{{ $janela->dispositivo() }}</span>
                            @if($janela->id === $janela_atual)
                                <x-admin.status rotulo="sessão atual" tom="green" />
                            @endif
                        </div>
                    </td>
                    <td class="small text-secondary font-monospace">{{ $janela->ip }}</td>
                    <td class="small text-nowrap text-secondary">{{ $janela->vistasHa() }}</td>
                    <td class="text-end">
                        @if($janela->id !== $janela_atual)
                            <form method="POST" action="{{ route('admin.seguranca.janela.encerrar', $janela->id) }}" class="d-inline" data-atualiza=".app-content"
                                data-confirmar="Encerrar esta sessão agora?">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger" title="Encerrar esta janela">
                                    <i class="fas fa-door-open" aria-hidden="true"></i>
                                </button>
                            </form>
                        @else
                            <span class="small text-secondary">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-admin.table>
        <p class="form-hint mt-2 mb-0">
            <i class="fas fa-circle-info me-1" aria-hidden="true"></i> Alterar a sua senha invalida automaticamente todas as outras sessões abertas.
        </p>
    </div>

    {{-- Bloco Administrativo: Políticas, Desbloqueio, Equipe e Auditoria --}}
    @if($pessoa->isAdmin())
        <div class="col-lg-5 d-flex flex-column gap-3">
            <x-admin.card title="Políticas de Proteção" icon="fa-sliders"
                descricao="Regras ativas de bloqueio, 2FA e expiração de sessão.">
                <div class="row g-2 small">
                    <div class="col-6">
                        <div class="p-2 rounded-3 bg-body-tertiary border h-100">
                            <div class="text-secondary" style="font-size:.74rem">Limite de tentativas</div>
                            <div class="fw-bold">{{ $limites['tentativas'] }} em {{ $limites['janela'] }} min</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded-3 bg-body-tertiary border h-100">
                            <div class="text-secondary" style="font-size:.74rem">Bloqueio automático</div>
                            <div class="fw-bold">{{ $limites['bloqueio'] }} minutos</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded-3 bg-body-tertiary border h-100">
                            <div class="text-secondary" style="font-size:.74rem">2FA obrigatório</div>
                            <div class="fw-bold">{{ $rotuloPolitica }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded-3 bg-body-tertiary border h-100">
                            <div class="text-secondary" style="font-size:.74rem">Sessões simultâneas</div>
                            <div class="fw-bold">{{ $limites['simultaneas'] > 0 ? 'Até '.$limites['simultaneas'] : 'Ilimitadas' }}</div>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="{{ route('admin.settings.index').'#chaves-seguranca' }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-sliders me-1" aria-hidden="true"></i> Configurar limites
                    </a>
                    <a href="{{ route('admin.activities.index', ['acao' => 'login_falhou']) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-list me-1" aria-hidden="true"></i> Ver auditoria completa
                    </a>
                </div>
            </x-admin.card>

            <x-admin.card title="Desbloquear Conta" icon="fa-unlock-keyhole"
                descricao="Zera o contador de tentativas falhas de um usuário bloqueado sem remover travas de IP suspeito.">
                <form method="POST" action="{{ route('admin.seguranca.destravar') }}" class="row g-2 align-items-end" data-atualiza=".app-content">
                    @csrf
                    <x-admin.field name="email" type="email" label="E-mail da conta bloqueada" col="col-12 col-sm-7"
                        :required="true" maxlength="190" placeholder="usuario@goldenrastreamento.com.br" />
                    <div class="col-12 col-sm-5">
                        <button class="btn btn-golden w-100"><i class="fas fa-unlock me-1" aria-hidden="true"></i> Desbloquear</button>
                    </div>
                </form>
            </x-admin.card>
        </div>

        <div class="col-lg-7">
            <x-admin.table title="Equipe e Status de 2FA" icon="fa-users"
                :descricao="'Visão geral da equipe no painel (política: '.$rotuloPolitica.').'"
                :headers="['Usuário', 'Perfil', '2FA', 'center:Sessões', 'Último acesso', 'end:Ação']"
                empty="Nenhum usuário cadastrado no painel.">
                @foreach($equipe as $linha)
                    <tr>
                        <td class="small">
                            <span class="fw-semibold">{{ $linha['pessoa']->name }}</span>
                            <span class="text-secondary d-block">{{ $linha['pessoa']->email }}</span>
                        </td>
                        <td>
                            <x-admin.status :rotulo="$linha['pessoa']->isAdmin() ? 'Administrador' : ucfirst((string) ($linha['pessoa']->role ?: 'Equipe'))" :tom="$linha['pessoa']->isAdmin() ? 'gold' : 'neutral'" />
                        </td>
                        <td>
                            @if($linha['pessoa']->doisFatoresAtivo())
                                <x-admin.status rotulo="acesa" tom="green" />
                            @else
                                <x-admin.status rotulo="desligada" :tom="$linha['pessoa']->isAdmin() ? 'amber' : 'neutral'" />
                            @endif
                        </td>
                        <td class="text-center small fw-semibold">{{ $linha['janelas'] }}</td>
                        <td class="small text-secondary text-nowrap">{{ $linha['ultimo'] ? $linha['ultimo']->diffForHumans() : 'sem registro' }}</td>
                        <td class="text-end">
                            @if($linha['pessoa']->doisFatoresAtivo() && $linha['pessoa']->id !== $pessoa->id)
                                <form method="POST" action="{{ route('admin.seguranca.dois-fatores.desligar', $linha['pessoa']->id) }}" class="d-inline"
                                    data-atualiza=".app-content"
                                    data-confirmar="Resetar o 2FA de {{ $linha['pessoa']->name }}? A conta voltará a pedir apenas senha até configurar novamente.">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger" title="Resetar 2FA deste usuário">
                                        <i class="fas fa-user-slash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-admin.table>
        </div>

        <div class="col-12">
            <x-admin.table title="Movimentos de Segurança (Últimos 7 dias)" icon="fa-clock-rotate-left"
                :headers="['Data/Hora', 'Usuário', 'Evento', 'Detalhe', 'IP']"
                empty="Nenhum evento de login, bloqueio ou 2FA registrado nos últimos 7 dias.">
                @foreach($movimentos as $movimento)
                    <tr>
                        <td class="small text-secondary text-nowrap">{{ $movimento->created_at->format('d/m/Y H:i') }}</td>
                        <td class="small fw-semibold">{{ $movimento->user_name ?: 'Sistema' }}</td>
                        <td>
                            <x-admin.status :rotulo="Str::ucfirst(str_replace('_', ' ', $movimento->action))"
                                :tom="str_contains((string) $movimento->action, 'falhou') ? 'red' : (str_contains((string) $movimento->action, 'destrav') ? 'amber' : 'neutral')" />
                        </td>
                        <td class="small">{{ $movimento->description }}</td>
                        <td class="small text-secondary font-monospace">{{ $movimento->ip }}</td>
                    </tr>
                @endforeach
            </x-admin.table>
        </div>
    @endif
</div>
@endsection
