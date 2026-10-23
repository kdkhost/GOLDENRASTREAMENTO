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
                <span class="badge {{ $lojaAtiva ? 'bg-success' : 'bg-secondary' }}">{{ $lojaAtiva ? 'Ativa' : 'Desativada' }}</span>
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
                <span class="badge {{ $boxAtivo ? 'bg-success' : 'bg-secondary' }}">{{ $boxAtivo ? 'Caixa multicanal' : 'Link direto' }}</span>
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
                        <div class="canal-linha d-flex align-items-center gap-2 mb-2">
                            <input class="form-check-input mt-0" type="checkbox" name="{{ $chave }}_ativo" value="1" id="{{ $chave }}-ativo"
                                @checked(($settings[$chave.'_ativo'] ?? '1') === '1')>
                            <label for="{{ $chave }}-ativo" class="small flex-grow-1 mb-0">
                                <i class="fas {{ $canal['icone'] }} text-gold me-1" aria-hidden="true"></i>{{ $canal['nome'] }}
                                <span class="d-block text-secondary">{{ $canal['msg'] }}</span>
                            </label>
                            <input name="{{ $chave }}_numero" class="form-control form-control-sm"
                                value="{{ $settings[$chave.'_numero'] ?? '' }}" placeholder="só dígitos" aria-label="Número do canal {{ $canal['nome'] }}">
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
            </dl>
            <p class="small text-secondary mb-0 mt-2">Para produção, defina <code>DB_CONNECTION=mysql</code> no <code>.env</code> e rode <code>php artisan migrate --force</code>. As migrations são compatíveis com os dois bancos.</p>
        </x-admin.card>
    </div>
</div>
@endsection
