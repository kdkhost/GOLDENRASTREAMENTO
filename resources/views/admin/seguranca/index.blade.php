@extends('admin.layouts.app')
@section('title', 'Segurança do acesso')
@section('page_title', 'Segurança do acesso')
@section('page_description', 'A porta do painel: quantas janelas abertas na sua conta, quem bateu na porta e se a sua segunda chave está acesa.')

@section('page_actions')
@if(auth()->user()->isAdmin())
    <a href="{{ route('admin.settings.index').'#chaves-seguranca' }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-sliders me-1" aria-hidden="true"></i> Ajustar os limites da casa
    </a>
@endif
@endsection

@php
    $pessoa = auth()->user();
    $exige = \App\Services\Seguranca\DoisFatores::exige($pessoa);
    $rotuloPolitica = data_get(\App\Support\ChavesDeIntegracao::campo('seguranca_dois_fatores'), 'opcoes.'.$politica, Str::ucfirst($politica));
    $travaEm = 'Trava em '.$limites['tentativas'].' tentativas / '.$limites['janela'].' min';
@endphp

@section('content')
<div class="row g-3">
    <div class="col-12">
        @if($travada_por)
            <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2 mb-3">
                <i class="fas fa-lock text-tone-amber" aria-hidden="true"></i>
                <span class="flex-grow-1">
                    <strong>Esta conta está travada pelo porteiro.</strong> Faltam {{ (int) ceil($travada_por / 60) }} minuto(s) para a porta
                    reabrir sozinha. Enquanto isso, nenhum navegador deste endereço entra com esta senha.
                </span>
            </div>
        @endif

        @if($exige && !$pessoa->doisFatoresAtivo())
            <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2 mb-3">
                <i class="fas fa-shield-halved text-tone-amber" aria-hidden="true"></i>
                <span class="flex-grow-1">
                    <strong>A política da casa exige o aplicativo de autenticação para o seu perfil</strong> e esta conta ainda não
                    tem a segunda chave. Acenda agora: na próxima entrada o painel vai levar você até a ativação de qualquer jeito.
                </span>
                <a href="{{ route('dois-fatores.configurar') }}" class="btn btn-sm btn-golden">Acender a segunda chave</a>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <x-admin.stat label="Janelas abertas" :value="$janelas->count()" icon="fa-window-maximize" tone="blue"
                    :hint="$limites['simultaneas'] > 0 ? 'Limite da casa: '.$limites['simultaneas'] : 'Sem limite de aparelhos'" />
            </div>
            <div class="col-6 col-lg-3">
                <x-admin.stat label="Tentativas na janela" :value="$tentativas_na_janela" icon="fa-keyboard" :tone="$tentativas_na_janela > 0 ? 'amber' : 'green'"
                    :hint="$travaEm" />
            </div>
            <div class="col-6 col-lg-3">
                <x-admin.stat label="Segunda chave" :value="$pessoa->doisFatoresAtivo() ? 'Acesa' : 'Desligada'"
                    icon="fa-shield-halved" :tone="$pessoa->doisFatoresAtivo() ? 'green' : ($exige ? 'red' : 'neutral')"
                    :hint="$codigos_restantes > 0 ? $codigos_restantes.' código(s) de recuperação no valor' : 'Nenhum código guardado'" />
            </div>
            <div class="col-6 col-lg-3">
                <x-admin.stat label="Sessão sem uso" :value="$limites['sessao_minutos'].' min'" icon="fa-hourglass-half" tone="violet"
                    hint="Depois disto, a senha é pedida de novo" />
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <x-admin.card title="Minha segunda chave" icon="fa-shield-halved"
            descricao="Um código de seis números que nasce no seu celular, junto com a senha.">
            @if($pessoa->doisFatoresAtivo())
                <p class="small text-secondary mb-3">
                    Está acesa desde {{ $pessoa->two_factor_confirmed_at?->format('d/m/Y') }}. Na próxima entrada, o painel pede a senha
                    e o código do aplicativo. Sobram <strong>{{ $codigos_restantes }}</strong> código(s) de recuperação desta fornada.
                </p>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <form method="POST" action="{{ route('dois-fatores.codigos.renovar') }}" class="d-inline" data-atualiza=".app-content"
                        data-confirmar="Gerar oito códigos novos? Os oito atuais perdem o valor na hora, inclusive os que você já usou.">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-rotate me-1" aria-hidden="true"></i> Renovar os códigos</button>
                    </form>
                    @if($codigos_restantes > 0)
                        <a href="{{ route('dois-fatores.codigos') }}" class="btn btn-sm btn-outline-secondary">Ver a folha de agora</a>
                    @endif
                </div>

                @if(!$exige)
                    <hr>
                    <form method="POST" action="{{ route('dois-fatores.desligar') }}" class="row g-2 align-items-end" data-atualiza=".app-content"
                        data-confirmar="Desligar a verificação em duas etapas? A conta volta a abrir só com a senha.">
                        @csrf
                        <x-admin.field name="codigo" label="Código do aplicativo (ou um de recuperação)" col="col-12"
                            :required="true" autocomplete="one-time-code" maxlength="32" placeholder="000000"
                            hint="Quem desliga é quem tem o telefone na mão — a aba aberta sozinha não basta." />
                        <div class="col-12">
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-power-off me-1" aria-hidden="true"></i> Desligar a segunda chave</button>
                        </div>
                    </form>
                @else
                    <p class="alert alert-secondary small py-2 mb-0">
                        A política da casa — <em>{{ $rotuloPolitica }}</em> — não deixa desligar por aqui. Um administrador muda em
                        Configurações → Chaves de integração → Segurança do acesso.
                    </p>
                @endif
            @else
                <p class="small text-secondary mb-3">
                    @if($exige)
                        A política da casa (<em>{{ $rotuloPolitica }}</em>) obriga o seu perfil a ter o aplicativo. Sem ela, a senha vazada
                        abre o painel sozinha.
                    @else
                        A política da casa hoje é <em>{{ $rotuloPolitica }}</em>, então a chave é escolha sua. É a defesa que sobra no dia
                        em que a senha vaza — e vazou em alguém da sua lista de distribuição.
                    @endif
                </p>
                <a href="{{ route('dois-fatores.configurar') }}" class="btn btn-golden">
                    <i class="fas fa-qrcode me-1" aria-hidden="true"></i> Acender a segunda chave
                </a>
                <ul class="mt-3 mb-0 ps-3 small text-secondary">
                    <li>Leva um minuto com qualquer aplicativo de autenticação.</li>
                    <li>Você recebe oito códigos de recuperação para o dia em que o celular não estiver na mão.</li>
                </ul>
            @endif
        </x-admin.card>
    </div>

    <div class="col-lg-7">
        <x-admin.table title="Janelas abertas nesta conta" icon="fa-window-maximize"
            descricao="Cada aparelho logado em seu nome. O painel reconhece o aparelho pelo par rede + navegador, e uma janela que não é sua é fechada aqui."
            :headers="['Aparelho', 'Endereço de IP', 'Último sinal', 'end:Ação']"
            :empty="'Nenhuma janela registrada ainda — o índice de aparelhos nasce com o próximo login.'">
            <x-slot:actions>
                @if($janelas->count() > 1)
                    <form method="POST" action="{{ route('admin.seguranca.janelas.outras') }}" class="d-inline" data-atualiza=".app-content"
                        data-confirmar="Encerrar todas as outras janelas? Cada uma volta a pedir senha e código.">
                        @csrf
                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-door-closed me-1" aria-hidden="true"></i> Encerrar as outras</button>
                    </form>
                @endif
            </x-slot:actions>
            @foreach($janelas as $janela)
                <tr>
                    <td class="small">
                        <span class="fw-semibold">{{ $janela->dispositivo() }}</span>
                        @if($janela->id === $janela_atual)
                            <x-admin.status rotulo="esta janela" tom="green" />
                        @endif
                    </td>
                    <td class="small text-secondary">{{ $janela->ip }}</td>
                    <td class="small text-nowrap">{{ $janela->vistasHa() }}</td>
                    <td class="text-end">
                        @if($janela->id !== $janela_atual)
                            <form method="POST" action="{{ route('admin.seguranca.janela.encerrar', $janela->id) }}" class="d-inline" data-atualiza=".app-content"
                                data-confirmar="Encerrar esta janela agora? Quem estiver nela volta para a tela de login.">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary" title="Encerrar esta janela">
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
            Trocar a senha fecha todas as janelas desta conta por construção: o painel guarda a impressão do hash da senha na sessão,
            e uma janela que não reconhece a senha de hoje se entrega na próxima leitura.
        </p>
    </div>

    @if($pessoa->isAdmin())
        <div class="col-12">
            <x-admin.card title="Os limites da casa" icon="fa-sliders"
                descricao="Tudo configurável pelo painel, gravado no banco. Campo vazio em Configurações é o painel se retirando do caminho e o número do arquivo de configuração conduzindo.">
                <dl class="defs mb-0">
                    <dt>Tentativas de senha até travar</dt>
                    <dd>{{ $limites['tentativas'] }} em {{ $limites['janela'] }} minutos — e a conta fica fora por {{ $limites['bloqueio'] }} minutos</dd>
                    <dt>Dois fatores obrigatório para</dt>
                    <dd>{{ $rotuloPolitica }}</dd>
                    <dt>Sessão do painel sem uso</dt>
                    <dd>{{ $limites['sessao_minutos'] }} minutos</dd>
                    <dt>Aparelhos abertos ao mesmo tempo</dt>
                    <dd>{{ $limites['simultaneas'] > 0 ? $limites['simultaneas'] : 'sem limite' }} — os mais antigos caem fora do número</dd>
                </dl>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="{{ route('admin.settings.index').'#chaves-seguranca' }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-sliders me-1" aria-hidden="true"></i> Ajustar na tela de Configurações
                    </a>
                    <a href="{{ route('admin.activities.index', ['acao' => 'login_falhou']) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-list me-1" aria-hidden="true"></i> Ver toda a trilha de auditoria
                    </a>
                </div>
            </x-admin.card>
        </div>

        <div class="col-12">
            <x-admin.table title="Equipe e segunda chave" icon="fa-users"
                :descricao="'Quem tem o aplicativo aceso, quantas janelas está usando e quando entrou por último. A política da casa hoje é: '.$rotuloPolitica.'.'"
                :headers="['Pessoa', 'Perfil', 'Segunda chave', 'center:Janelas', 'Último acesso', 'end:Ação']"
                empty="Ninguém cadastrado no painel.">
                    @foreach($equipe as $linha)
                        <tr>
                            <td class="small">
                                <span class="fw-semibold">{{ $linha['pessoa']->name }}</span>
                                <span class="text-secondary d-block">{{ $linha['pessoa']->email }}</span>
                            </td>
                            <td><x-admin.status :rotulo="$linha['pessoa']->isAdmin() ? 'Administrador' : 'Equipe'" :tom="$linha['pessoa']->isAdmin() ? 'gold' : 'neutral'" /></td>
                            <td>
                                @if($linha['pessoa']->doisFatoresAtivo())
                                    <x-admin.status rotulo="acesa" tom="green" />
                                @else
                                    <x-admin.status rotulo="desligada" :tom="$linha['pessoa']->isAdmin() ? 'amber' : 'neutral'" />
                                @endif
                            </td>
                            <td class="text-center small">{{ $linha['janelas'] }}</td>
                            <td class="small text-secondary text-nowrap">{{ $linha['ultimo'] ? $linha['ultimo']->diffForHumans() : 'sem registro' }}</td>
                            <td class="text-end">
                                @if($linha['pessoa']->doisFatoresAtivo() && $linha['pessoa']->id !== $pessoa->id)
                                    <form method="POST" action="{{ route('admin.seguranca.dois-fatores.desligar', $linha['pessoa']->id) }}" class="d-inline"
                                        data-atualiza=".app-content"
                                        data-confirmar="Apagar a segunda chave de {{ $linha['pessoa']->name }}? É o caminho de quem perdeu o celular: a conta reabre só com a senha até que uma chave nova seja acesa.">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary" title="Apagar a segunda chave desta conta">
                                            <i class="fas fa-user-slash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
            </x-admin.table>
        </div>

        <div class="col-lg-7">
            <x-admin.card title="Destravar uma conta" icon="fa-unlock-keyhole"
                descricao="O caso honesto: dez senhas digitadas errado, o escritório inteiro atrás do mesmo roteador, um teste que bateu no limite.">
                <form method="POST" action="{{ route('admin.seguranca.destravar') }}" class="row g-3 align-items-end" data-atualiza=".app-content">
                    @csrf
                    <x-admin.field name="email" type="email" label="E-mail da conta travada" col="col-12 col-md-7"
                        :required="true" maxlength="190" placeholder="pessoa@empresa.com.br" />
                    <div class="col-12 col-md-5">
                        <button class="btn btn-golden"><i class="fas fa-unlock me-1" aria-hidden="true"></i> Zerar as contagens</button>
                    </div>
                </form>
                <p class="form-text mt-3 mb-0">
                    Zera as contagens <em>da conta</em>, e deixa a trava do endereço de quem está atirando no lugar: destravar a vítima não
                    abre a porta para o atirador. Toda destravação fica registrada na auditoria com quem fez o pedido.
                </p>
            </x-admin.card>

            <x-admin.table class="mt-3" title="Movimentos da última semana" icon="fa-clock-rotate-left"
                :headers="['Quando', 'Quem', 'Ato', 'Detalhe', 'IP']"
                empty="Nenhum acesso, trava ou segunda chave mexida nos últimos sete dias.">
                @foreach($movimentos as $movimento)
                    <tr>
                        <td class="small text-secondary text-nowrap">{{ $movimento->created_at->format('d/m H:i') }}</td>
                        <td class="small fw-semibold">{{ $movimento->user_name }}</td>
                        <td><x-admin.status :rotulo="Str::ucfirst(str_replace('_', ' ', $movimento->action))"
                                :tom="str_contains((string) $movimento->action, 'falhou') ? 'red' : (str_contains((string) $movimento->action, 'destrav') ? 'amber' : 'neutral')" /></td>
                        <td class="small">{{ $movimento->description }}</td>
                        <td class="small text-secondary">{{ $movimento->ip }}</td>
                    </tr>
                @endforeach
            </x-admin.table>
        </div>
    @endif
</div>
@endsection
