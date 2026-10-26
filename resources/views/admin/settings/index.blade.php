@extends('admin.layouts.app')
@section('title', 'Configurações')
@section('page_title', 'Configurações do sistema')
@section('page_description', 'Contato oficial, endereço da empresa, loja virtual e ferramentas de medição.')

@php
    $lojaAtiva = ($settings['loja_ativa'] ?? '1') === '1';
    $livre = ($settings['loja_livre'] ?? '0') === '1';
    $retirada = ($settings['loja_retirada'] ?? '0') === '1';
    $boxAtivo = ($settings['whatsapp_box_ativo'] ?? '1') === '1';
    $paraExibir = fn ($cents) => ((int) $cents) > 0 ? number_format(((int) $cents) / 100, 2, '.', '') : '';
    $numeroPadrao = $settings['contato_whatsapp'] ?? config('golden.whatsapp');
@endphp

@section('page_actions')
@if($lojaAtiva)
    <a href="{{ route('site.loja.index') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-up-right-from-square me-1"></i> Ver a loja no site</a>
@endif
<a href="{{ route('site.home') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-globe me-1"></i> Ver o site</a>
@endsection

@section('content')
<div class="row g-3">
    {{-- Identidade, contato, endereço e medição: um único endpoint, um único formulário --}}
    <div class="col-lg-7">
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <x-admin.card title="Contato oficial" icon="fa-id-card" descricao="É o que aparece no rodapé, no formulário do site e nos e-mails.">
                <div class="row g-3">
                    <x-admin.field name="site_nome" label="Nome do site" :value="$settings['site_nome'] ?? 'Golden Rastreamento'" col="col-12 col-md-6" :required="true" maxlength="120" />
                    <x-admin.field name="contato_whatsapp" label="WhatsApp oficial" :value="$settings['contato_whatsapp'] ?? ''" col="col-12 col-md-6" :required="true" maxlength="32" placeholder="5519997101483" hint="DDI + DDD + número, só dígitos." />
                    <x-admin.field name="contato_telefone" label="Telefones extras" :value="$settings['contato_telefone'] ?? ''" maxlength="60" :opcional="true" placeholder="(19) 99710-1483 · (19) 97139-5129" />
                    <x-admin.field name="contato_email" type="email" label="E-mail comercial" :value="$settings['contato_email'] ?? ''" maxlength="160" :opcional="true" autocomplete="off" />
                </div>
            </x-admin.card>

            <x-admin.card title="Endereço da empresa" icon="fa-location-dot" class="mt-3">
                <div class="row g-3">
                    <x-admin.field name="empresa_endereco" label="Endereço" :value="$settings['empresa_endereco'] ?? ''" maxlength="255" :opcional="true" placeholder="R. Pref. Olinto Réis Campos, 104 - Centro" />
                    <x-admin.field name="empresa_cidade" label="Cidade / UF" :value="$settings['empresa_cidade'] ?? ''" col="col-8" maxlength="120" :opcional="true" placeholder="Três Pontas - MG" />
                    <x-admin.field name="empresa_cep" label="CEP" :value="$settings['empresa_cep'] ?? ''" col="col-4" maxlength="12" :opcional="true" placeholder="37185-016" />
                </div>
            </x-admin.card>

            <x-admin.card title="Acessos externos do site" icon="fa-arrow-up-right-from-square" class="mt-3"
                descricao="Para onde levam o submenu da conta e o botão do topo do site. O portal do cliente é rota interna e não aparece aqui.">
                <div class="row g-3">
                    <x-admin.field name="acesso_financeiro_url" type="url" label="Financeiro antigo" :value="$settings['acesso_financeiro_url'] ?? config('golden.acessos.financeiro')" maxlength="255" :opcional="true" placeholder="https://financeiro.exemplo.com.br/login" hint="Abre em outra aba, pelo submenu do ícone de login." />
                    <x-admin.field name="acesso_servidor_1_url" type="url" label="Servidor de rastreamento 1" :value="$settings['acesso_servidor_1_url'] ?? config('golden.acessos.servidor_1')" maxlength="255" :opcional="true" placeholder="https://app1.exemplo.com.br" />
                    <x-admin.field name="acesso_servidor_2_url" type="url" label="Servidor de rastreamento 2" :value="$settings['acesso_servidor_2_url'] ?? config('golden.acessos.servidor_2')" maxlength="255" :opcional="true" placeholder="http://203.0.113.10:8082/" hint="Cada servidor aparece como uma opção no botão do topo do site." />
                </div>
            </x-admin.card>

            <x-admin.card title="Medição e consentimento" icon="fa-chart-area" class="mt-3"
                descricao="Só habilite a coleta depois de publicar a Política de Privacidade e o banner de consentimento.">
                <div class="row g-3">
                    <x-admin.switch name="tracking_enabled" label="Ativar coleta de eventos (GA4, Meta Pixel, GTM)" :checked="($settings['tracking_enabled'] ?? '0') === '1'" />
                    <x-admin.field name="ga_measurement_id" label="GA4 Measurement ID" :value="$settings['ga_measurement_id'] ?? ''" col="col-12 col-md-4" maxlength="60" :opcional="true" placeholder="G-XXXXXXX" />
                    <x-admin.field name="meta_pixel_id" label="Meta Pixel ID" :value="$settings['meta_pixel_id'] ?? ''" col="col-12 col-md-4" maxlength="60" :opcional="true" />
                    <x-admin.field name="gtm_id" label="GTM ID" :value="$settings['gtm_id'] ?? ''" col="col-12 col-md-4" maxlength="60" :opcional="true" placeholder="GTM-XXXX" />
                </div>
            </x-admin.card>

            <button class="btn btn-golden mt-3"><i class="fas fa-save me-1"></i> Salvar configurações gerais</button>
        </form>
    </div>

    <div class="col-lg-5">
        <x-admin.card title="Loja virtual" icon="fa-store" :descricao="$lojaAtiva ? 'Ativa — as páginas /loja estão no ar e no menu do site.' : 'Desativada — /loja sai do ar e some do menu.'">
            <x-slot:actions>
                <x-admin.status :rotulo="$lojaAtiva ? 'ativa' : 'desativada'" :tom="tom_por_flag($lojaAtiva)" />
            </x-slot:actions>
            <form method="POST" action="{{ route('admin.settings.loja') }}" class="mb-3">
                @csrf
                <input type="hidden" name="ativa" value="{{ $lojaAtiva ? '0' : '1' }}">
                <button class="btn w-100 {{ $lojaAtiva ? 'btn-outline-danger' : 'btn-success' }}">
                    <i class="fas fa-power-off me-1"></i> {{ $lojaAtiva ? 'Desativar loja agora' : 'Ativar loja agora' }}
                </button>
            </form>

            <form method="POST" action="{{ route('admin.settings.loja-regras') }}" class="row g-3">
                @csrf
                <x-admin.switch name="loja_livre" label="Frete combinado (não cobra valor fixo)" :checked="$livre" />
                <x-admin.switch name="loja_retirada" label="Permitir retirada na loja" :checked="$retirada" />
                <x-admin.field name="loja_frete" type="number" label="Frete fixo (R$)" :value="$paraExibir($settings['loja_frete_cents'] ?? 0)" col="col-6" step="0.01" min="0" max="99999" placeholder="0,00" :disabled="$livre" />
                <x-admin.field name="loja_frete_gratis_acima" type="number" label="Frete grátis acima de (R$)" :value="$paraExibir($settings['loja_frete_gratis_acima_cents'] ?? 0)" col="col-6" step="0.01" min="0" max="99999" placeholder="ex.: 997,00" :disabled="$livre" />
                <x-admin.field name="loja_pix_chave" label="Chave Pix exibida no checkout" :value="$settings['loja_pix_chave'] ?? ''" maxlength="120" :opcional="true" placeholder="CPF, CNPJ, e-mail ou aleatória" />
                <x-admin.field name="loja_email_equipe" type="email" label="E-mail da equipe" :value="$settings['loja_email_equipe'] ?? ''" maxlength="160" :opcional="true" hint="Recebe o alerta de cada venda nova." placeholder="vendas@seudominio.com.br" />
                <div class="col-12">
                    <button class="btn btn-golden btn-sm w-100"><i class="fas fa-save me-1"></i> Salvar regras da loja</button>
                </div>
            </form>
        </x-admin.card>

        <x-admin.card title="Botão do WhatsApp no site" icon="fa-brands fa-whatsapp" class="mt-3"
            :descricao="$boxAtivo ? 'Abre a central de canais com a mensagem pronta por segmento.' : 'Link direto para o número principal.'">
            <x-slot:actions>
                <x-admin.status :rotulo="$boxAtivo ? 'caixa multicanal' : 'link direto'" :tom="tom_por_flag($boxAtivo)" />
            </x-slot:actions>
            <form method="POST" action="{{ route('admin.settings.whatsapp') }}" class="row g-3">
                @csrf
                <x-admin.switch name="whatsapp_box_ativo" label="Mostrar a caixa multicanal" :checked="$boxAtivo" />
                <x-admin.field name="whatsapp_box_titulo" label="Título da caixa" :value="$settings['whatsapp_box_titulo'] ?? config('golden.whatsapp_box.titulo')" col="col-6" maxlength="120" :opcional="true" />
                <x-admin.field name="whatsapp_box_subtitulo" label="Subtítulo" :value="$settings['whatsapp_box_subtitulo'] ?? config('golden.whatsapp_box.subtitulo')" col="col-6" maxlength="180" :opcional="true" />

                <div class="col-12 pt-2">
                    <span class="form-label">Canais</span>
                    <div class="form-hint d-block mb-2">O número do canal é opcional: vazio, usa o WhatsApp principal ({{ $numeroPadrao }}).</div>
                    @foreach(config('golden.whatsapp_box.canais') as $canal)
                        @php $chave = 'whatsapp_canal_'.$canal['id']; @endphp
                        <div class="canal-linha">
                            <x-admin.switch name="{{ $chave }}_ativo" col="" :checked="($settings[$chave.'_ativo'] ?? '1') === '1'">
                                <i class="fas {{ $canal['icone'] }} text-gold me-1" aria-hidden="true"></i>{{ $canal['nome'] }}
                                <span class="d-block text-secondary small">{{ $canal['msg'] }}</span>
                            </x-admin.switch>
                            <x-admin.field name="{{ $chave }}_numero" label="Número" col="" class="canal-numero"
                                :value="$settings[$chave.'_numero'] ?? ''" placeholder="só dígitos" />
                        </div>
                    @endforeach
                </div>
                <div class="col-12">
                    <button class="btn btn-golden btn-sm w-100"><i class="fas fa-save me-1"></i> Salvar WhatsApp</button>
                </div>
            </form>
        </x-admin.card>

        <x-admin.card title="Ambiente" icon="fa-circle-info" class="mt-3">
            <dl class="defs mb-0">
                <dt>Banco ativo</dt><dd>{{ config('database.default') }}@if(config('database.default') === 'sqlite') <span class="form-hint">(<code>database/database.sqlite</code>)</span>@endif</dd>
                <dt>Módulos</dt><dd>CRM (leads, clientes, interações) · CMS (páginas, depoimentos, FAQs) · ERP (produtos, estoque, fornecedores, pedidos)</dd>
                <dt>Chaves</dt><dd>{{ count(\App\Support\ChavesDeIntegracao::campos()) }} campos, todos editáveis nesta tela. O <code>.env</code> deste servidor só conduz o que o banco não tem.</dd>
            </dl>
            <p class="small text-secondary mb-0 mt-2">Ficam no <code>.env</code> só o que é deste servidor e não decisão de ninguém: a ligação com o banco (<code>DB_CONNECTION=mysql</code>, <code>php artisan migrate --force</code> — as migrations rodam nos dois bancos), a chave da aplicação e o transporte do Google Ads (<code>GOOGLE_ADS_TRANSPORT</code>, <code>GOOGLE_ADS_CACHE_STORE</code>), que dependem do que a máquina tem.</p>
        </x-admin.card>
    </div>

    {{-- As credenciais que até aqui só existiam no .env. Um card por grupo, um endpoint só:
         a tela percorre ChavesDeIntegracao::grupos(), então acrescentar uma chave é escrever na
         lista — ninguém precisa mexer em view nem em controle para isso aparecer aqui. --}}
    <div class="col-12" id="chaves">
        <h6 class="zone-title"><i class="fas fa-key text-tone-gold" aria-hidden="true"></i> Chaves de integração</h6>
        <p class="form-hint">O que for gravado aqui vale na hora, sem tocar no servidor, e fica cifrado no banco quando é segredo. Campo vazio é o painel se retirando do caminho: o <code>.env</code> deste servidor volta a conduzir aquele valor.</p>

        <div class="row g-3">
            @foreach (\App\Support\ChavesDeIntegracao::grupos() as $id => $grupo)
                <div class="col-12 col-xl-4" id="chaves-{{ $id }}">
                    <x-admin.card :title="$grupo['rotulo']" :icon="$grupo['icone']" :descricao="$grupo['descricao']">
                        <form method="POST" action="{{ route('admin.settings.chaves') }}" class="row g-3">
                            @csrf
                            <input type="hidden" name="grupo" value="{{ $id }}">
                            @foreach ($grupo['campos'] as $campo)
                                <x-admin.campo-chave :campo="$campo" />
                            @endforeach
                            <div class="col-12">
                                <button class="btn btn-golden btn-sm w-100"><i class="fas fa-save me-1"></i> Salvar chaves</button>
                            </div>
                        </form>
                    </x-admin.card>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
