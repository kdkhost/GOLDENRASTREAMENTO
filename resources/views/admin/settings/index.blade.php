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
                        <x-admin.card title="Contato oficial" icon="fa-id-card" descricao="É o que aparece no rodapé, no formulário do site e nos e-mails.">
                            <div class="row g-3">
                                <x-admin.field name="site_nome" label="Nome do site" :value="$settings['site_nome'] ?? 'Golden Rastreamento'" col="col-12 col-md-6" :required="true" maxlength="120" />
                                <x-admin.field name="contato_whatsapp" label="WhatsApp oficial" :value="$settings['contato_whatsapp'] ?? ''" col="col-12 col-md-6" :required="true" maxlength="32" placeholder="5519997101483" hint="DDI + DDD + número, só dígitos." />
                                <x-admin.field name="contato_telefone" label="Telefones extras" :value="$settings['contato_telefone'] ?? ''" maxlength="60" :opcional="true" placeholder="(19) 99710-1483 · (19) 97139-5129" />
                                <x-admin.field name="contato_email" type="email" label="E-mail comercial" :value="$settings['contato_email'] ?? ''" maxlength="160" :opcional="true" autocomplete="off" />
                            </div>
                        </x-admin.card>
                    </div>
                    <div class="col-xl-5">
                        <x-admin.card title="Endereço da empresa" icon="fa-location-dot">
                            <div class="row g-3">
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
                <div class="col-xl-4">
                    <x-admin.card title="Loja virtual" icon="fa-store" ajuda="loja_ligar" :descricao="$lojaAtiva ? 'Ativa — as páginas /loja estão no ar e no menu do site.' : 'Desativada — /loja sai do ar e some do menu.'">
                        <x-slot:actions>
                            <x-admin.status :rotulo="$lojaAtiva ? 'ativa' : 'desativada'" :tom="tom_por_flag($lojaAtiva)" />
                        </x-slot:actions>
                        <form method="POST" action="{{ route('admin.settings.loja') }}" data-atualiza=".app-content">
                            @csrf
                            <input type="hidden" name="ativa" value="{{ $lojaAtiva ? '0' : '1' }}">
                            <button class="btn w-100 {{ $lojaAtiva ? 'btn-outline-danger' : 'btn-success' }}">
                                <i class="fas fa-power-off me-1"></i> {{ $lojaAtiva ? 'Desativar loja agora' : 'Ativar loja agora' }}
                            </button>
                        </form>
                        <p class="form-hint mt-3 mb-0">Desligar tira a loja do site na hora; os pedidos já feitos continuam no painel.</p>
                    </x-admin.card>

                    <x-admin.card title="Atalhos e Links Rápidos" icon="fa-link" class="mt-3">
                        <ul class="list-unstyled mb-0 small">
                            <li class="mb-2">
                                <a href="{{ route('site.rastreio.index') }}" target="_blank" rel="noopener" class="text-decoration-none">
                                    <i class="fas fa-barcode text-gold me-1"></i> Página de Rastreamento Público
                                </a>
                            </li>
                            <li class="mb-2">
                                <a href="{{ route('admin.pedidos.index') }}" class="text-decoration-none">
                                    <i class="fas fa-box-archive text-gold me-1"></i> Gestão de Pedidos e Despacho
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('site.loja.index') }}" target="_blank" rel="noopener" class="text-decoration-none">
                                    <i class="fas fa-cart-shopping text-gold me-1"></i> Vitrine da Loja Virtual
                                </a>
                            </li>
                        </ul>
                    </x-admin.card>
                </div>

                <div class="col-xl-8">
                    <form method="POST" action="{{ route('admin.settings.loja-regras') }}" class="row g-3" data-atualiza=".app-content">
                        @csrf
                        <div class="col-12">
                            <x-admin.card title="Regras Comerciais & Pagamento" icon="fa-sliders" descricao="Frete base, retirada e dados de Pix.">
                                <div class="row g-3">
                                    <x-admin.switch name="loja_livre" label="Frete combinado / a combinar (não cobra valor fixo)" :checked="$livre" />
                                    <x-admin.switch name="loja_retirada" label="Permitir retirada na empresa Golden Rastreamento" :checked="$retirada" />
                                    <x-admin.field name="loja_frete" type="number" label="Frete fixo padrão (R$)" :value="$paraExibir($settings['loja_frete_cents'] ?? 0)" col="col-md-6" step="0.01" min="0" max="99999" placeholder="0,00" :travado="$livre" />
                                    <x-admin.field name="loja_frete_gratis_acima" type="number" label="Frete grátis acima de (R$)" :value="$paraExibir($settings['loja_frete_gratis_acima_cents'] ?? 0)" col="col-md-6" step="0.01" min="0" max="99999" placeholder="ex.: 997,00" :travado="$livre" />
                                    <x-admin.field name="loja_pix_chave" label="Chave Pix exibida no checkout" :value="$settings['loja_pix_chave'] ?? ''" maxlength="120" :opcional="true" col="col-md-6" placeholder="CPF, CNPJ, e-mail ou aleatória" />
                                    <x-admin.field name="loja_email_equipe" type="email" label="E-mail da equipe para alertas" :value="$settings['loja_email_equipe'] ?? ''" maxlength="160" :opcional="true" col="col-md-6" hint="Recebe alerta de cada venda nova." placeholder="vendas@goldenrastreamento.com.br" />
                                </div>
                            </x-admin.card>
                        </div>

                        <div class="col-12">
                            <x-admin.card title="Logística Correios (Com e Sem Contrato & Coleta)" icon="fa-truck-fast" ajuda="correios_logistica"
                                descricao="Cálculo oficial de frete balcão ou com contrato corporativo, coleta na empresa e rastreamento em tempo real.">
                                <div class="row g-3">
                                    <x-admin.switch name="correios_ativo" label="Ativar cálculo dinâmico de frete Correios no checkout e carrinho" :checked="$correiosAtivo" />
                                    
                                    <div class="col-md-6">
                                        <x-admin.switch name="correios_possui_contrato" label="Empresa possui contrato com os Correios (tarifas reduzidas)" :checked="$correiosContrato" />
                                        <div class="form-hint">Aplica desconto contratual nas tarifas e habilita credenciais CWS/SIGEP.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <x-admin.switch name="correios_coleta_empresa" label="Coleta na empresa (carteiro/veículo retira na Golden)" :checked="$correiosColeta" />
                                        <div class="form-hint">Indica no painel e nos pedidos que as encomendas aguardam coleta na sede da Golden.</div>
                                    </div>

                                    <x-admin.field name="correios_cep_origem" label="CEP de Origem / Expedição" :value="$settings['correios_cep_origem'] ?? '35500-000'" col="col-md-4" placeholder="35500-000" hint="CEP de saída das mercadorias" />
                                    <x-admin.field name="correios_prazo_adicional" type="number" label="Prazo adicional (dias úteis)" :value="$settings['correios_prazo_adicional'] ?? 1" col="col-md-4" min="0" max="30" hint="Tempo de preparação/testes" />
                                    <x-admin.field name="correios_taxa_manuseio" type="number" step="0.01" label="Taxa manuseio / embalagem (R$)" :value="$settings['correios_taxa_manuseio'] ?? '0.00'" col="col-md-4" min="0" max="999" placeholder="0.00" hint="Adicionado ao frete dos Correios" />

                                    <div class="col-12 pt-2 border-top">
                                        <h6 class="small fw-bold text-uppercase text-secondary mb-2"><i class="fas fa-boxes-packing me-1"></i> Modalidades dos Correios Habilitadas</h6>
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
                                        <h6 class="small fw-bold text-uppercase text-secondary mb-1"><i class="fas fa-file-contract me-1"></i> Credenciais do Contrato Correios (Opcional / CWS)</h6>
                                        <p class="form-hint mb-3">Preencha caso possua contrato formal com os Correios para autenticação na API CWS / SIGEP Web.</p>
                                        <div class="row g-3">
                                            <x-admin.field name="correios_codigo_administrativo" label="Código Administrativo" :value="$settings['correios_codigo_administrativo'] ?? ''" col="col-md-6" :opcional="true" placeholder="Ex.: 08000000" />
                                            <x-admin.field name="correios_cartao_postagem" label="Cartão de Postagem" :value="$settings['correios_cartao_postagem'] ?? ''" col="col-md-6" :opcional="true" placeholder="Ex.: 0057018901" />
                                            <x-admin.field name="correios_usuario" label="Usuário CWS / SIGEP" :value="$settings['correios_usuario'] ?? ''" col="col-md-6" :opcional="true" placeholder="Ex.: goldenrastreamento" />
                                            <x-admin.field name="correios_senha" type="password" label="Senha / Token CWS" :value="$settings['correios_senha'] ?? ''" col="col-md-6" :opcional="true" placeholder="••••••••" />
                                            <x-admin.field name="correios_instrucoes_coleta" type="textarea" label="Instruções de Coleta na Empresa" :value="$settings['correios_instrucoes_coleta'] ?? ''" rows="2" col="col-12" :opcional="true" hint="Ex.: Coleta diária às 15h na sede da Golden Rastreamento. Contato da expedição: (37) 99999-9999." />
                                        </div>
                                    </div>
                                </div>
                            </x-admin.card>
                        </div>

                        <div class="col-12">
                            <x-admin.card title="Mercado Livre / Mercado Envios" icon="fa-bag-shopping" ajuda="mercado_livre_card"
                                descricao="Opção alternativa de envio caso o administrador opte por intermediar a logística via Mercado Livre.">
                                <div class="row g-3">
                                    <x-admin.switch name="mercado_livre_envio_ativo" label="Habilitar opção de envio pelo Mercado Livre (Mercado Envios)" :checked="$mercadoLivreAtivo" />
                                    <x-admin.field name="mercado_livre_link_loja" label="Link da Loja / Anúncio Oficial no Mercado Livre" :value="$settings['mercado_livre_link_loja'] ?? ''" col="col-12" :opcional="true" placeholder="https://www.mercadolivre.com.br/loja/golden-rastreamento" />
                                    <x-admin.field name="mercado_livre_instrucoes" type="textarea" label="Instruções e orientações do Mercado Envios" :value="$settings['mercado_livre_instrucoes'] ?? ''" rows="2" col="col-12" :opcional="true" placeholder="Ex.: O envio é gerenciado pela plataforma do Mercado Livre com código de rastreamento Mercado Envios." />
                                </div>
                            </x-admin.card>
                        </div>

                        <div class="col-12">
                            <button class="btn btn-golden"><i class="fas fa-save me-1"></i> Salvar todas as regras e configurações de logística</button>
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
                        <x-admin.card title="Medição e consentimento" icon="fa-chart-area"
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
                    <x-admin.card title="Testar a conexão agora" icon="fa-satellite-dish"
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
                                <x-admin.card title="Testar o disparo agora" icon="fa-paper-plane" class="mt-3"
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
                        </div>
                    @endforeach
                </div>
            </div>

            <x-admin.card title="Ambiente" icon="fa-circle-info" class="mt-3">
                <dl class="defs mb-0">
                    <dt>Banco ativo</dt><dd>{{ config('database.default') }}@if(config('database.default') === 'sqlite') <span class="form-hint">(<code>database/database.sqlite</code>)</span>@endif</dd>
                    <dt>Módulos</dt><dd>CRM (leads, clientes, interações) · CMS (páginas, depoimentos, FAQs) · ERP (produtos, estoque, fornecedores, pedidos)</dd>
                    <dt>Chaves</dt><dd>{{ count(\App\Support\ChavesDeIntegracao::campos()) }} campos, todos editáveis nesta tela: os de acesso ficam aqui, os de veiculação do financeiro na aba Financeiro (WHMCS). O <code>.env</code> deste servidor só conduz o que o banco não tem.</dd>
                </dl>
                <p class="small text-secondary mb-0 mt-2">Ficam no <code>.env</code> só o que é deste servidor e não decisão de ninguém: a ligação com o banco (<code>DB_CONNECTION=mysql</code>, <code>php artisan migrate --force</code> — as migrations rodam nos dois bancos), a chave da aplicação e o transporte do Google Ads (<code>GOOGLE_ADS_TRANSPORT</code>, <code>GOOGLE_ADS_CACHE_STORE</code>), que dependem do que a máquina tem.</p>
            </x-admin.card>
        </div>
    </div>
</div>
@endsection
