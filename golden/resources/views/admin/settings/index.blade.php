@extends('admin.layouts.app')
@section('title', 'Configurações')
@section('page_title', 'Configurações do sistema')
@section('page_description', 'Contato oficial, endereço da empresa, loja virtual e ferramentas de medição.')

@php $lojaAtiva = ($settings['loja_ativa'] ?? '1') === '1'; $livre = ($settings['loja_livre'] ?? '0') === '1'; @endphp

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                <h5 class="fw-bold mb-3"><i class="fas fa-id-card text-warning me-1"></i> Contato oficial</h5>
                <div class="mb-2"><label class="form-label small">Nome do site</label><input name="site_nome" class="form-control form-control-sm" value="{{ $settings['site_nome'] ?? 'Golden Rastreamento' }}" required></div>
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label small">WhatsApp (DDI+DDD+número, só dígitos)</label><input name="contato_whatsapp" class="form-control form-control-sm" value="{{ $settings['contato_whatsapp'] ?? '' }}" required placeholder="5519997101483"></div>
                    <div class="col-md-6"><label class="form-label small">Telefones fixos / extras</label><input name="contato_telefone" class="form-control form-control-sm" value="{{ $settings['contato_telefone'] ?? '' }}" placeholder="(19) 99710-1483 · (19) 97139-5129"></div>
                </div>
                <div class="mt-2"><label class="form-label small">E-mail comercial</label><input type="email" name="contato_email" class="form-control form-control-sm" value="{{ $settings['contato_email'] ?? '' }}"></div>

                <hr class="my-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-location-dot text-warning me-1"></i> Endereço da empresa</h5>
                <div class="mb-2"><label class="form-label small">Endereço</label><input name="empresa_endereco" class="form-control form-control-sm" value="{{ $settings['empresa_endereco'] ?? '' }}" placeholder="R. Pref. Olinto Réis Campos, 104 - Centro"></div>
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label small">Cidade / UF</label><input name="empresa_cidade" class="form-control form-control-sm" value="{{ $settings['empresa_cidade'] ?? '' }}" placeholder="Três Pontas - MG"></div>
                    <div class="col-md-6"><label class="form-label small">CEP</label><input name="empresa_cep" class="form-control form-control-sm" value="{{ $settings['empresa_cep'] ?? '' }}" placeholder="37185-016"></div>
                </div>

                <hr class="my-4">
                <h5 class="fw-bold mb-1"><i class="fas fa-chart-area text-warning me-1"></i> Medição e consentimento</h5>
                <div class="form-check form-switch mb-3 mt-2">
                    <input class="form-check-input" type="checkbox" name="tracking_enabled" value="1" id="trk" @checked(($settings['tracking_enabled'] ?? '0')==='1')>
                    <label class="form-check-label small" for="trk">Ativar coleta de eventos (GA4 / Meta Pixel / GTM). Só habilite após publicar a Política de Privacidade e o banner de consentimento.</label>
                </div>
                <div class="row g-2">
                    <div class="col-md-4"><label class="form-label small">GA4 Measurement ID</label><input name="ga_measurement_id" class="form-control form-control-sm" value="{{ $settings['ga_measurement_id'] ?? '' }}" placeholder="G-XXXXXXX"></div>
                    <div class="col-md-4"><label class="form-label small">Meta Pixel ID</label><input name="meta_pixel_id" class="form-control form-control-sm" value="{{ $settings['meta_pixel_id'] ?? '' }}"></div>
                    <div class="col-md-4"><label class="form-label small">GTM ID</label><input name="gtm_id" class="form-control form-control-sm" value="{{ $settings['gtm_id'] ?? '' }}" placeholder="GTM-XXXX"></div>
                </div>

                <button class="btn btn-golden btn-sm mt-4"><i class="fas fa-save me-1"></i> Salvar configurações</button>
            </form>
        </div></div>
    </div>

    <div class="col-lg-5">
        {{-- Loja virtual --}}
        <div class="card shadow-sm" style="border-radius:16px">
            <div class="card-header bg-body d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-store text-warning me-1"></i> Loja virtual</span>
                <span class="badge {{ $lojaAtiva ? 'bg-success':'bg-secondary' }}">{{ $lojaAtiva ? 'Ativa':'Desativada' }}</span>
            </div>
            <div class="card-body">
                <p class="small text-secondary">A loja vende rastreadores, chips e acessórios cadastrados no ERP. Desligue a qualquer momento — as páginas <code>/loja</code> saem do ar na hora e somem do menu do site.</p>
                <form method="POST" action="{{ route('admin.settings.loja') }}">
                    @csrf
                    <input type="hidden" name="ativa" value="{{ $lojaAtiva ? '0' : '1' }}">
                    <div class="d-flex gap-2 align-items-center flex-wrap mb-3">
                        <input type="checkbox" name="loja_livre" value="1" id="livre" class="form-check-input mt-0" @checked($livre)>
                        <label for="livre" class="small flex-grow-1">Frete grátis / combinar entrega (não cobra valor fixo)</label>
                    </div>
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-7"><label class="form-label small">Frete fixo (R$) — se não for grátis</label><input type="number" step="0.01" min="0" name="loja_frete" class="form-control form-control-sm" placeholder="0,00" @disabled($livre)></div>
                        <div class="col-5"><button class="btn btn-sm btn-golden w-100"><i class="fas fa-save me-1"></i> Salvar</button></div>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.settings.loja') }}">
                    @csrf
                    <input type="hidden" name="ativa" value="{{ $lojaAtiva ? '0' : '1' }}">
                    <button class="btn w-100 {{ $lojaAtiva ? 'btn-outline-danger':'btn-success' }}">
                        <i class="fas fa-power-off me-1"></i> {{ $lojaAtiva ? 'Desativar loja agora' : 'Ativar loja agora' }}
                    </button>
                </form>
                @if($lojaAtiva)
                <a href="{{ route('site.loja.index') }}" target="_blank" class="btn btn-sm btn-outline-secondary w-100 mt-2"><i class="fas fa-external-link-alt me-1"></i> Ver a loja no site</a>
                @endif
            </div>
        </div>

        {{-- Banco --}}
        <div class="card shadow-sm mt-3" style="border-radius:16px"><div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-circle-info text-warning me-1"></i> Banco de dados</h5>
            <p class="small text-secondary mb-2">Conexão ativa: <span class="badge bg-body-secondary text-body">{{ config('database.default') }}</span></p>
            <p class="small text-secondary">Em desenvolvimento o sistema usa <strong>SQLite</strong> (<code>database/database.sqlite</code>). Para produção, defina <code>DB_CONNECTION=mysql</code> no <code>.env</code> e rode <code>php artisan migrate --force</code>. As migrations são compatíveis com ambos.</p>
        </div></div>

        <div class="card shadow-sm mt-3" style="border-radius:16px"><div class="card-body">
            <h6 class="fw-bold">Sistema integrado</h6>
            <ul class="small text-secondary mb-0" style="line-height:1.9">
                <li><strong>CRM</strong> — leads, clientes e interações</li>
                <li><strong>CMS</strong> — páginas, depoimentos e FAQs editáveis</li>
                <li><strong>ERP</strong> — produtos, estoque, fornecedores e pedidos</li>
            </ul>
        </div></div>
    </div>
</div>
@endsection
