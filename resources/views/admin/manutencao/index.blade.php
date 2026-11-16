@extends('admin.layouts.app')
@section('title', 'Modo de Manutenção')
@section('page_title', 'Modo de manutenção do sistema')

@php
    $ipsLista = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $ipsPermitidos) ?: [])));
    $meuIpLiberado = $meuIp && in_array($meuIp, $ipsLista, true);

    $previsaoCarbon = null;
    if (! empty($previsaoFim)) {
        try {
            $previsaoCarbon = \Illuminate\Support\Carbon::parse($previsaoFim);
        } catch (\Throwable) {
            $previsaoCarbon = null;
        }
    }

    $opcoesModo = [
        'total' => 'Bloqueio Total (Site público e Portal do Cliente)',
        'publico_apenas' => 'Apenas Site Público (Portal do Cliente liberado)',
    ];

    $opcoesVelocidade = [
        'suave' => 'Suave (Movimento tranquilo e relaxante)',
        'normal' => 'Padrão (Velocidade balanceada a 60 FPS)',
        'rapida' => 'Dinâmica (Tráfego ágil com alta taxa de telemetria)',
    ];

    $modulos503 = [
        ['rotulo' => 'Fundo Animado (Carros)', 'icone' => 'fa-car-side', 'ativo' => (bool) $exibirAnimacao],
        ['rotulo' => 'Satélite Orbital & Feixes', 'icone' => 'fa-satellite', 'ativo' => (bool) $exibirSatelite],
        ['rotulo' => 'Contador Regressivo', 'icone' => 'fa-stopwatch', 'ativo' => (bool) $exibirCronometro],
        ['rotulo' => 'Botão do WhatsApp', 'icone' => 'fa-comments', 'ativo' => (bool) $exibirWhatsapp],
        ['rotulo' => 'Redes Sociais', 'icone' => 'fa-share-nodes', 'ativo' => (bool) $exibirRedes],
        ['rotulo' => 'Plantão de Emergência', 'icone' => 'fa-headset', 'ativo' => (bool) $exibirPlantao],
    ];
@endphp

@section('page_actions')
    <a href="{{ route('admin.erros.preview', 503) }}" target="_blank" rel="noopener" class="btn btn-sm btn-golden">
        <i class="fas fa-eye" aria-hidden="true"></i> Prévia da Tela 503
    </a>
    <a href="{{ $urlBypass }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-key" aria-hidden="true"></i> Testar Bypass
    </a>
    <a href="{{ route('site.home') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-globe" aria-hidden="true"></i> Ver o site
    </a>
@endsection

