@extends('admin.layouts.app')
@section('title', 'Configurações')
@section('page_title', 'Configurações do sistema')
@section('page_description', 'Cada tipo de configuração na sua aba: contato, acessos do site, loja, WhatsApp, medição, financeiro (WHMCS) e chaves de integração.')

@php
    $lojaAtiva = ($settings['loja_ativa'] ?? '1') === '1';
    $livre = ($settings['loja_livre'] ?? '0') === '1';
    $retirada = ($settings['loja_retirada'] ?? '0') === '1';
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
            <form method="POST" action="{{ route('admin.settings.update') }}">
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
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                <input type="hidden" name="_aba" value="acessos">
                <div class="row g-3">
                    <div class="col-xl-5">
                        <x-admin.card title="Acessos externos do site" icon="fa-arrow-up-right-from-square"
                            descricao="Para onde levam o submenu da conta e o botão do topo do site, com o nome e o ícone de cada servidor. O portal do cliente é rota interna e não aparece aqui.">
                            <div class="row g-3">
                                <x-admin.field name="acesso_financeiro_url" type="url" label="Financeiro antigo" :value="$settings['acesso_financeiro_url'] ?? config('golden.acessos.financeiro')" maxlength="255" :opcional="true" placeholder="https://financeiro.exemplo.com.br/login" hint="Abre em outra aba, pelo submenu do ícone de login." />
                                <x-admin.field name="acesso_servidor_1_url" type="url" label="Endereço do servidor 1" :value="$settings['acesso_servidor_1_url'] ?? config('golden.acessos.servidor_1')" col="col-12 col-md-6" maxlength="255" :opcional="true" placeholder="https://app1.exemplo.com.br" />
                                <x-admin.field name="acesso_servidor_2_url" type="url" label="Endereço do servidor 2" :value="$settings['acesso_servidor_2_url'] ?? config('golden.acessos.servidor_2')" col="col-12 col-md-6" maxlength="255" :opcional="true" placeholder="http://203.0.113.10:8082/" />
                                <x-admin.field name="acesso_servidor_1_titulo" label="Nome do servidor 1" :value="$settings['acesso_servidor_1_titulo'] ?? $textosAcesso['servidor_1_titulo']" col="col-12 col-md-6" maxlength="60" :opcional="true" placeholder="Servidor 1" hint="É o que o cliente lê no modal — o endereço fica só no link." />
                                <x-admin.field name="acesso_servidor_2_titulo" label="Nome do servidor 2" :value="$settings['acesso_servidor_2_titulo'] ?? $textosAcesso['servidor_2_titulo']" col="col-12 col-md-6" maxlength="60" :opcional="true" placeholder="Servidor 2" />
                                <x-admin.field name="acesso_servidor_1_icone" type="select" label="Ícone do servidor 1" :value="$settings['acesso_servidor_1_icone'] ?? $textosAcesso['servidor_1_icone']" :options="$iconesAcesso" col="col-12 col-md-6" empty="Ícone padrão" :opcional="true" />
                                <x-admin.field name="acesso_servidor_2_icone" type="select" label="Ícone do servidor 2" :value="$settings['acesso_servidor_2_icone'] ?? $textosAcesso['servidor_2_icone']" :options="$iconesAcesso" col="col-12 col-md-6" empty="Ícone padrão" :opcional="true" />
                            </div>
                        </x-admin.card>
                    </div>
                    <div class="col-xl-7">
                        <x-admin.card title="Textos dos acessos" icon="fa-pen-nib"
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
                <div class="col-xl-5">
                    <x-admin.card title="Loja virtual" icon="fa-store" :descricao="$lojaAtiva ? 'Ativa — as páginas /loja estão no ar e no menu do site.' : 'Desativada — /loja sai do ar e some do menu.'">
                        <x-slot:actions>
                            <x-admin.status :rotulo="$lojaAtiva ? 'ativa' : 'desativada'" :tom="tom_por_flag($lojaAtiva)" />
                        </x-slot:actions>
                        <form method="POST" action="{{ route('admin.settings.loja') }}">
                            @csrf
                            <input type="hidden" name="ativa" value="{{ $lojaAtiva ? '0' : '1' }}">
                            <button class="btn w-100 {{ $lojaAtiva ? 'btn-outline-danger' : 'btn-success' }}">
                                <i class="fas fa-power-off me-1"></i> {{ $lojaAtiva ? 'Desativar loja agora' : 'Ativar loja agora' }}
                            </button>
                        </form>
                        <p class="form-hint mt-3 mb-0">Desligar tira a loja do site na hora; os pedidos já feitos continuam no painel.</p>
                    </x-admin.card>
                </div>
                <div class="col-xl-7">
                    <x-admin.card title="Regras da loja" icon="fa-truck-fast" descricao="Frete, retirada, Pix e o e-mail que recebe cada venda.">
                        <form method="POST" action="{{ route('admin.settings.loja-regras') }}" class="row g-3">
                            @csrf
                            <x-admin.switch name="loja_livre" label="Frete combinado (não cobra valor fixo)" :checked="$livre" />
                            <x-admin.switch name="loja_retirada" label="Permitir retirada na loja" :checked="$retirada" />
                            <x-admin.field name="loja_frete" type="number" label="Frete fixo (R$)" :value="$paraExibir($settings['loja_frete_cents'] ?? 0)" col="col-6" step="0.01" min="0" max="99999" placeholder="0,00" :disabled="$livre" />
                            <x-admin.field name="loja_frete_gratis_acima" type="number" label="Frete grátis acima de (R$)" :value="$paraExibir($settings['loja_frete_gratis_acima_cents'] ?? 0)" col="col-6" step="0.01" min="0" max="99999" placeholder="ex.: 997,00" :disabled="$livre" />
                            <x-admin.field name="loja_pix_chave" label="Chave Pix exibida no checkout" :value="$settings['loja_pix_chave'] ?? ''" maxlength="120" :opcional="true" placeholder="CPF, CNPJ, e-mail ou aleatória" />
                            <x-admin.field name="loja_email_equipe" type="email" label="E-mail da equipe" :value="$settings['loja_email_equipe'] ?? ''" maxlength="160" :opcional="true" hint="Recebe o alerta de cada venda nova." placeholder="vendas@seudominio.com.br" />
                            <div class="col-12">
                                <button class="btn btn-golden"><i class="fas fa-save me-1"></i> Salvar regras da loja</button>
                            </div>
                        </form>
                    </x-admin.card>
                </div>
            </div>
        </div>

        {{-- 4. A caixa multicanal: textos da caixa e um item por atendente humano. --}}
        <div class="tab-pane fade" id="aba-whatsapp" data-aba-painel="whatsapp" role="tabpanel" aria-labelledby="aba-whatsapp-botao" tabindex="0">
            <x-admin.card title="Botão do WhatsApp no site" icon="fa-brands fa-whatsapp"
                :descricao="$boxAtivo ? 'Abre a central de canais com a mensagem pronta por segmento.' : 'Link direto para o número principal.'">
                <x-slot:actions>
                    <x-admin.status :rotulo="$boxAtivo ? 'caixa multicanal' : 'link direto'" :tom="tom_por_flag($boxAtivo)" />
                </x-slot:actions>
                <form method="POST" action="{{ route('admin.settings.whatsapp') }}" class="row g-3">
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
                                    <x-admin.field name="{{ $chave }}_icone" type="select" label="Ícone" :value="$settings[$chave.'_icone'] ?? ''" :options="config('golden.whatsapp_box.icones')" col="col-12 col-md-6" empty="Ícone padrão do canal" :opcional="true" />
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
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                <input type="hidden" name="_aba" value="medicao">
                <div class="row g-3">
                    <div class="col-xl-8">
                        <x-admin.card title="Medição e consentimento" icon="fa-chart-area"
                            descricao="Só habilite a coleta depois de publicar a Política de Privacidade e o banner de consentimento.">
                            <div class="row g-3">
                                <x-admin.switch name="tracking_enabled" label="Ativar coleta de eventos (GA4, Meta Pixel, GTM)" :checked="($settings['tracking_enabled'] ?? '0') === '1'" />
                                <x-admin.field name="ga_measurement_id" label="GA4 Measurement ID" :value="$settings['ga_measurement_id'] ?? ''" col="col-12 col-md-4" maxlength="60" :opcional="true" placeholder="G-XXXXXXX" />
                                <x-admin.field name="meta_pixel_id" label="Meta Pixel ID" :value="$settings['meta_pixel_id'] ?? ''" col="col-12 col-md-4" maxlength="60" :opcional="true" />
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
                    <x-admin.card title="Estado da conexão" icon="fa-plug-circle-bolt"
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
                        <form method="POST" action="{{ route('admin.settings.whmcs.sondar') }}" class="row g-3">
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
                    <x-admin.card :title="$veiculacao['rotulo']" :icon="$veiculacao['icone']" :descricao="$veiculacao['descricao']">
                        <form method="POST" action="{{ route('admin.settings.chaves') }}" class="row g-3">
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
                            <x-admin.card :title="$grupo['rotulo']" :icon="$grupo['icone']" :descricao="$grupo['descricao']">
                                <form method="POST" action="{{ route('admin.settings.chaves') }}" class="row g-3">
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

                                    <form method="POST" action="{{ route('admin.settings.email.sondar') }}" class="row g-3">
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
