@extends('admin.layouts.app')
@section('title', 'Configurações')
@section('page_title', 'Configurações do sistema')

@php
    $lojaAtiva = ($settings['loja_ativa'] ?? '1') === '1';
    $livre = ($settings['loja_livre'] ?? '0') === '1';
    $retirada = ($settings['loja_retirada'] ?? '0') === '1';
    $correiosAtivo = ($settings['correios_ativo'] ?? '1') === '1';
    $correiosContrato = ($settings['correios_possui_contrato'] ?? '0') === '1';
    $correiosColeta = ($settings['correios_coleta_empresa'] ?? '0') === '1';
    $correiosSedex = ($settings['correios_sedex_ativo'] ?? '1') === '1';
    $correiosPac = ($settings['correios_pac_ativo'] ?? '1') === '1';
    $melhorEnvioAtivo = ($settings['melhor_envio_ativo'] ?? '0') === '1';
    $melhorEnvioSandbox = ($settings['melhor_envio_sandbox'] ?? '0') === '1';
    $melhorEnvioTemToken = trim((string) ($settings['melhor_envio_token'] ?? '')) !== '';
    $mercadoLivreAtivo = ($settings['mercado_livre_envio_ativo'] ?? '0') === '1';
    $boxAtivo = ($settings['whatsapp_box_ativo'] ?? '1') === '1';
    $paraExibir = fn ($cents) => ((int) $cents) > 0 ? number_format(((int) $cents) / 100, 2, '.', '') : '';
    $numeroPadrao = $settings['contato_whatsapp'] ?? config('golden.whatsapp');
    $textosAcesso = config('golden.acessos.textos');
    $iconesAcesso = config('golden.acessos.icones');
    $gruposDeChaves = \App\Support\ChavesDeIntegracao::gruposDeChaves();
    // A sonda volta por flash como um pacote (estado, título, texto): o estado veste o selo pela
    // tabela de config('golden.tons_status'), e sem sonda feita o pacote é nulo — a tela fica em
    // branco, não em vermelho.
    $sonda = session('sonda');
    $sondaEmail = session('sonda_email');
@endphp

@section('page_actions')
<form method="POST" action="{{ route('admin.settings.limpar-cache') }}" class="d-inline" data-sem-ajax data-confirmar="Limpar todo o cache do sistema (aplicação, views, rotas e configurações)?">
    @csrf
    <button type="submit" class="btn btn-sm btn-outline-warning" title="Limpa cache da aplicação, views, rotas e configurações">
        <i class="fas fa-broom me-1"></i> Limpar cache
    </button>
</form>
@if($lojaAtiva)
    <a href="{{ route('site.loja.index') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-up-right-from-square me-1"></i> Ver a loja no site</a>
@endif
<a href="{{ route('site.home') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-globe me-1"></i> Ver o site</a>
@endsection

@section('content')
{{-- Uma aba por tipo de configuração. O que é do mesmo dono fica junto: identidade e endereço
     num formulário, acessos noutro, medição noutro — cada aba salva só os seus campos (o
     `_aba` diz ao controle de onde veio o POST). As abas internas de Chaves abrem um grupo
     por vez, e o mesmo componente (admin-abas.js) devolve a pessoa à aba onde estava. --}}