@section('content')
{{-- Painel de Comando Imediato --}}
<x-admin.card title="Status Operacional do Sistema" icon="fa-power-off" ajuda="manutencao_status" class="mb-3">
    <x-slot:actions>
        <x-admin.status :rotulo="$ativa ? 'manutenção ativa (503)' : 'sistema online'" :tom="$ativa ? 'red' : 'green'" />
    </x-slot:actions>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-start gap-3">
            <div class="rounded-circle d-grid flex-none {{ $ativa ? 'tone-red' : 'tone-green' }}" style="width:52px;height:52px;place-items:center">
                <i class="fas {{ $ativa ? 'fa-triangle-exclamation' : 'fa-circle-check' }} fa-lg" aria-hidden="true"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1 {{ $ativa ? 'text-tone-red' : 'text-tone-green' }}">
                    {{ $ativa ? 'MODO DE MANUTENÇÃO ATIVADO' : 'SISTEMA OPERANDO NORMALMENTE' }}
                </h4>
                <p class="mb-2 text-secondary small">
                    {{ $ativa ? 'O site está temporariamente fechado ao público com a tela personalizada de manutenção (HTTP 503).' : 'O site público e todas as páginas estão acessíveis para visitantes e clientes.' }}
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge-status tone-gold">
                        <i class="fas fa-shield-halved me-1" aria-hidden="true"></i>
                        {{ $modo === 'total' ? 'Escopo: Bloqueio Total' : 'Escopo: Apenas Site Público' }}
                    </span>
                    <span class="badge-status tone-neutral">
                        <i class="fas fa-clock me-1" aria-hidden="true"></i>
                        {{ $previsaoCarbon ? 'Previsão: '.$previsaoCarbon->format('d/m/Y H:i') : 'Sem previsão definida' }}
                    </span>
                    <span class="badge-status {{ $meuIpLiberado ? 'tone-green' : 'tone-neutral' }}">
                        <i class="fas fa-network-wired me-1" aria-hidden="true"></i>
                        Seu IP ({{ $meuIp }}): {{ $meuIpLiberado ? 'liberado na whitelist' : 'não listado' }}
                    </span>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.manutencao.toggle') }}" class="m-0"
            data-atualiza=".app-content, .app-content-header"
            data-confirmar="{{ $ativa ? 'Deseja realmente desativar o modo de manutenção e liberar o site ao público?' : 'Deseja ativar o modo de manutenção agora? O site público passará a exibir a tela personalizada 503.' }}">
            @csrf
            <button type="submit" class="btn {{ $ativa ? 'btn-success' : 'btn-danger' }} fw-bold px-4 py-2">
                <i class="fas {{ $ativa ? 'fa-play' : 'fa-power-off' }}" aria-hidden="true"></i>
                {{ $ativa ? 'Desativar Manutenção e Abrir Site' : 'Ativar Modo de Manutenção Agora' }}
            </button>
        </form>
    </div>
</x-admin.card>

{{-- Formulário Unificado em Abas --}}
<form id="formManutencaoConfig" method="POST" action="{{ route('admin.manutencao.update') }}" data-atualiza=".app-content, .app-content-header">
    @csrf
    @method('PUT')
    <input type="hidden" name="ativa" value="{{ $ativa ? '1' : '0' }}">

    <div class="abas" data-abas="manutencao">
        <div class="abas-barra">
            <ul class="nav abas-nav" role="tablist" aria-label="Configurações do modo de manutenção">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" type="button" role="tab" id="aba-conteudo-botao"
                        data-bs-toggle="tab" data-bs-target="#aba-conteudo" data-aba="conteudo"
                        aria-controls="aba-conteudo" aria-selected="true">
                        <i class="fas fa-pen-to-square" aria-hidden="true"></i><span>Conteúdo &amp; Cronômetro</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" type="button" role="tab" id="aba-animacao-botao"
                        data-bs-toggle="tab" data-bs-target="#aba-animacao" data-aba="animacao"
                        aria-controls="aba-animacao" aria-selected="false">
                        <i class="fas fa-satellite" aria-hidden="true"></i><span>Telemetria &amp; Cenário 503</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" type="button" role="tab" id="aba-atendimento-botao"
                        data-bs-toggle="tab" data-bs-target="#aba-atendimento" data-aba="atendimento"
                        aria-controls="aba-atendimento" aria-selected="false">
                        <i class="fas fa-headset" aria-hidden="true"></i><span>Plantão &amp; Redes Sociais</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" type="button" role="tab" id="aba-acesso-botao"
                        data-bs-toggle="tab" data-bs-target="#aba-acesso" data-aba="acesso"
                        aria-controls="aba-acesso" aria-selected="false">
                        <i class="fas fa-key" aria-hidden="true"></i><span>Bypass &amp; Liberação por IP</span>
                        <span class="abas-contagem">{{ count($ipsLista) }}</span>
                    </button>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            {{-- Aba 1: Conteúdo & Cronômetro --}}
            <div class="tab-pane fade show active" id="aba-conteudo" data-aba-painel="conteudo" role="tabpanel" aria-labelledby="aba-conteudo-botao" tabindex="0">
                <div class="row g-3">
                    <div class="col-12 col-xl-7">
                        <x-admin.card title="Mensagens da Tela 503" icon="fa-pen-to-square" ajuda="manutencao_conteudo"
                            descricao="Textos principais do comunicado exibido durante a manutenção." class="h-100">
                            <div class="row g-3">
                                <x-admin.field name="titulo" label="Título Principal" :value="$titulo" col="col-12"
                                    :required="true" maxlength="160" ajuda="manutencao_titulo"
                                    placeholder="Sistema em Manutenção Programada" />
                                <x-admin.field name="subtitulo" label="Subtítulo Dourado" :value="$subtitulo" col="col-12"
                                    :opcional="true" maxlength="255" ajuda="manutencao_subtitulo"
                                    placeholder="Estamos realizando atualizações para melhorar sua experiência." />
                                <x-admin.field name="mensagem" type="textarea" :rows="4" label="Mensagem aos Clientes e Visitantes"
                                    :value="$mensagem" col="col-12" :opcional="true" maxlength="2000" ajuda="manutencao_mensagem" />
                            </div>
                        </x-admin.card>
                    </div>

                    <div class="col-12 col-xl-5">
                        <x-admin.card title="Escopo & Cronômetro Regressivo" icon="fa-stopwatch" ajuda="manutencao_cronometro"
                            descricao="Abrangência do bloqueio e relógio de retorno." class="h-100">
                            <div class="row g-3">
                                <x-admin.field name="modo" type="select" label="Escopo do Bloqueio" :value="$modo"
                                    :options="$opcoesModo" col="col-12" :required="true" ajuda="manutencao_modo"
                                    hint="O painel administrativo (/admin) permanece sempre acessível aos administradores." />

                                <x-admin.switch name="exibir_cronometro" label="Exibir Contador Regressivo ao Vivo"
                                    :checked="$exibirCronometro" col="col-12" ajuda="manutencao_exibir_cronometro"
                                    hint="Mostra dias, horas, minutos e segundos até a reabertura dos serviços." />

                                <x-admin.field name="previsao_fim" type="datetime-local" label="Previsão de Término"
                                    :value="$previsaoFim" col="col-12" :opcional="true" ajuda="manutencao_previsao_fim"
                                    hint="Alimenta o cronômetro regressivo da tela de manutenção." />

                                <div class="col-12">
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-previsao-minutos="30">+30 min</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-previsao-minutos="60">+1 hora</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-previsao-minutos="120">+2 horas</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-previsao-minutos="240">+4 horas</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-previsao-limpar>Limpar</button>
                                    </div>
                                </div>
                            </div>
                        </x-admin.card>
                    </div>
                </div>
            </div>

            {{-- Aba 2: Telemetria & Cenário 503 --}}
            <div class="tab-pane fade" id="aba-animacao" data-aba-painel="animacao" role="tabpanel" aria-labelledby="aba-animacao-botao" tabindex="0">
                <div class="row g-3">
                    <div class="col-12 col-xl-7">
                        <x-admin.card title="Cenário Satelital & Fluxo Viário" icon="fa-satellite" ajuda="manutencao_animacao"
                            descricao="Animação em Canvas de alta performance na paleta oficial Golden." class="h-100">
                            <div class="callout mb-3">
                                <span class="small">
                                    <i class="fas fa-satellite-dish text-gold me-1" aria-hidden="true"></i>
                                    <strong>Telemetria em Tempo Real:</strong> veículos trafegam exclusivamente dentro das faixas asfaltadas das vias enquanto feixes orbitais conectam o satélite à frota.
                                </span>
                            </div>

                            <div class="row g-3">
                                <x-admin.switch name="exibir_animacao" label="Ativar Fundo Animado com Carros"
                                    :checked="$exibirAnimacao" col="col-12 col-md-6" ajuda="manutencao_exibir_animacao"
                                    hint="Carros com faróis e lanternas circulando na malha viária." />

                                <x-admin.switch name="exibir_satelite" label="Exibir Satélite em Órbita & Feixes"
                                    :checked="$exibirSatelite" col="col-12 col-md-6" ajuda="manutencao_exibir_satelite"
                                    hint="Ondas de rádio e feixes luminosos ligando aos veículos." />

                                <x-admin.field name="velocidade_animacao" type="select" label="Velocidade da Dinâmica de Tráfego"
                                    :value="$velocidadeAnimacao" :options="$opcoesVelocidade" col="col-12"
                                    ajuda="manutencao_velocidade_animacao" />
                            </div>
                        </x-admin.card>
                    </div>

                    <div class="col-12 col-xl-5">
                        <x-admin.card title="Resumo dos Módulos da Tela 503" icon="fa-sliders" ajuda="manutencao_resumo_visual"
                            descricao="Estado salvo atualmente para cada módulo da tela pública." class="h-100">
                            <ul class="list-group list-group-flush small mb-3">
                                @foreach($modulos503 as $modulo)
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span>
                                            <i class="fas {{ $modulo['icone'] }} text-gold me-2" aria-hidden="true"></i>
                                            {{ $modulo['rotulo'] }}
                                        </span>
                                        <x-admin.status :rotulo="$modulo['ativo'] ? 'Ativo' : 'Desativado'" :tom="tom_por_flag($modulo['ativo'])" />
                                    </li>
                                @endforeach
                            </ul>

                            <a href="{{ route('admin.erros.preview', 503) }}" target="_blank" rel="noopener" class="btn btn-golden w-100">
                                <i class="fas fa-play" aria-hidden="true"></i> Abrir Demonstração da Tela 503
                            </a>
                        </x-admin.card>
                    </div>
                </div>
            </div>

            {{-- Aba 3: Plantão & Redes Sociais --}}
            <div class="tab-pane fade" id="aba-atendimento" data-aba-painel="atendimento" role="tabpanel" aria-labelledby="aba-atendimento-botao" tabindex="0">
                <div class="row g-3">
                    <div class="col-12 col-xl-7">
                        <x-admin.card title="Atendimento & WhatsApp de Emergência" icon="fa-headset" ajuda="manutencao_atendimento"
                            descricao="Canais prioritários exibidos ao cliente durante o fechamento temporário." class="h-100">
                            <div class="row g-3">
                                <x-admin.switch name="exibir_whatsapp" label="Exibir Botão de WhatsApp em Destaque"
                                    :checked="$exibirWhatsapp" col="col-12 col-md-6" ajuda="manutencao_exibir_whatsapp"
                                    hint="Abre conversa direta de suporte durante a manutenção." />

                                <x-admin.switch name="exibir_plantao" label="Exibir Canais de Plantão (Tel &amp; E-mail)"
                                    :checked="$exibirPlantao" col="col-12 col-md-6" ajuda="manutencao_exibir_plantao"
                                    hint="Mostra telefone 24h e endereço de e-mail técnico." />

                                <x-admin.field name="whatsapp_numero" label="Número de WhatsApp" :value="$whatsappNumero"
                                    col="col-12 col-md-6" :opcional="true" maxlength="32" ajuda="manutencao_whatsapp_numero"
                                    placeholder="5519997101483"
                                    hint="Somente dígitos com DDI + DDD. Em branco, usa o WhatsApp padrão do site." />

                                <x-admin.field name="whatsapp_mensagem" label="Mensagem Pré-formatada do WhatsApp"
                                    :value="$whatsappMensagem" col="col-12 col-md-6" :opcional="true" maxlength="255"
                                    ajuda="manutencao_whatsapp_mensagem"
                                    hint="Texto que aparece pré-preenchido ao abrir o WhatsApp." />

                                <x-admin.field name="telefone" label="Telefone 24h / Plantão" :value="$telefone"
                                    col="col-12 col-md-6" :opcional="true" maxlength="60" ajuda="manutencao_telefone"
                                    placeholder="(19) 99710-1483" />

                                <x-admin.field name="email" type="email" label="E-mail de Suporte Técnico" :value="$email"
                                    col="col-12 col-md-6" :opcional="true" maxlength="120" ajuda="manutencao_email"
                                    placeholder="suporte@goldenrastreamento.com.br" />
                            </div>
                        </x-admin.card>
                    </div>

                    <div class="col-12 col-xl-5">
                        <x-admin.card title="Redes Sociais no Rodapé" icon="fa-share-nodes" ajuda="manutencao_redes"
                            descricao="Perfis oficiais da Golden exibidos na base da tela 503." class="h-100">
                            <div class="row g-3">
                                <x-admin.switch name="exibir_redes" label="Exibir Links das Redes Sociais no Rodapé"
                                    :checked="$exibirRedes" col="col-12" ajuda="manutencao_exibir_redes"
                                    hint="Mantém os canais oficiais visíveis para comunicados." />

                                <x-admin.field name="instagram" type="url" label="Instagram" :value="$instagram"
                                    col="col-12" :opcional="true" maxlength="255" ajuda="manutencao_instagram"
                                    placeholder="https://instagram.com/goldenrastreamento" />

                                <x-admin.field name="facebook" type="url" label="Facebook" :value="$facebook"
                                    col="col-12" :opcional="true" maxlength="255" ajuda="manutencao_facebook"
                                    placeholder="https://facebook.com/goldenrastreamento" />

                                <x-admin.field name="youtube" type="url" label="YouTube" :value="$youtube"
                                    col="col-12" :opcional="true" maxlength="255" ajuda="manutencao_youtube"
                                    placeholder="https://youtube.com/@goldenrastreamento" />

                                <x-admin.field name="linkedin" type="url" label="LinkedIn" :value="$linkedin"
                                    col="col-12" :opcional="true" maxlength="255" ajuda="manutencao_linkedin"
                                    placeholder="https://linkedin.com/company/goldenrastreamento" />
                            </div>
                        </x-admin.card>
                    </div>
                </div>
            </div>

            {{-- Aba 4: Bypass & Liberação por IP --}}
            <div class="tab-pane fade" id="aba-acesso" data-aba-painel="acesso" role="tabpanel" aria-labelledby="aba-acesso-botao" tabindex="0">
                <div class="row g-3">
                    <div class="col-12 col-xl-6">
                        <x-admin.card title="Acesso Liberado por IP (Whitelist)" icon="fa-shield-halved" ajuda="manutencao_ips"
                            descricao="Estes endereços IP navegam no site normalmente durante a manutenção." class="h-100">
                            <div class="row g-3">
                                <x-admin.field name="ips_permitidos" type="textarea" :rows="5" class="font-monospace"
                                    label="Endereços IP Liberados" :value="$ipsPermitidos" col="col-12" :opcional="true"
                                    ajuda="manutencao_ips_permitidos"
                                    placeholder="187.54.120.30&#10;201.89.44.12"
                                    hint="Digite um endereço IP por linha (ou separados por vírgula)." />

                                <div class="col-12">
                                    <div class="callout">
                                        <span class="small">
                                            Seu IP atual detectado: <code>{{ $meuIp }}</code>
                                        </span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-incluir-ip="{{ $meuIp }}">
                                            <i class="fas fa-plus" aria-hidden="true"></i> Incluir meu IP atual
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </x-admin.card>
                    </div>

                    <div class="col-12 col-xl-6">
                        <x-admin.card title="Acesso Secreto por Bypass &amp; SEO" icon="fa-key" ajuda="manutencao_bypass"
                            descricao="Compartilhe com a diretoria ou equipe técnica para visualizar o site fechado." class="h-100">
                            <div class="row g-3">
                                <x-admin.field name="token_bypass" class="font-monospace" label="Chave Secreta Atual"
                                    :value="$tokenBypass" col="col-12" :opcional="true" maxlength="64"
                                    ajuda="manutencao_token_bypass"
                                    hint="Ao abrir a URL com esta chave, o navegador grava um cookie de acesso por 7 dias." />

                                <div class="col-12">
                                    <x-admin.link-copiar :valor="$urlBypass" label="Link Completo com Chave Secreta"
                                        hint="(válido por 7 dias no navegador)" id="input-bypass-url" />
                                </div>

                                <div class="col-12 d-flex flex-wrap gap-2">
                                    <button type="submit" form="formGerarTokenBypass" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-arrows-rotate" aria-hidden="true"></i> Gerar Nova Chave Secreta
                                    </button>
                                    <a href="{{ $urlBypass }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-up-right-from-square" aria-hidden="true"></i> Abrir Link de Bypass
                                    </a>
                                </div>

                                <div class="col-12">
                                    <div class="callout">
                                        <span class="small text-secondary">
                                            <strong class="text-body">Segurança &amp; SEO:</strong> a tela 503 envia cabeçalho <code>HTTP 503</code> com <code>Retry-After: 300</code> para buscadores (Google/Bing), evitando perda de indexação durante janelas técnicas.
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </x-admin.card>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="abas-salvar aderente">
        <p class="abas-salvar-note">
            Salva todas as quatro abas preservando o estado operacional atual (o botão no topo é quem liga ou desliga o bloqueio 503 ao público).
        </p>
        <a href="{{ route('admin.erros.preview', 503) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
            <i class="fas fa-eye" aria-hidden="true"></i> Ver Prévia
        </a>
        <button type="submit" class="btn btn-golden">
            <i class="fas fa-save" aria-hidden="true"></i> Salvar Configurações de Manutenção
        </button>
    </div>