<div class="abas" data-abas="configuracoes">
    <div class="abas-barra">
        <ul class="nav abas-nav" role="tablist" aria-label="Tipos de configuração">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" type="button" role="tab" id="aba-contato-botao"
                    data-bs-toggle="tab" data-bs-target="#aba-contato" data-aba="contato"
                    aria-controls="aba-contato" aria-selected="true">
                    <i class="fas fa-id-card" aria-hidden="true"></i><span>Contato e endereço</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" role="tab" id="aba-acessos-botao"
                    data-bs-toggle="tab" data-bs-target="#aba-acessos" data-aba="acessos"
                    aria-controls="aba-acessos" aria-selected="false">
                    <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i><span>Acessos do site</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" role="tab" id="aba-loja-botao"
                    data-bs-toggle="tab" data-bs-target="#aba-loja" data-aba="loja"
                    aria-controls="aba-loja" aria-selected="false">
                    <i class="fas fa-store" aria-hidden="true"></i><span>Loja virtual</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" role="tab" id="aba-whatsapp-botao"
                    data-bs-toggle="tab" data-bs-target="#aba-whatsapp" data-aba="whatsapp"
                    aria-controls="aba-whatsapp" aria-selected="false">
                    <i class="fab fa-whatsapp" aria-hidden="true"></i><span>WhatsApp</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" role="tab" id="aba-medicao-botao"
                    data-bs-toggle="tab" data-bs-target="#aba-medicao" data-aba="medicao"
                    aria-controls="aba-medicao" aria-selected="false">
                    <i class="fas fa-chart-area" aria-hidden="true"></i><span>Medição</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" role="tab" id="aba-financeiro-botao"
                    data-bs-toggle="tab" data-bs-target="#aba-financeiro" data-aba="financeiro"
                    aria-controls="aba-financeiro" aria-selected="false">
                    <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i><span>Financeiro (WHMCS)</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" role="tab" id="aba-chaves-botao"
                    data-bs-toggle="tab" data-bs-target="#aba-chaves" data-aba="chaves"
                    aria-controls="aba-chaves" aria-selected="false">
                    <i class="fas fa-key" aria-hidden="true"></i><span>Chaves de integração</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" role="tab" id="aba-preloader-botao"
                    data-bs-toggle="tab" data-bs-target="#aba-preloader" data-aba="preloader"
                    aria-controls="aba-preloader" aria-selected="false">
                    <i class="fas fa-spinner" aria-hidden="true"></i><span>Preloader</span>
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content">
        {{-- 1. Identidade: quem a Golden é e onde fica. --}}
        <div class="tab-pane fade show active" id="aba-contato" data-aba-painel="contato" role="tabpanel" aria-labelledby="aba-contato-botao" tabindex="0">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-atualiza=".app-content">
                @csrf
                <input type="hidden" name="_aba" value="contato">
                <div class="row g-3">
                    <div class="col-xl-7">
                        <x-admin.card title="Contato oficial" icon="fa-id-card" ajuda="contato_oficial" descricao="É o que aparece no rodapé, no formulário do site e nos e-mails.">
                            <div class="row g-3">
                                <x-admin.field name="site_nome" label="Nome do site" :value="$settings['site_nome'] ?? 'Golden Rastreamento'" col="col-12 col-md-6" :required="true" maxlength="120" />
                                <x-admin.field name="contato_whatsapp" label="WhatsApp oficial" :value="$settings['contato_whatsapp'] ?? ''" col="col-12 col-md-6" :required="true" maxlength="32" placeholder="5519997101483" hint="DDI + DDD + número, só dígitos." />
                                <x-admin.field name="contato_telefone" label="Telefones extras" :value="$settings['contato_telefone'] ?? ''" maxlength="60" :opcional="true" placeholder="(19) 99710-1483 · (19) 97139-5129" />
                                <x-admin.field name="contato_email" type="email" label="E-mail comercial" :value="$settings['contato_email'] ?? ''" maxlength="160" :opcional="true" autocomplete="off" />
                            </div>
                        </x-admin.card>
                    </div>
                    <div class="col-xl-5">
                        <x-admin.card title="Endereço da empresa" icon="fa-location-dot" ajuda="endereco_empresa">
                            <div class="row g-3">
                                <x-admin.field name="empresa_cnpj" label="CNPJ da empresa" :value="$settings['empresa_cnpj'] ?? ''" maxlength="24" :opcional="true" placeholder="00.000.000/0001-00" hint="Exibido nas faturas PDF A4 e no rodapé dos e-mails." />
                                <x-admin.field name="empresa_endereco" label="Endereço" :value="$settings['empresa_endereco'] ?? ''" maxlength="255" :opcional="true" placeholder="R. Pref. Olinto Réis Campos, 104 - Centro" />
                                <x-admin.field name="empresa_cidade" label="Cidade / UF" :value="$settings['empresa_cidade'] ?? ''" col="col-8" maxlength="120" :opcional="true" placeholder="Três Pontas - MG" />
                                <x-admin.field name="empresa_cep" label="CEP" :value="$settings['empresa_cep'] ?? ''" col="col-4" maxlength="12" :opcional="true" placeholder="37185-016" />
                                <x-admin.field name="fuso_horario" type="select" label="Fuso horário"
                                    :value="$settings['fuso_horario'] ?? ''" :options="$fuso['opcoes']"
                                    empty="Sem escolha — vale o do servidor ({{ $fuso['vigente'] }})"
                                    hint="É o relógio da hora em cada fala da Ana e de toda data do painel, do portal e dos e-mails. O servidor da hospedagem acorda em UTC: sem escolher, o chat sai três horas adiantado para quem escreve do Brasil." />
                            </div>
                        </x-admin.card>
                    </div>
                </div>
                <div class="abas-salvar">
                    <button class="btn btn-golden"><i class="fas fa-save me-1"></i> Salvar contato e endereço</button>
                </div>
            </form>
        </div>

        {{-- 2. Para onde o site leva: endereços dos servidores e cada rótulo do caminho. --}}
        <div class="tab-pane fade" id="aba-acessos" data-aba-painel="acessos" role="tabpanel" aria-labelledby="aba-acessos-botao" tabindex="0">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-atualiza=".app-content">
                @csrf
                <input type="hidden" name="_aba" value="acessos">
                <div class="row g-3">
                    <div class="col-xl-5">
                        <x-admin.card title="Acessos externos do site" icon="fa-arrow-up-right-from-square" ajuda="acessos_externos"
                            descricao="Para onde levam o submenu da conta e o botão do topo do site, com o nome e o ícone de cada servidor. O portal do cliente é rota interna e não aparece aqui.">
                            <div class="row g-3">
                                <x-admin.field name="acesso_financeiro_url" type="url" label="Financeiro antigo" :value="$settings['acesso_financeiro_url'] ?? config('golden.acessos.financeiro')" maxlength="255" :opcional="true" placeholder="https://financeiro.exemplo.com.br/login" hint="Abre em outra aba, pelo submenu do ícone de login." />
                                <x-admin.field name="acesso_servidor_1_url" type="url" label="Endereço do servidor 1" :value="$settings['acesso_servidor_1_url'] ?? config('golden.acessos.servidor_1')" col="col-12 col-md-6" maxlength="255" :opcional="true" placeholder="https://app1.exemplo.com.br" ajuda="acesso_servidor_url" />
                                <x-admin.field name="acesso_servidor_2_url" type="url" label="Endereço do servidor 2" :value="$settings['acesso_servidor_2_url'] ?? config('golden.acessos.servidor_2')" col="col-12 col-md-6" maxlength="255" :opcional="true" placeholder="http://203.0.113.10:8082/" ajuda="acesso_servidor_url" />
                                <x-admin.field name="acesso_servidor_1_titulo" label="Nome do servidor 1" :value="$settings['acesso_servidor_1_titulo'] ?? $textosAcesso['servidor_1_titulo']" col="col-12 col-md-6" maxlength="60" :opcional="true" placeholder="Servidor 1" hint="É o que o cliente lê no modal — o endereço fica só no link." />
                                <x-admin.field name="acesso_servidor_2_titulo" label="Nome do servidor 2" :value="$settings['acesso_servidor_2_titulo'] ?? $textosAcesso['servidor_2_titulo']" col="col-12 col-md-6" maxlength="60" :opcional="true" placeholder="Servidor 2" />
                                <x-admin.field name="acesso_servidor_1_icone" type="icon" label="Ícone do servidor 1" :value="$settings['acesso_servidor_1_icone'] ?? $textosAcesso['servidor_1_icone']" col="col-12 col-md-6" :opcional="true" hint="Selecione visualmente pelo ícone" />
                                <x-admin.field name="acesso_servidor_2_icone" type="icon" label="Ícone do servidor 2" :value="$settings['acesso_servidor_2_icone'] ?? $textosAcesso['servidor_2_icone']" col="col-12 col-md-6" :opcional="true" hint="Selecione visualmente pelo ícone" />
                            </div>
                        </x-admin.card>
                    </div>
                    <div class="col-xl-7">
                        <x-admin.card title="Textos dos acessos" icon="fa-pen-nib" ajuda="acessos_textos"
                            descricao="Cada rótulo do caminho até a plataforma, do botão dourado ao modal. Em branco vale o texto padrão da casa.">
                            <div class="row g-3">
                                <x-admin.field name="acesso_botao_rotulo" label="Rótulo do botão dourado" :value="$settings['acesso_botao_rotulo'] ?? $textosAcesso['botao']" col="col-12 col-md-6" maxlength="40" :opcional="true" placeholder="Rastreamento" hint="Aparece no topo e na gaveta do site, e abre a escolha de servidor." />
                                <x-admin.field name="acesso_conta_rotulo" label="Nome do ícone de conta" :value="$settings['acesso_conta_rotulo'] ?? $textosAcesso['conta']" col="col-12 col-md-6" maxlength="60" :opcional="true" placeholder="Acessar minha conta" hint="Lido pelo leitor de tela e mostrado ao passar o mouse." />
                                <x-admin.field name="acesso_area_titulo" label="Título da área no menu" :value="$settings['acesso_area_titulo'] ?? $textosAcesso['area']" col="col-12 col-md-6" maxlength="60" :opcional="true" placeholder="Área do cliente" hint="O grupo que reúne portal e financeiro na gaveta do site." />
                                <x-admin.field name="acesso_portal_titulo" label="Nome do portal do cliente" :value="$settings['acesso_portal_titulo'] ?? $textosAcesso['portal_titulo']" col="col-12 col-md-6" maxlength="60" :opcional="true" placeholder="Painel do cliente" />
                                <x-admin.field name="acesso_portal_desc" label="Descrição do portal" :value="$settings['acesso_portal_desc'] ?? $textosAcesso['portal_desc']" col="col-12 col-md-6" maxlength="80" :opcional="true" placeholder="Pedidos, chamados e contratos" />
                                <x-admin.field name="acesso_financeiro_titulo" label="Nome do financeiro antigo" :value="$settings['acesso_financeiro_titulo'] ?? $textosAcesso['financeiro_titulo']" col="col-12 col-md-6" maxlength="60" :opcional="true" placeholder="Financeiro antigo" />
                                <x-admin.field name="acesso_financeiro_desc" label="Descrição do financeiro" :value="$settings['acesso_financeiro_desc'] ?? $textosAcesso['financeiro_desc']" col="col-12 col-md-6" maxlength="80" :opcional="true" placeholder="Abrir o sistema financeiro" />
                                <x-admin.field name="acesso_modal_titulo" label="Título do modal" :value="$settings['acesso_modal_titulo'] ?? $textosAcesso['modal_titulo']" col="col-12 col-md-6" maxlength="80" :opcional="true" placeholder="Acessar a plataforma" />
                                <x-admin.field name="acesso_modal_desc" label="Descrição do modal" :value="$settings['acesso_modal_desc'] ?? $textosAcesso['modal_desc']" col="col-12" maxlength="160" :opcional="true" placeholder="Cada servidor é uma plataforma diferente. Escolha o da sua instalação." />
                                <x-admin.field name="acesso_ajuda" label="Frase de ajuda" :value="$settings['acesso_ajuda'] ?? $textosAcesso['ajuda']" col="col-12 col-md-6" maxlength="120" :opcional="true" placeholder="Não sabe qual é o seu?" hint="Fica no pé do modal, antes do link." />
                                <x-admin.field name="acesso_ajuda_link" label="Texto do link de ajuda" :value="$settings['acesso_ajuda_link'] ?? $textosAcesso['ajuda_link']" col="col-12 col-md-6" maxlength="60" :opcional="true" placeholder="Fale com um especialista" hint="O link leva à página de contato." />
                            </div>
                        </x-admin.card>
                    </div>
                </div>
                <div class="abas-salvar">
                    <button class="btn btn-golden"><i class="fas fa-save me-1"></i> Salvar acessos do site</button>
                </div>
            </form>
        </div>

        {{-- 3. Loja: ligar/desligar e as regras comerciais, cada um no seu formulário. --}}
        <div class="tab-pane fade" id="aba-loja" data-aba-painel="loja" role="tabpanel" aria-labelledby="aba-loja-botao" tabindex="0">
            <div class="row g-3">
                {{-- Barra superior: Status da loja + Atalhos rápidos em largura total --}}
                <div class="col-12">
                    <x-admin.card title="Loja virtual" icon="fa-store" ajuda="loja_ligar" :descricao="$lojaAtiva ? 'Ativa — as páginas /loja estão no ar e no menu do site.' : 'Desativada — /loja sai do ar e oculta do menu.'">
                        <x-slot:actions>
                            <x-admin.status :rotulo="$lojaAtiva ? 'ativa' : 'desativada'" :tom="tom_por_flag($lojaAtiva)" />
                        </x-slot:actions>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('site.loja.index') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-cart-shopping me-1"></i> Vitrine da Loja
                                </a>
                                <a href="{{ route('admin.pedidos.index') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-box-archive me-1"></i> Pedidos &amp; Despacho
                                </a>
                                <a href="{{ route('site.rastreio.index') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-barcode me-1"></i> Rastreamento Público
                                </a>
                            </div>
                            <form method="POST" action="{{ route('admin.settings.loja') }}" class="m-0" data-atualiza=".app-content">
                                @csrf
                                <input type="hidden" name="ativa" value="{{ $lojaAtiva ? '0' : '1' }}">
                                <button class="btn btn-sm {{ $lojaAtiva ? 'btn-outline-danger' : 'btn-success' }}">
                                    <i class="fas fa-power-off me-1"></i> {{ $lojaAtiva ? 'Desativar loja agora' : 'Ativar loja agora' }}
                                </button>
                            </form>
                        </div>
                    </x-admin.card>
                </div>

                {{-- Formulário único de regras comerciais e logística --}}
                <div class="col-12">
                    <form method="POST" action="{{ route('admin.settings.loja-regras') }}" id="formLojaRegras" class="row g-3" data-atualiza=".app-content">
                        @csrf
                        <div class="col-12">
                            <x-admin.card title="Regras Comerciais & Pagamento" icon="fa-sliders" ajuda="loja_regras" descricao="Frete base, retirada na empresa, chave Pix e e-mail de alertas.">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <x-admin.switch name="loja_cobrar_primeira_mensalidade" label="Cobrar 1ª mensalidade hoje (Padrão)" :checked="($settings['loja_cobrar_primeira_mensalidade'] ?? '1') === '1'" hint="Padrão para produtos recorrentes." />
                                    </div>
                                    <div class="col-md-4">
                                        <x-admin.switch name="loja_livre" label="Frete combinado / a combinar" :checked="$livre" />
                                    </div>
                                    <div class="col-md-4">
                                        <x-admin.switch name="loja_retirada" label="Permitir retirada na Golden" :checked="$retirada" />
                                    </div>
                                    <x-admin.field name="loja_frete" type="number" label="Frete fixo padrão (R$)" :value="$paraExibir($settings['loja_frete_cents'] ?? 0)" col="col-md-3" step="0.01" min="0" max="99999" placeholder="0,00" :travado="$livre" />
                                    <x-admin.field name="loja_frete_gratis_acima" type="number" label="Frete grátis acima de (R$)" :value="$paraExibir($settings['loja_frete_gratis_acima_cents'] ?? 0)" col="col-md-3" step="0.01" min="0" max="99999" placeholder="ex.: 997,00" :travado="$livre" />
                                    <x-admin.field name="loja_pix_chave" label="Chave Pix exibida no checkout" :value="$settings['loja_pix_chave'] ?? ''" maxlength="120" :opcional="true" col="col-md-3" placeholder="CPF, CNPJ, e-mail ou aleatória" />
                                    <x-admin.field name="loja_email_equipe" type="email" label="E-mail da equipe para alertas" :value="$settings['loja_email_equipe'] ?? ''" maxlength="160" :opcional="true" col="col-md-3" hint="Recebe alerta de cada venda nova." placeholder="vendas@goldenrastreamento.com.br" />
                                </div>
                            </x-admin.card>
                        </div>

                        <div class="col-12">
                    <x-admin.card title="Logística & Transportadoras" icon="fa-truck-fast" ajuda="logistica_transportadoras"
                        descricao="Configure cada modalidade de envio em sua respectiva aba e clique em salvar ao final.">
                        <ul class="nav nav-pills gap-2 mb-3 pb-2 border-bottom" id="lojaLogisticaTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active d-flex align-items-center gap-2" id="tab-correios-btn" data-bs-toggle="pill" data-bs-target="#tab-correios" type="button" role="tab">
                                    <i class="fas fa-truck-fast"></i> Correios
                                    <span class="badge {{ $correiosAtivo ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $correiosAtivo ? 'Ativo' : 'Off' }}</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link d-flex align-items-center gap-2" id="tab-melhor-envio-btn" data-bs-toggle="pill" data-bs-target="#tab-melhor-envio" type="button" role="tab">
                                    <i class="fas fa-shipping-fast"></i> Melhor Envio
                                    <span class="badge {{ $melhorEnvioAtivo ? ($melhorEnvioSandbox ? 'text-bg-warning' : 'text-bg-success') : 'text-bg-secondary' }}">
                                        {{ $melhorEnvioAtivo ? ($melhorEnvioSandbox ? 'Sandbox' : 'Ativo') : 'Off' }}
                                    </span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link d-flex align-items-center gap-2" id="tab-mercado-livre-btn" data-bs-toggle="pill" data-bs-target="#tab-mercado-livre" type="button" role="tab">
                                    <i class="fas fa-bag-shopping"></i> Mercado Envios
                                    <span class="badge {{ $mercadoLivreAtivo ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $mercadoLivreAtivo ? 'Ativo' : 'Off' }}</span>
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="lojaLogisticaContent">
                            {{-- Sub-aba Correios --}}
                            <div class="tab-pane fade show active" id="tab-correios" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <x-admin.switch name="correios_ativo" label="Ativar cálculo dinâmico de frete Correios no carrinho e checkout" :checked="$correiosAtivo" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-admin.switch name="correios_possui_contrato" label="Empresa possui contrato com os Correios" :checked="$correiosContrato" />
                                        <div class="form-hint">Aplica tarifas reduzidas e habilita credenciais CWS/SIGEP.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <x-admin.switch name="correios_coleta_empresa" label="Coleta na empresa (retira na sede Golden)" :checked="$correiosColeta" />
                                        <div class="form-hint">Indica nos pedidos que as encomendas aguardam coleta na Golden.</div>
                                    </div>

                                    <x-admin.field name="correios_cep_origem" label="CEP de Origem / Expedição" :value="$settings['correios_cep_origem'] ?? '35500-000'" col="col-md-4" placeholder="35500-000" />
                                    <x-admin.field name="correios_prazo_adicional" type="number" label="Prazo adicional (dias úteis)" :value="$settings['correios_prazo_adicional'] ?? 1" col="col-md-4" min="0" max="30" />
                                    <x-admin.field name="correios_taxa_manuseio" type="number" step="0.01" label="Taxa manuseio / embalagem (R$)" :value="$settings['correios_taxa_manuseio'] ?? '0.00'" col="col-md-4" min="0" max="999" placeholder="0.00" />

                                    <div class="col-12 pt-2 border-top">
                                        <h6 class="small fw-bold text-uppercase text-secondary mb-2"><i class="fas fa-boxes-packing me-1"></i> Modalidades Habilitadas</h6>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <x-admin.switch name="correios_sedex_ativo" label="SEDEX (Envio Expresso / Rápido)" :checked="$correiosSedex" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-admin.switch name="correios_pac_ativo" label="PAC (Envio Econômico)" :checked="$correiosPac" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 pt-2 border-top">
                                        <details>
                                            <summary class="small fw-bold text-uppercase text-secondary" style="cursor:pointer">
                                                <i class="fas fa-file-contract me-1"></i> Credenciais do Contrato Correios &amp; Instruções de Coleta (Opcional)
                                            </summary>
                                            <div class="row g-3 mt-1">
                                                <x-admin.field name="correios_codigo_administrativo" label="Código Administrativo" :value="$settings['correios_codigo_administrativo'] ?? ''" col="col-md-3" :opcional="true" placeholder="Ex.: 08000000" />
                                                <x-admin.field name="correios_cartao_postagem" label="Cartão de Postagem" :value="$settings['correios_cartao_postagem'] ?? ''" col="col-md-3" :opcional="true" placeholder="Ex.: 0057018901" />
                                                <x-admin.field name="correios_usuario" label="Usuário CWS / SIGEP" :value="$settings['correios_usuario'] ?? ''" col="col-md-3" :opcional="true" placeholder="Ex.: goldenrastreamento" />
                                                <x-admin.field name="correios_senha" type="password" label="Senha / Token CWS" :value="$settings['correios_senha'] ?? ''" col="col-md-3" :opcional="true" placeholder="••••••••" />
                                                <x-admin.field name="correios_instrucoes_coleta" type="textarea" label="Instruções de Coleta na Empresa" :value="$settings['correios_instrucoes_coleta'] ?? ''" rows="2" col="col-12" :opcional="true" placeholder="Ex.: Coleta diária às 15h na sede da Golden Rastreamento." />
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            </div>

                            {{-- Sub-aba Melhor Envio --}}
                            <div class="tab-pane fade" id="tab-melhor-envio" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <x-admin.switch name="melhor_envio_ativo" label="Ativar cálculo dinâmico via Melhor Envio" :checked="$melhorEnvioAtivo" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-admin.switch name="melhor_envio_sandbox" label="Modo Sandbox (homologação / testes)" :checked="$melhorEnvioSandbox" />
                                        <div class="form-hint">Use Sandbox apenas com token gerado em sandbox.melhorenvio.com.br.</div>
                                    </div>

                                    <x-admin.field name="melhor_envio_token" type="password" label="Token de Acesso (API v2 Melhor Envio)"
                                        value="" col="col-md-8" :opcional="true"
                                        :placeholder="$melhorEnvioTemToken ? '•••••••••••••••• (token salvo — deixe em branco para manter)' : 'Cole o Bearer Token gerado no painel do Melhor Envio'"
                                        hint="Deixe em branco para manter o token atual salvo." />
                                    <x-admin.field name="melhor_envio_cep_origem" label="CEP de Origem (Melhor Envio)"
                                        :value="$settings['melhor_envio_cep_origem'] ?? ($settings['correios_cep_origem'] ?? '35500-000')"
                                        col="col-md-4" placeholder="35500-000" hint="Se vazio, usa o CEP de origem dos Correios." />

                                    <x-admin.field name="melhor_envio_peso_kg" type="number" step="0.01" min="0.05" max="100"
                                        label="Peso padrão (kg)" :value="$settings['melhor_envio_peso_kg'] ?? '0.35'" col="col-md-3" />
                                    <x-admin.field name="melhor_envio_largura_cm" type="number" min="11" max="200"
                                        label="Largura (cm)" :value="$settings['melhor_envio_largura_cm'] ?? 16" col="col-md-3" />
                                    <x-admin.field name="melhor_envio_altura_cm" type="number" min="2" max="200"
                                        label="Altura (cm)" :value="$settings['melhor_envio_altura_cm'] ?? 8" col="col-md-3" />
                                    <x-admin.field name="melhor_envio_comprimento_cm" type="number" min="16" max="200"
                                        label="Comprimento (cm)" :value="$settings['melhor_envio_comprimento_cm'] ?? 22" col="col-md-3" />

                                    <x-admin.field name="melhor_envio_prazo_adicional" type="number" min="0" max="30"
                                        label="Prazo adicional (dias úteis)" :value="$settings['melhor_envio_prazo_adicional'] ?? 1" col="col-md-4" />
                                    <x-admin.field name="melhor_envio_taxa_adicional" type="number" step="0.01" min="0" max="999"
                                        label="Taxa adicional / embalagem (R$)" :value="$paraExibir($settings['melhor_envio_taxa_adicional_cents'] ?? 0)" col="col-md-4" placeholder="0,00" />
                                    <x-admin.field name="melhor_envio_servicos" label="IDs de serviços (opcional)"
                                        :value="$settings['melhor_envio_servicos'] ?? ''" col="col-md-4" :opcional="true"
                                        placeholder="Ex.: 1,2,3,4,17" hint="Vazio = todas as transportadoras." />

                                    <div class="col-12 d-flex flex-wrap align-items-center gap-2 pt-2 border-top">
                                        <button type="submit" formaction="{{ route('admin.settings.melhor-envio.testar') }}" formnovalidate class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-plug me-1"></i> Testar conexão com Melhor Envio
                                        </button>
                                        <span class="small text-secondary">Salve as configurações antes de testar um token novo.</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Sub-aba Mercado Envios --}}
                            <div class="tab-pane fade" id="tab-mercado-livre" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <x-admin.switch name="mercado_livre_envio_ativo" label="Habilitar opção de envio pelo Mercado Livre (Mercado Envios)" :checked="$mercadoLivreAtivo" />
                                    </div>
                                    <x-admin.field name="mercado_livre_access_token" type="password" label="Access Token API Oficial Mercado Livre (Opcional)"
                                        value="" col="col-12" :opcional="true"
                                        :placeholder="!empty($settings['mercado_livre_access_token']) ? '•••••••••••••••• (token salvo — deixe em branco para manter)' : 'Cole seu Access Token OAuth da API Mercado Livre'"
                                        hint="Necessário para consultar envios do Mercado Envios em tempo real na API oficial (api.mercadolibre.com/shipments)." />
                                    <x-admin.field name="mercado_livre_link_loja" label="Link da Loja / Anúncio Oficial no Mercado Livre" :value="$settings['mercado_livre_link_loja'] ?? ''" col="col-12" :opcional="true" placeholder="https://www.mercadolivre.com.br/loja/golden-rastreamento" />
                                    <x-admin.field name="mercado_livre_instrucoes" type="textarea" label="Instruções e orientações do Mercado Envios" :value="$settings['mercado_livre_instrucoes'] ?? ''" rows="2" col="col-12" :opcional="true" placeholder="Ex.: O envio é gerenciado pela plataforma do Mercado Livre com código de rastreamento Mercado Envios." />
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-top">
                            <div class="row g-3">
                                <x-admin.field name="rastreio_wonca_token" type="password" label="Chave API de Rastreamento Universal (Wonca / Seu Rastreio — Opcional)"
                                    value="" col="col-12" :opcional="true"
                                    :placeholder="!empty($settings['rastreio_wonca_token']) ? '•••••••••••••••• (token salvo — deixe em branco para manter)' : 'Cole sua Apikey da Wonca / Seu Rastreio'"
                                    hint="Permite rastrear encomendas dos Correios e outras transportadoras em tempo real na página /rastreamento sem exigir contrato CWS." />
                            </div>
                        </div>

                        <div class="d-flex justify-content-end pt-3 mt-3 border-top">
                            <button type="submit" class="btn btn-golden">
                                <i class="fas fa-save me-1"></i> Salvar todas as regras e configurações de logística
                            </button>
                        </div>
                    </x-admin.card>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- 4. A caixa multicanal: textos da caixa e um item por atendente humano. --}}
        <div class="tab-pane fade" id="aba-whatsapp" data-aba-painel="whatsapp" role="tabpanel" aria-labelledby="aba-whatsapp-botao" tabindex="0">
            <x-admin.card title="Botão do WhatsApp no site" icon="fa-brands fa-whatsapp" ajuda="whatsapp_box"
                :descricao="$boxAtivo ? 'Abre a central de canais com a mensagem pronta por segmento.' : 'Link direto para o número principal.'">
                <x-slot:actions>
                    <x-admin.status :rotulo="$boxAtivo ? 'caixa multicanal' : 'link direto'" :tom="tom_por_flag($boxAtivo)" />
                </x-slot:actions>
                <form method="POST" action="{{ route('admin.settings.whatsapp') }}" class="row g-3" data-atualiza=".app-content">
                    @csrf
                    <x-admin.switch name="whatsapp_box_ativo" label="Mostrar a caixa multicanal" :checked="$boxAtivo" />

                    <div class="col-12 pt-2">
                        <span class="form-label">Textos da caixa</span>
                        <div class="form-hint d-block mb-2">Tudo o que o visitante lê no botão do canto e na caixa aberta. Em branco, vale o texto padrão da casa.</div>
                    </div>
                    <x-admin.field name="whatsapp_box_titulo" label="Título da caixa" :value="$settings['whatsapp_box_titulo'] ?? ''" col="col-12 col-md-7" maxlength="120" :opcional="true" :placeholder="config('golden.whatsapp_box.titulo')" />
                    <x-admin.field name="whatsapp_box_online" label="Selo de status" :value="$settings['whatsapp_box_online'] ?? ''" col="col-12 col-md-5" maxlength="60" :opcional="true" :placeholder="config('golden.whatsapp_box.online')" hint="Ao lado do ponto verde, no topo da caixa." />
                    <x-admin.field name="whatsapp_box_subtitulo" label="Subtítulo" :value="$settings['whatsapp_box_subtitulo'] ?? ''" col="col-12" maxlength="180" :opcional="true" :placeholder="config('golden.whatsapp_box.subtitulo')" />
                    <x-admin.field name="whatsapp_box_flutuante" label="Dica do botão flutuante" :value="$settings['whatsapp_box_flutuante'] ?? ''" col="col-12 col-md-6" maxlength="60" :opcional="true" :placeholder="config('golden.whatsapp_box.flutuante')" hint="Aparece ao passar o mouse no botão do canto." />
                    <x-admin.field name="whatsapp_box_rodape" label="Rodapé da caixa" :value="$settings['whatsapp_box_rodape'] ?? ''" col="col-12 col-md-6" maxlength="80" :opcional="true" :placeholder="config('golden.whatsapp_box.rodape')" hint="O último link, para quem não se encaixa em nenhum canal." />
                    <x-admin.field name="whatsapp_box_abertura" type="textarea" :rows="2" label="Mensagem do rodapé" :value="$settings['whatsapp_box_abertura'] ?? ''" col="col-12" maxlength="300" :opcional="true" :placeholder="config('golden.whatsapp_box.abertura')" hint="Já vai escrita na conversa quando o visitante toca no rodapé." />

                    <div class="col-12 pt-2">
                        <span class="form-label">Atendentes humanos</span>
                        <div class="form-hint d-block mb-2">Cada canal é um item: nome, descrição, ícone, número e mensagem pronta. O número vazio usa o WhatsApp principal ({{ $numeroPadrao }}); o assistente virtual tem ficha própria em Atendimento → Assistente virtual.</div>
                        @foreach(config('golden.whatsapp_box.canais') as $canal)
                            @php
                                $chave = 'whatsapp_canal_'.$canal['id'];
                                $doBanco = fn ($campo, $padrao) => ($settings[$chave.'_'.$campo] ?? '') !== '' ? $settings[$chave.'_'.$campo] : $padrao;
                            @endphp
                            <div class="canal-bloco">
                                <x-admin.switch name="{{ $chave }}_ativo" :checked="($settings[$chave.'_ativo'] ?? '1') === '1'">
                                    <i class="fas {{ $doBanco('icone', $canal['icone']) }} text-gold me-1" aria-hidden="true"></i>{{ $doBanco('nome', $canal['nome']) }}
                                    <span class="d-block text-secondary small">Ligado na caixa do site</span>
                                </x-admin.switch>
                                <div class="row g-3">
                                    <x-admin.field name="{{ $chave }}_nome" label="Nome do atendente" :value="$settings[$chave.'_nome'] ?? ''" col="col-12 col-md-6" maxlength="60" :opcional="true" :placeholder="$canal['nome']" />
                                    <x-admin.field name="{{ $chave }}_desc" label="Descrição" :value="$settings[$chave.'_desc'] ?? ''" col="col-12 col-md-6" maxlength="120" :opcional="true" :placeholder="$canal['desc']" />
                                    <x-admin.field name="{{ $chave }}_icone" type="icon" label="Ícone do canal" :value="$settings[$chave.'_icone'] ?? ''" col="col-12 col-md-6" :opcional="true" hint="Selecione visualmente pelo ícone" />
                                    <x-admin.field name="{{ $chave }}_numero" label="Número do WhatsApp" :value="$settings[$chave.'_numero'] ?? ''" col="col-12 col-md-6" maxlength="20" :opcional="true" placeholder="só dígitos" hint="Vazio, usa o principal." />
                                    <x-admin.field name="{{ $chave }}_msg" type="textarea" :rows="2" label="Mensagem pronta no WhatsApp" :value="$settings[$chave.'_msg'] ?? ''" col="col-12" maxlength="300" :opcional="true" :placeholder="$canal['msg']" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-12">
                        <button class="btn btn-golden"><i class="fas fa-save me-1"></i> Salvar WhatsApp</button>
                    </div>
                </form>
            </x-admin.card>
        </div>

        {{-- 5. Medição: a coleta de eventos e os três identificadores. --}}
        <div class="tab-pane fade" id="aba-medicao" data-aba-painel="medicao" role="tabpanel" aria-labelledby="aba-medicao-botao" tabindex="0">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-atualiza=".app-content">
                @csrf
                <input type="hidden" name="_aba" value="medicao">
                <div class="row g-3">
                    <div class="col-xl-8">
                        <x-admin.card title="Medição e consentimento" icon="fa-chart-area" ajuda="medicao_consentimento"
                            descricao="Só habilite a coleta depois de publicar a Política de Privacidade e o banner de consentimento.">
                            <div class="row g-3">
                                <x-admin.switch name="tracking_enabled" label="Ativar coleta de eventos (Google Ads, GA4, Meta Pixel, GTM)" :checked="($settings['tracking_enabled'] ?? '0') === '1'" />
                                <x-admin.field name="google_ads_tag_id" label="Google Ads Tag ID (Pixel / Tag)" :value="$settings['google_ads_tag_id'] ?? ''" col="col-12 col-md-6" maxlength="60" :opcional="true" placeholder="AW-XXXXXXXXXX" hint="ID da tag do Google Ads (ex: AW-123456789) para rastrear visitantes no site." />
                                <x-admin.field name="google_ads_conversion_label" label="Rótulo de Conversão de Leads (Google Ads)" :value="$settings['google_ads_conversion_label'] ?? ''" col="col-12 col-md-6" maxlength="100" :opcional="true" placeholder="AW-XXXXXXXXXX/AbCdEfGhIjK ou AbCdEfGhIjK" hint="Rótulo do evento disparado ao cadastrar lead pelo site." />
                                <x-admin.field name="ga_measurement_id" label="GA4 Measurement ID" :value="$settings['ga_measurement_id'] ?? ''" col="col-12 col-md-4" maxlength="60" :opcional="true" placeholder="G-XXXXXXX" />
                                <x-admin.field name="meta_pixel_id" label="Meta Pixel ID" :value="$settings['meta_pixel_id'] ?? ''" col="col-12 col-md-4" maxlength="60" :opcional="true" placeholder="123456789012345" />
                                <x-admin.field name="gtm_id" label="GTM ID" :value="$settings['gtm_id'] ?? ''" col="col-12 col-md-4" maxlength="60" :opcional="true" placeholder="GTM-XXXX" />
                            </div>
                        </x-admin.card>
                    </div>
                </div>
                <div class="abas-salvar">
                    <button class="btn btn-golden"><i class="fas fa-save me-1"></i> Salvar medição</button>
                </div>
            </form>
        </div>

        {{-- 6. Financeiro (WHMCS): o estado da porta, o que passa por ela e a prova de que abre.
             As credenciais ficam na aba Chaves porque são segredo; o comportamento delas vive
             aqui, porque é decisão de negócio e não chave de servidor. --}}
        <div class="tab-pane fade" id="aba-financeiro" data-aba-painel="financeiro" role="tabpanel" aria-labelledby="aba-financeiro-botao" tabindex="0">
            <div class="row g-3">
                <div class="col-xl-5">
                    <x-admin.card title="Estado da conexão" icon="fa-plug-circle-bolt" ajuda="whmcs"
                        :descricao="'O que o portal do cliente vai consultar no financeiro, no endereço abaixo.'">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <x-admin.status :rotulo="$financeiro['configurado'] ? 'Credenciais completas' : 'Falta configurar'" :tom="tom_por_flag($financeiro['configurado'])" />
                            <span class="form-hint">{{ $financeiro['configurado'] ? 'há o que consultar' : 'nenhuma chamada é feita' }}</span>
                        </div>

                        <dl class="defs mb-0">
                            <dt>Endereço consultado</dt>
                            <dd><code>{{ $financeiro['endpoint'] !== '' ? $financeiro['endpoint'] : '— sem endereço —' }}</code></dd>
                            <dt>Tempo de espera</dt>
                            <dd>{{ (int) config('whmcs.timeout') }}s <span class="form-hint">por chamada</span></dd>
                            <dt>Limite de itens</dt>
                            <dd>{{ (int) config('whmcs.limite') }} <span class="form-hint">por lista</span></dd>
                            <dt>Grupos exibidos</dt>
                            <dd>{{ \App\Services\Whmcs\Veiculacao::grupos() === [] ? 'todos' : implode(' · ', \App\Services\Whmcs\Veiculacao::grupos()) }}</dd>
                        </dl>

                        @if(!$financeiro['configurado'])
                            <p class="small text-secondary mb-0 mt-3">
                                Faltam: {{ implode(', ', $financeiro['faltando']) }}.
                                <a href="#chaves-whmcs">Preencher na aba Chaves</a> — o valor fica cifrado no banco, e o <code>.env</code> deste servidor continua conduzindo o que o banco não tem.
                            </p>
                        @endif
                    </x-admin.card>
                </div>

                <div class="col-xl-7">
                    <x-admin.card title="Testar a conexão agora" icon="fa-satellite-dish" ajuda="whmcs_teste"
                        descricao="Uma chamada de verdade ao financeiro, na frente de quem aperta o botão.">
                        <form method="POST" action="{{ route('admin.settings.whmcs.sondar') }}" class="row g-3" data-atualiza=".app-content">
                            @csrf
                            <x-admin.field name="sonda_email" type="email" label="E-mail de uma conta do WHMCS"
                                :value="$settings['contato_email'] ?? ''" :col="''" maxlength="190" :required="true"
                                placeholder="cliente@exemplo.com.br"
                                hint="Informe o e-mail de um cliente seu. A sonda pergunta ao financeiro se aquela conta existe — é assim que se sabe se a credencial abre a porta." />

                            <div class="col-12">
                                <button class="btn btn-outline-secondary" @if(!$financeiro['configurado']) disabled @endif>
                                    <i class="fas fa-bolt me-1"></i> Sondar o financeiro
                                </button>
                                @if(!$financeiro['configurado'])
                                    <span class="form-hint ms-2">Complete as credenciais para liberar o teste.</span>
                                @endif
                            </div>

                            @if($sonda)
                                <div class="col-12">
                                    <div class="d-flex align-items-start gap-2">
                                        <x-admin.status :rotulo="$sonda['titulo']" :tom="tom_por_status('sonda_whmcs', $sonda['estado'])" class="flex-shrink-0 mt-1" />
                                        <p class="small text-secondary mb-0">{{ $sonda['texto'] }}</p>
                                    </div>
                                </div>
                            @endif
                        </form>
                    </x-admin.card>
                </div>

                <div class="col-12">
                    <x-admin.card :title="$veiculacao['rotulo']" :icon="$veiculacao['icone']" :descricao="$veiculacao['descricao']"
                        :ajuda="\App\Support\ChavesDeIntegracao::GRUPO_VEICULACAO">
                        <form method="POST" action="{{ route('admin.settings.chaves') }}" class="row g-3" data-atualiza=".app-content">
                            @csrf
                            <input type="hidden" name="grupo" value="{{ \App\Support\ChavesDeIntegracao::GRUPO_VEICULACAO }}">
                            @foreach ($veiculacao['campos'] as $campo)
                                <x-admin.campo-chave :campo="$campo" />
                            @endforeach
                            <div class="col-12">
                                <button class="btn btn-golden"><i class="fas fa-save me-1"></i> Salvar veiculação</button>
                            </div>
                        </form>
                    </x-admin.card>
                </div>
            </div>
        </div>

        {{-- 7. Chaves: um grupo por aba interna, com o ambiente explicado no pé. --}}
        <div class="tab-pane fade" id="aba-chaves" data-aba-painel="chaves" role="tabpanel" aria-labelledby="aba-chaves-botao" tabindex="0">
            <p class="form-hint">O que for gravado aqui vale na hora, sem tocar no servidor, e fica cifrado no banco quando é segredo. Campo vazio é o painel se retirando do caminho: o <code>.env</code> deste servidor volta a conduzir aquele valor.</p>

            <div class="abas abas-aninhadas" data-abas="chaves">
                <ul class="nav abas-pilulas" role="tablist" aria-label="Grupos de chaves de integração">
                    @foreach ($gruposDeChaves as $id => $grupo)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $loop->first ? 'active' : '' }}" type="button" role="tab"
                                id="aba-chaves-{{ $id }}-botao"
                                data-bs-toggle="tab" data-bs-target="#aba-chaves-{{ $id }}" data-aba="{{ $id }}"
                                aria-controls="aba-chaves-{{ $id }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                <i class="fas {{ $grupo['icone'] }}" aria-hidden="true"></i>
                                <span>{{ $grupo['rotulo'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content">
                    @foreach ($gruposDeChaves as $id => $grupo)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="aba-chaves-{{ $id }}" data-aba-painel="{{ $id }}" role="tabpanel" aria-labelledby="aba-chaves-{{ $id }}-botao" tabindex="0">
                            <x-admin.card :title="$grupo['rotulo']" :icon="$grupo['icone']" :descricao="$grupo['descricao']" :ajuda="$id">
                                @if($id === 'google-ads')
                                    <div class="alert alert-info d-flex align-items-center gap-2 mb-3 py-2 px-3 small">
                                        <i class="fas fa-bullseye fa-lg text-info" aria-hidden="true"></i>
                                        <div>
                                            Para configurar a <strong>Tag de Rastreamento (Pixel)</strong> e o <strong>Rótulo de Conversão de Leads</strong> do Google Ads no site, acesse a aba <a href="#aba-medicao" data-bs-toggle="tab" data-bs-target="#aba-medicao" class="alert-link text-decoration-underline">Medição</a>.
                                        </div>
                                    </div>
                                @endif
                                @if($id === 'avaliacoes')
                                    <div class="alert alert-info d-flex align-items-center gap-2 mb-3 py-2 px-3 small">
                                        <i class="fas fa-info-circle fa-lg text-info" aria-hidden="true"></i>
                                        <div>
                                            <strong>Dica:</strong> Para exibir depoimentos com o selo oficial do Google no site, você não precisa obrigatoriamente preencher esta API. Você pode cadastrar as avaliações dos clientes diretamente em <a href="{{ route('admin.conteudo.depoimentos.index') }}" class="alert-link text-decoration-underline">Conteúdo → Depoimentos</a> escolhendo a origem <strong>Google</strong>.
                                        </div>
                                    </div>
                                @endif
                                @if($id === 'slack')
                                    <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 py-2 px-3 small">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fab fa-slack fa-lg text-warning" aria-hidden="true"></i>
                                            <div>
                                                Para gerenciar quais eventos (vendas, leads, coletas, entregas) tocam no Slack ou ativar notificações push, acesse <a href="{{ route('admin.notificacoes.preferencias') }}" class="alert-link text-decoration-underline">Preferências de Notificações</a>.
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-golden" data-bs-toggle="modal" data-bs-target="#modalSlackDownload">
                                            <i class="fas fa-download me-1"></i> Baixar Aplicativo Slack
                                        </button>
                                    </div>
                                @endif
                                <form method="POST" action="{{ route('admin.settings.chaves') }}" class="row g-3" data-atualiza=".app-content">
                                    @csrf
                                    <input type="hidden" name="grupo" value="{{ $id }}">
                                    @foreach ($grupo['campos'] as $campo)
                                        <x-admin.campo-chave :campo="$campo" />
                                    @endforeach
                                    <div class="col-12">
                                        <button class="btn btn-golden"><i class="fas fa-save me-1"></i> Salvar chaves</button>
                                    </div>
                                </form>
                            </x-admin.card>

                            {{-- O grupo do e-mail é o único que tem prova de fogo na própria aba: as
                                 outras chaves se conferem usando a integração, e o SMTP não tem tela
                                 nenhuma para "usar" — só a mensagem que sai ou não sai. --}}
                            @if($id === 'email')
                                <x-admin.card title="Testar o disparo agora" icon="fa-paper-plane" ajuda="email_teste" class="mt-3"
                                    :descricao="'Pelo transporte e pelo remetente abaixo, uma mensagem de verdade — o mesmo caminho do link de senha do portal e da segunda via da fatura.'">
                                    <dl class="defs mb-3">
                                        @foreach($correio['viagem'] as $rotulo => $valor)
                                            <dt>{{ $rotulo }}</dt><dd><code>{{ $valor }}</code></dd>
                                        @endforeach
                                    </dl>

                                    @if(!$correio['configurado'])
                                        <p class="small text-secondary mb-3">
                                            Ainda não há o que testar: {{ implode(', ', $correio['faltando']) }}.
                                            Preencha acima e salve — o valor passa a valer nesta hora, sem tocar no servidor.
                                        </p>
                                    @endif

                                    <form method="POST" action="{{ route('admin.settings.email.sondar') }}" class="row g-3" data-atualiza=".app-content">
                                        @csrf
                                        <x-admin.field name="sonda_email_destino" type="email" label="Receber a mensagem de prova em"
                                            :value="$settings['contato_email'] ?? ''" :col="''" maxlength="190" :required="true"
                                            placeholder="voce@seudominio.com.br"
                                            hint="Digite a sua própria caixa, nunca o e-mail de um cliente: isto é prova de transporte, não correspondência." />

                                        <div class="col-12">
                                            <button class="btn btn-outline-secondary" @if(!$correio['configurado']) disabled @endif>
                                                <i class="fas fa-bolt me-1"></i> Enviar a prova
                                            </button>
                                            @if(!$correio['configurado'])
                                                <span class="form-hint ms-2">Complete o transporte para liberar o teste.</span>
                                            @endif
                                        </div>

                                        @if($sondaEmail)
                                            <div class="col-12">
                                                <div class="d-flex align-items-start gap-2">
                                                    <x-admin.status :rotulo="$sondaEmail['titulo']" :tom="tom_por_status('sonda_email', $sondaEmail['estado'])" class="flex-shrink-0 mt-1" />
                                                    <p class="small text-secondary mb-0">{{ $sondaEmail['texto'] }}</p>
                                                </div>
                                            </div>
                                        @endif
                                    </form>
                                </x-admin.card>
                            @endif

                            @if($id === 'slack')
                                <x-admin.card title="Testar Envio & Baixar Aplicativo Slack" icon="fab fa-slack" class="mt-3"
                                    :descricao="'Dispare um alerta de prova diretamente no canal do Slack para verificar a conexão do Webhook, ou baixe o aplicativo oficial para computador e smartphone.'">
                                    <div class="row g-3 align-items-center">
                                        <div class="col-lg-6 border-end-lg">
                                            <h6 class="fw-bold mb-2"><i class="fas fa-bolt text-golden me-1"></i> Disparar mensagem de teste</h6>
                                            <p class="small text-secondary mb-3">
                                                Envia uma mensagem de teste imediata com a marca da Golden Rastreamento para a URL do Webhook gravada acima.
                                            </p>
                                            <form method="POST" action="{{ route('admin.settings.slack.sondar') }}" data-atualiza=".app-content">
                                                @csrf
                                                <button class="btn btn-outline-warning">
                                                    <i class="fab fa-slack me-1"></i> Disparar teste agora
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-lg-6 ps-lg-4">
                                            <h6 class="fw-bold mb-2"><i class="fas fa-download text-golden me-1"></i> Aplicativo para a Equipe</h6>
                                            <p class="small text-secondary mb-3">
                                                Disponível oficialmente para Windows, Mac, Android e iPhone.
                                            </p>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="https://slack.com/ssb/download-win64" target="_blank" rel="noopener" class="btn btn-sm btn-golden">
                                                    <i class="fab fa-windows me-1"></i> Windows (64-bit)
                                                </a>
                                                <a href="https://slack.com/ssb/download-mac" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fab fa-apple me-1"></i> macOS
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalSlackDownload">
                                                    <i class="fas fa-mobile-screen me-1"></i> Todas as opções
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </x-admin.card>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <x-admin.card title="Ambiente" icon="fa-circle-info" ajuda="ambiente_sistema" class="mt-3">
                <dl class="defs mb-0">
                    <dt>Banco ativo</dt><dd>{{ config('database.default') }}@if(config('database.default') === 'sqlite') <span class="form-hint">(<code>database/database.sqlite</code>)</span>@endif</dd>
                    <dt>Módulos</dt><dd>CRM (leads, clientes, interações) · CMS (páginas, depoimentos, FAQs) · ERP (produtos, estoque, fornecedores, pedidos)</dd>
                    <dt>Chaves</dt><dd>{{ count(\App\Support\ChavesDeIntegracao::campos()) }} campos, todos editáveis nesta tela: os de acesso ficam aqui, os de veiculação do financeiro na aba Financeiro (WHMCS). O <code>.env</code> deste servidor só conduz o que o banco não tem.</dd>
                </dl>
                <p class="small text-secondary mb-0 mt-2">Ficam no <code>.env</code> só o que é deste servidor e não decisão de ninguém: a ligação com o banco (<code>DB_CONNECTION=mysql</code>, <code>php artisan migrate --force</code> — as migrations rodam nos dois bancos), a chave da aplicação e o transporte do Google Ads (<code>GOOGLE_ADS_TRANSPORT</code>, <code>GOOGLE_ADS_CACHE_STORE</code>), que dependem do que a máquina tem.</p>
            </x-admin.card>
        </div>

        {{-- 8. Preloader personalizado (Site Público, Painel Admin e Portal do Cliente) --}}
        <div class="tab-pane fade" id="aba-preloader" data-aba-painel="preloader" role="tabpanel" aria-labelledby="aba-preloader-botao" tabindex="0">
            @php
                $preloaderSite = ($settings['preloader_site_ativo'] ?? '1') === '1';
                $preloaderAdmin = ($settings['preloader_admin_ativo'] ?? '1') === '1';
                $preloaderPortal = ($settings['preloader_portal_ativo'] ?? '1') === '1';
                $preloaderEstilo = $settings['preloader_estilo'] ?? 'radar';
                $preloaderFundo = $settings['preloader_fundo'] ?? 'radial_dark';
                $preloaderTitulo = $settings['preloader_titulo'] ?? ($golden['marca']['nome'] ?? 'Golden Rastreamento');
                $preloaderSubtitulo = $settings['preloader_subtitulo'] ?? 'Proteção e monitoramento 24h';
                $preloaderCor = $settings['preloader_cor'] ?? '#d4a017';
                $preloaderCorSecundaria = $settings['preloader_cor_secundaria'] ?? '#f59e0b';
                $preloaderIconeTipo = $settings['preloader_icone_tipo'] ?? 'icone';
                $preloaderIcone = $settings['preloader_icone'] ?? 'fa-location-crosshairs';
                $preloaderBadgeAtivo = ($settings['preloader_badge_ativo'] ?? '1') === '1';
                $preloaderBadgeTexto = $settings['preloader_badge_texto'] ?? 'Sinal GPS Ativo • 24h';
                $preloaderBarraAtiva = ($settings['preloader_barra_ativa'] ?? '1') === '1';
                $preloaderBarraEstilo = $settings['preloader_barra_estilo'] ?? 'shimmer';
                $preloaderVelocidade = $settings['preloader_velocidade'] ?? 'normal';
                $preloaderTempoSaida = $settings['preloader_tempo_saida'] ?? 'suave';
                $preloaderTimeoutMax = (int) ($settings['preloader_timeout_max'] ?? 2400);
            @endphp

            <div class="row g-4">
                {{-- Coluna 1: Formulário Cirúrgico de Configuração --}}
                <div class="col-xl-7">
                    <form method="POST" action="{{ route('admin.settings.update') }}" id="formPreloaderConfig" data-atualiza=".app-content">
                        @csrf
                        <input type="hidden" name="_aba" value="preloader">

                        {{-- Bloco 1: Áreas de Ativação --}}
                        <x-admin.card title="Onde o Preloader Atua" icon="fa-toggle-on" ajuda="preloader_config" class="mb-3"
                            descricao="Controle cirúrgico e independente de exibição para visitantes, administradores e clientes.">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                                        <div class="form-check form-switch mb-1 d-flex align-items-center gap-2">
                                            <input class="form-check-input" type="checkbox" role="switch" id="preloader_site_ativo" name="preloader_site_ativo" value="1" @checked($preloaderSite)>
                                            <label class="form-check-label fw-semibold" for="preloader_site_ativo">Site Público</label>
                                            <x-admin.ajuda chave="preloader_site_ativo" />
                                        </div>
                                        <small class="text-secondary d-block">Páginas públicas e vitrine da loja.</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                                        <div class="form-check form-switch mb-1 d-flex align-items-center gap-2">
                                            <input class="form-check-input" type="checkbox" role="switch" id="preloader_admin_ativo" name="preloader_admin_ativo" value="1" @checked($preloaderAdmin)>
                                            <label class="form-check-label fw-semibold" for="preloader_admin_ativo">Painel Admin</label>
                                            <x-admin.ajuda chave="preloader_admin_ativo" />
                                        </div>
                                        <small class="text-secondary d-block">Abertura e troca de módulos administrativos.</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                                        <div class="form-check form-switch mb-1 d-flex align-items-center gap-2">
                                            <input class="form-check-input" type="checkbox" role="switch" id="preloader_portal_ativo" name="preloader_portal_ativo" value="1" @checked($preloaderPortal)>
                                            <label class="form-check-label fw-semibold" for="preloader_portal_ativo">Portal do Cliente</label>
                                        </div>
                                        <small class="text-secondary d-block">Acesso de clientes a faturas e serviços.</small>
                                    </div>
                                </div>
                            </div>
                        </x-admin.card>

                        {{-- Bloco 2: Estilo Visual & Atmosfera da Animação --}}
                        <x-admin.card title="Estilo Visual & Atmosfera da Animação" icon="fa-wand-magic-sparkles" class="mb-3"
                            descricao="Escolha entre 7 temas visuais modernos desenhados para rastreamento, alta tecnologia e segurança militar.">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="preloader_estilo">Estilo da Animação <x-admin.ajuda chave="preloader_estilo" /></label>
                                    <select class="form-select" id="preloader_estilo" name="preloader_estilo">
                                        <option value="radar" @selected($preloaderEstilo === 'radar')>📡 Radar GPS Tático (Varredura 360° & Sonar)</option>
                                        <option value="orbita" @selected($preloaderEstilo === 'orbita')>🪐 Órbita Dupla Cósmica (Anéis 3D Concêntricos)</option>
                                        <option value="satelite" @selected($preloaderEstilo === 'satelite')>🛰️ Satélite & Varredura Orbital (Laser de Busca)</option>
                                        <option value="telemetria" @selected($preloaderEstilo === 'telemetria')>🎯 HUD Militar & Telemetria Tática (Mira Digital)</option>
                                        <option value="circuito" @selected($preloaderEstilo === 'circuito')>⚡ Circuito Cibernético Neon (Hexágono Laser)</option>
                                        <option value="fluxo" @selected($preloaderEstilo === 'fluxo')>💎 Fluxo Holográfico 3D (Diamante Geométrico)</option>
                                        <option value="pulso" @selected($preloaderEstilo === 'pulso')>✨ Pulso Minimalista & Halo Respiratório (Discreto)</option>
                                    </select>
                                    <small class="text-secondary d-block mt-1">O preview ao lado atualiza imediatamente ao alterar.</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="preloader_fundo">Tema de Fundo & Atmosfera</label>
                                    <select class="form-select" id="preloader_fundo" name="preloader_fundo">
                                        <option value="radial_dark" @selected($preloaderFundo === 'radial_dark')>Escuro Profundo (Degradê Radial Noturno)</option>
                                        <option value="oled_black" @selected($preloaderFundo === 'oled_black')>Preto Absoluto OLED (#000000 - Contraste Máximo)</option>
                                        <option value="glass_blur" @selected($preloaderFundo === 'glass_blur')>Vidro Fosco Translúcido (Glassmorphism Blur)</option>
                                        <option value="aurora_gold" @selected($preloaderFundo === 'aurora_gold')>Aurora Dourada (Nébula Ouro & Âmbar)</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="preloader_cor">Cor Principal de Destaque <x-admin.ajuda chave="preloader_cor" /></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="color" class="form-control form-control-color" id="preloader_cor" name="preloader_cor" value="{{ $preloaderCor }}">
                                        <input type="text" class="form-control form-control-sm font-monospace" id="preloader_cor_hex" value="{{ $preloaderCor }}" maxlength="7">
                                    </div>
                                    <div class="d-flex gap-1 mt-1 flex-wrap">
                                        <button type="button" class="badge border-0 p-1 btn-paleta" style="background:#d4a017;color:#000" data-cor="#d4a017">Golden</button>
                                        <button type="button" class="badge border-0 p-1 btn-paleta" style="background:#f59e0b;color:#000" data-cor="#f59e0b">Âmbar</button>
                                        <button type="button" class="badge border-0 p-1 btn-paleta" style="background:#06b6d4;color:#000" data-cor="#06b6d4">Ciano</button>
                                        <button type="button" class="badge border-0 p-1 btn-paleta" style="background:#10b981;color:#000" data-cor="#10b981">Esmeralda</button>
                                        <button type="button" class="badge border-0 p-1 btn-paleta" style="background:#ef4444;color:#fff" data-cor="#ef4444">Rubi</button>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="preloader_cor_secundaria">Cor Secundária (Contraste e Anéis)</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="color" class="form-control form-control-color" id="preloader_cor_secundaria" name="preloader_cor_secundaria" value="{{ $preloaderCorSecundaria }}">
                                        <input type="text" class="form-control form-control-sm font-monospace" id="preloader_cor_sec_hex" value="{{ $preloaderCorSecundaria }}" maxlength="7">
                                    </div>
                                    <small class="text-secondary d-block mt-1">Usada para anéis concêntricos, esferas orbitais e degradês.</small>
                                </div>
                            </div>
                        </x-admin.card>

                        {{-- Bloco 3: Elemento Central (Ícone ou Logo da Marca) --}}
                        <x-admin.card title="Coração do Preloader (Ícone ou Logo)" icon="fa-icons" class="mb-3"
                            descricao="Defina se o centro exibe um ícone vetorial de alta definição ou a logomarca oficial da empresa.">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="preloader_icone_tipo" id="tipo_icone" value="icone" @checked($preloaderIconeTipo === 'icone')>
                                        <label class="btn btn-outline-secondary" for="tipo_icone"><i class="fas fa-icons me-1"></i> Ícone Vetorial FontAwesome</label>

                                        <input type="radio" class="btn-check" name="preloader_icone_tipo" id="tipo_logo" value="logo" @checked($preloaderIconeTipo === 'logo')>
                                        <label class="btn btn-outline-secondary" for="tipo_logo"><i class="fas fa-shield-alt me-1"></i> Logomarca Oficial Golden</label>
                                    </div>
                                </div>

                                <div class="col-12" id="boxSeletorIcone">
                                    <label class="form-label fw-semibold" for="preloader_icone">Ícone Central</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas {{ $preloaderIcone }}" id="iconePreviaCentral"></i></span>
                                        <input type="text" class="form-control" id="preloader_icone" name="preloader_icone" value="{{ $preloaderIcone }}" placeholder="fa-location-crosshairs">
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        <span class="small text-secondary me-2">Atalhos rápidos:</span>
                                        @foreach(['fa-location-crosshairs' => 'Mira GPS', 'fa-satellite' => 'Satélite', 'fa-satellite-dish' => 'Antena', 'fa-shield-halved' => 'Escudo', 'fa-car-side' => 'Veículo', 'fa-truck-fast' => 'Caminhão', 'fa-compass' => 'Bússola', 'fa-radar' => 'Radar', 'fa-bolt' => 'Raio'] as $icoClass => $icoNome)
                                            <button type="button" class="btn btn-xs btn-outline-secondary btn-atalho-icone" data-icone="{{ $icoClass }}">
                                                <i class="fas {{ $icoClass }} me-1"></i> {{ $icoNome }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </x-admin.card>

                        {{-- Bloco 4: Textos, Telemetria & Barra de Progresso --}}
                        <x-admin.card title="Textos, Telemetria & Barra" icon="fa-font" class="mb-3"
                            descricao="Ajuste os textos exibidos, a etiqueta de status em tempo real e a barra de progresso.">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="preloader_titulo">Título Principal <x-admin.ajuda chave="preloader_titulo" /></label>
                                    <input type="text" class="form-control" id="preloader_titulo" name="preloader_titulo" value="{{ $preloaderTitulo }}" maxlength="80">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="preloader_subtitulo">Subtítulo Explicativo</label>
                                    <input type="text" class="form-control" id="preloader_subtitulo" name="preloader_subtitulo" value="{{ $preloaderSubtitulo }}" maxlength="120" placeholder="Proteção e monitoramento 24h">
                                </div>

                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                                        <div class="form-check form-switch mb-1 d-flex align-items-center gap-2">
                                            <input class="form-check-input" type="checkbox" role="switch" id="preloader_badge_ativo" name="preloader_badge_ativo" value="1" @checked($preloaderBadgeAtivo)>
                                            <label class="form-check-label fw-semibold" for="preloader_badge_ativo">Badge de Status / Telemetria</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm mt-2" id="preloader_badge_texto" name="preloader_badge_texto" value="{{ $preloaderBadgeTexto }}" maxlength="60" placeholder="Sinal GPS Ativo • 24h">
                                        <small class="text-secondary d-block mt-1">Exibe pílula com ponto verde pulsante de status operacional.</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                                        <div class="form-check form-switch mb-1 d-flex align-items-center gap-2">
                                            <input class="form-check-input" type="checkbox" role="switch" id="preloader_barra_ativa" name="preloader_barra_ativa" value="1" @checked($preloaderBarraAtiva)>
                                            <label class="form-check-label fw-semibold" for="preloader_barra_ativa">Barra de Progresso</label>
                                        </div>
                                        <select class="form-select form-select-sm mt-2" id="preloader_barra_estilo" name="preloader_barra_estilo">
                                            <option value="shimmer" @selected($preloaderBarraEstilo === 'shimmer')>Brilho Deslizante (Shimmer)</option>
                                            <option value="pulso" @selected($preloaderBarraEstilo === 'pulso')>Pulsação Viva de Preenchimento</option>
                                            <option value="laser" @selected($preloaderBarraEstilo === 'laser')>Laser Tático Alta Frequência</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </x-admin.card>

                        {{-- Bloco 5: Dinâmica Temporal & Transição --}}
                        <x-admin.card title="Velocidade, Saída & Tempo Limite" icon="fa-gauge-high" class="mb-3"
                            descricao="Ajuste o ritmo das animações, a suavidade do desaparecimento e o tempo limite de segurança.">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="preloader_velocidade">Ritmo da Animação</label>
                                    <select class="form-select" id="preloader_velocidade" name="preloader_velocidade">
                                        <option value="rapida" @selected($preloaderVelocidade === 'rapida')>⚡ Rápida e Ágil (0.7s)</option>
                                        <option value="normal" @selected($preloaderVelocidade === 'normal')>⚖️ Equilibrada (Padrão 1.0s)</option>
                                        <option value="suave" @selected($preloaderVelocidade === 'suave')>🌊 Suave e Fluida (1.5s)</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="preloader_tempo_saida">Efeito de Desaparecimento</label>
                                    <select class="form-select" id="preloader_tempo_saida" name="preloader_tempo_saida">
                                        <option value="suave" @selected($preloaderTempoSaida === 'suave')>Fade Suave com Leve Zoom (420ms)</option>
                                        <option value="cinema" @selected($preloaderTempoSaida === 'cinema')>Dissolve Cinematográfico (700ms)</option>
                                        <option value="instantaneo" @selected($preloaderTempoSaida === 'instantaneo')>Instantâneo / Ultrarrápido (180ms)</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="preloader_timeout_max">Timeout Máximo de Segurança</label>
                                    <select class="form-select" id="preloader_timeout_max" name="preloader_timeout_max">
                                        <option value="1800" @selected($preloaderTimeoutMax == 1800)>1.8 segundos (Super Rápido)</option>
                                        <option value="2400" @selected($preloaderTimeoutMax == 2400)>2.4 segundos (Recomendado)</option>
                                        <option value="3500" @selected($preloaderTimeoutMax == 3500)>3.5 segundos (Conexões instáveis)</option>
                                        <option value="5000" @selected($preloaderTimeoutMax == 5000)>5.0 segundos (Máximo)</option>
                                    </select>
                                </div>
                            </div>
                        </x-admin.card>

                        <div class="d-flex flex-wrap gap-2 align-items-center pt-2">
                            <button type="submit" class="btn btn-golden btn-lg px-4 shadow-sm">
                                <i class="fas fa-save me-1" aria-hidden="true"></i> Salvar configurações do preloader
                            </button>
                            <button type="button" class="btn btn-outline-warning" id="btnTestarPreloaderTelaCheia">
                                <i class="fas fa-expand me-1"></i> ⚡ Testar em Tela Cheia (3s)
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Coluna 2: Simulador em Tempo Real (Live Preview) --}}
                <div class="col-xl-5">
                    <div class="sticky-top" style="top: 80px; z-index: 10;">
                        <x-admin.card title="Simulador Ao Vivo (Live Preview)" icon="fa-desktop"
                            descricao="Veja as alterações visuais em tempo real antes de salvar.">
                            
                            {{-- Moldura do Dispositivo / Viewport --}}
                            <div class="border rounded-4 overflow-hidden shadow-lg position-relative" style="background:#0b0d12; min-height: 440px;">
                                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom border-dark bg-dark text-white-50 small">
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="rounded-circle bg-danger d-inline-block" style="width:10px;height:10px;"></span>
                                        <span class="rounded-circle bg-warning d-inline-block" style="width:10px;height:10px;"></span>
                                        <span class="rounded-circle bg-success d-inline-block" style="width:10px;height:10px;"></span>
                                    </div>
                                    <span class="small font-monospace text-secondary" id="previewLabelEstilo">Radar GPS Tático</span>
                                    <span class="badge bg-secondary" id="previewBadgeRes">Ao vivo</span>
                                </div>

                                {{-- Palco da Simulação --}}
                                <div id="gpPreviewContainer" class="golden-preloader golden-preloader--{{ $preloaderEstilo }} golden-preloader--fundo-{{ $preloaderFundo }} golden-preloader--vel-{{ $preloaderVelocidade }}"
                                    style="position: relative; height: 380px; inset: auto; --gp-accent: {{ $preloaderCor }}; --gp-accent-secondary: {{ $preloaderCorSecundaria }};">
                                    <div class="golden-preloader__inner">
                                        <div class="golden-preloader__stage" id="previewStage" aria-hidden="true">
                                            {{-- Elementos injetados dinamicamente via JS --}}
                                        </div>

                                        <div class="golden-preloader__title" id="previewTitulo">{{ $preloaderTitulo }}</div>
                                        <div class="golden-preloader__subtitle" id="previewSubtitulo">{{ $preloaderSubtitulo }}</div>

                                        <div class="golden-preloader__badge" id="previewBadge" style="{{ $preloaderBadgeAtivo ? '' : 'display:none !important;' }}">
                                            <span class="golden-preloader__badge-dot"></span>
                                            <span id="previewBadgeTexto">{{ $preloaderBadgeTexto }}</span>
                                        </div>

                                        <div class="golden-preloader__bar golden-preloader__bar--{{ $preloaderBarraEstilo }}" id="previewBarra" style="{{ $preloaderBarraAtiva ? '' : 'display:none !important;' }}">
                                            <div class="golden-preloader__bar-fill"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                <small class="text-secondary">
                                    <i class="fas fa-check-circle text-success me-1"></i> Renderizado em CSS nativo 60 FPS
                                </small>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnReiniciarPreview">
                                    <i class="fas fa-rotate me-1"></i> Reiniciar Animação
                                </button>
                            </div>
                        </x-admin.card>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Script de Sincronização em Tempo Real (Live Preview) --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var container = document.getElementById('gpPreviewContainer');
    if (!container) return;

    var elEstilo = document.getElementById('preloader_estilo');
    var elFundo = document.getElementById('preloader_fundo');
    var elCor = document.getElementById('preloader_cor');
    var elCorHex = document.getElementById('preloader_cor_hex');
    var elCorSec = document.getElementById('preloader_cor_secundaria');
    var elCorSecHex = document.getElementById('preloader_cor_sec_hex');
    var elTitulo = document.getElementById('preloader_titulo');
    var elSubtitulo = document.getElementById('preloader_subtitulo');
    var elIcone = document.getElementById('preloader_icone');
    var elBadgeAtivo = document.getElementById('preloader_badge_ativo');
    var elBadgeTexto = document.getElementById('preloader_badge_texto');
    var elBarraAtiva = document.getElementById('preloader_barra_ativa');
    var elBarraEstilo = document.getElementById('preloader_barra_estilo');
    var elVelocidade = document.getElementById('preloader_velocidade');

    var previewStage = document.getElementById('previewStage');
    var previewTitulo = document.getElementById('previewTitulo');
    var previewSubtitulo = document.getElementById('previewSubtitulo');
    var previewBadge = document.getElementById('previewBadge');
    var previewBadgeTexto = document.getElementById('previewBadgeTexto');
    var previewBarra = document.getElementById('previewBarra');
    var previewLabelEstilo = document.getElementById('previewLabelEstilo');

    function obterTipoIcone() {
        var rad = document.querySelector('input[name="preloader_icone_tipo"]:checked');
        return rad ? rad.value : 'icone';
    }

    function renderizarStage() {
        var estilo = elEstilo.value;
        var tipo = obterTipoIcone();
        var icone = elIcone.value || 'fa-location-crosshairs';
        var html = '';

        if (estilo === 'radar') {
            html += '<span class="gp-ring gp-ring--1"></span><span class="gp-ring gp-ring--2"></span><span class="gp-ring gp-ring--3"></span><span class="gp-sweep"></span><span class="gp-blip gp-blip--1"></span><span class="gp-blip gp-blip--2"></span>';
        } else if (estilo === 'orbita') {
            html += '<span class="gp-orbit"><span class="gp-satellite-node"></span></span><span class="gp-orbit gp-orbit--inner"><span class="gp-satellite-node"></span></span>';
        } else if (estilo === 'pulso') {
            html += '<span class="gp-halo"></span><span class="gp-halo gp-halo--outer"></span>';
        } else if (estilo === 'satelite') {
            html += '<span class="gp-sat-track"><span class="gp-sat-dish"><i class="fas fa-satellite"></i></span></span><span class="gp-sat-beam"></span>';
        } else if (estilo === 'telemetria') {
            html += '<span class="gp-hud-bracket gp-hud-bracket--tl"></span><span class="gp-hud-bracket gp-hud-bracket--tr"></span><span class="gp-hud-bracket gp-hud-bracket--bl"></span><span class="gp-hud-bracket gp-hud-bracket--br"></span><span class="gp-reticle"></span><span class="gp-crosshair-h"></span><span class="gp-crosshair-v"></span>';
        } else if (estilo === 'circuito') {
            html += '<span class="gp-circuit-ring"></span><span class="gp-circuit-ring gp-circuit-ring--outer"></span>';
        } else if (estilo === 'fluxo') {
            html += '<span class="gp-diamond"></span><span class="gp-diamond gp-diamond--inner"></span>';
        }

        html += '<div class="golden-preloader__core">';
        if (tipo === 'logo') {
            html += '<i class="fas fa-shield-alt text-dark fs-3"></i>';
        } else {
            html += '<i class="fas ' + icone + '" aria-hidden="true"></i>';
        }
        html += '</div>';

        previewStage.innerHTML = html;
        if (previewLabelEstilo && elEstilo.options[elEstilo.selectedIndex]) {
            previewLabelEstilo.textContent = elEstilo.options[elEstilo.selectedIndex].text.replace(/^.*?\s/, '');
        }
    }

    function sincronizar() {
        container.className = 'golden-preloader golden-preloader--' + elEstilo.value +
                              ' golden-preloader--fundo-' + elFundo.value +
                              ' golden-preloader--vel-' + elVelocidade.value;

        container.style.setProperty('--gp-accent', elCor.value);
        container.style.setProperty('--gp-accent-secondary', elCorSec.value);

        if (elCorHex) elCorHex.value = elCor.value;
        if (elCorSecHex) elCorSecHex.value = elCorSec.value;

        previewTitulo.textContent = elTitulo.value;
        previewSubtitulo.textContent = elSubtitulo.value;

        if (elBadgeAtivo.checked && elBadgeTexto.value.trim() !== '') {
            previewBadge.style.display = 'inline-flex';
            previewBadgeTexto.textContent = elBadgeTexto.value;
        } else {
            previewBadge.style.display = 'none';
        }

        if (elBarraAtiva.checked) {
            previewBarra.style.display = 'block';
            previewBarra.className = 'golden-preloader__bar golden-preloader__bar--' + elBarraEstilo.value;
        } else {
            previewBarra.style.display = 'none';
        }

        var iconePrevia = document.getElementById('iconePreviaCentral');
        if (iconePrevia) iconePrevia.className = 'fas ' + (elIcone.value || 'fa-location-crosshairs');

        renderizarStage();
    }

    [elEstilo, elFundo, elCor, elCorSec, elTitulo, elSubtitulo, elIcone, elBadgeAtivo, elBadgeTexto, elBarraAtiva, elBarraEstilo, elVelocidade].forEach(function (el) {
        if (el) {
            el.addEventListener('input', sincronizar);
            el.addEventListener('change', sincronizar);
        }
    });

    document.querySelectorAll('input[name="preloader_icone_tipo"]').forEach(function (rad) {
        rad.addEventListener('change', function () {
            var box = document.getElementById('boxSeletorIcone');
            if (box) box.style.display = this.value === 'logo' ? 'none' : 'block';
            sincronizar();
        });
    });

    document.querySelectorAll('.btn-paleta').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var cor = this.getAttribute('data-cor');
            if (cor && elCor) {
                elCor.value = cor;
                sincronizar();
            }
        });
    });

    document.querySelectorAll('.btn-atalho-icone').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ico = this.getAttribute('data-icone');
            if (ico && elIcone) {
                elIcone.value = ico;
                sincronizar();
            }
        });
    });

    if (elCorHex) {
        elCorHex.addEventListener('input', function () {
            if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
                elCor.value = this.value;
                sincronizar();
            }
        });
    }
    if (elCorSecHex) {
        elCorSecHex.addEventListener('input', function () {
            if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
                elCorSec.value = this.value;
                sincronizar();
            }
        });
    }

    var btnReiniciar = document.getElementById('btnReiniciarPreview');
    if (btnReiniciar) {
        btnReiniciar.addEventListener('click', function () {
            container.style.opacity = '0';
            setTimeout(function () {
                renderizarStage();
                container.style.opacity = '1';
            }, 120);
        });
    }

    var btnTestar = document.getElementById('btnTestarPreloaderTelaCheia');
    if (btnTestar) {
        btnTestar.addEventListener('click', function () {
            var existing = document.getElementById('gp-fullscreen-test');
            if (existing) existing.remove();

            var fs = container.cloneNode(true);
            fs.id = 'gp-fullscreen-test';
            fs.style.position = 'fixed';
            fs.style.inset = '0';
            fs.style.height = '100vh';
            fs.style.zIndex = '999999';
            fs.style.opacity = '0';
            fs.style.transition = 'opacity 0.3s ease';

            document.body.appendChild(fs);
            requestAnimationFrame(function () {
                fs.style.opacity = '1';
            });

            setTimeout(function () {
                fs.classList.add('is-hidden');
                setTimeout(function () {
                    fs.remove();
                }, 500);
            }, 3000);
        });
    }

    // Inicializa preview
    sincronizar();
});
</script>
@endpush
@endsection