</form>

<form id="formGerarTokenBypass" method="POST" action="{{ route('admin.manutencao.gerar-token') }}" class="d-none"
    data-atualiza=".app-content, .app-content-header"
    data-confirmar="Deseja gerar uma nova chave secreta? O link anterior deixará de funcionar imediatamente.">
    @csrf
</form>
@endsection

@section('scripts')
<script>
document.addEventListener('click', function (evento) {
    var alvo = evento.target instanceof Element ? evento.target : null;
    if (!alvo) {
        return;
    }

    var btnPrevisao = alvo.closest('[data-previsao-minutos]');
    if (btnPrevisao) {
        var minutos = parseInt(btnPrevisao.getAttribute('data-previsao-minutos'), 10);
        var campoData = document.getElementById('f-previsao-fim');
        if (campoData && Number.isFinite(minutos)) {
            var agora = new Date(Date.now() + minutos * 60000);
            var pad = function (n) { return String(n).padStart(2, '0'); };
            campoData.value = agora.getFullYear() + '-' + pad(agora.getMonth() + 1) + '-' + pad(agora.getDate())
                + 'T' + pad(agora.getHours()) + ':' + pad(agora.getMinutes());
            campoData.dispatchEvent(new Event('change', { bubbles: true }));
        }
        return;
    }

    if (alvo.closest('[data-previsao-limpar]')) {
        var campoLimpar = document.getElementById('f-previsao-fim');
        if (campoLimpar) {
            campoLimpar.value = '';
            campoLimpar.dispatchEvent(new Event('change', { bubbles: true }));
        }
        return;
    }

    var btnIp = alvo.closest('[data-incluir-ip]');
    if (btnIp) {
        var ip = (btnIp.getAttribute('data-incluir-ip') || '').trim();
        var textarea = document.getElementById('f-ips-permitidos');
        if (!ip || !textarea) {
            return;
        }
        var linhas = textarea.value.split(/[\r\n,]+/).map(function (item) {
            return item.trim();
        }).filter(Boolean);

        if (linhas.indexOf(ip) !== -1) {
            if (window.GoldenAvisos) {
                window.GoldenAvisos.info('Este endereço IP já está na lista.');
            }
            return;
        }

        linhas.push(ip);
        textarea.value = linhas.join('\n');
        textarea.focus();
        if (window.GoldenAvisos) {
            window.GoldenAvisos.sucesso('IP adicionado. Clique em Salvar Configurações de Manutenção para confirmar.');
        }
    }
});
</script>
@endsection
