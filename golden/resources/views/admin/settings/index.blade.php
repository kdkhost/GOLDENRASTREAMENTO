@extends('admin.layouts.app')
@section('title', 'Configurações')
@section('page_title', 'Configurações do sistema')
@section('page_description', 'Contato oficial, consentimento e ferramentas de medição das campanhas.')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                <h5 class="fw-bold mb-3"><i class="fas fa-id-card text-warning me-1"></i> Contato oficial</h5>
                <div class="mb-2"><label class="form-label small">Nome do site</label><input name="site_nome" class="form-control form-control-sm" value="{{ $settings['site_nome'] ?? 'Golden Rastreamento' }}" required></div>
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label small">WhatsApp (DDI+DDD+número, só dígitos)</label><input name="contato_whatsapp" class="form-control form-control-sm" value="{{ $settings['contato_whatsapp'] ?? '' }}" required placeholder="5535988880000"></div>
                    <div class="col-md-6"><label class="form-label small">E-mail comercial</label><input type="email" name="contato_email" class="form-control form-control-sm" value="{{ $settings['contato_email'] ?? '' }}"></div>
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
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-circle-info text-warning me-1"></i> Banco de dados</h5>
            <p class="small text-secondary mb-2">Conexão ativa: <span class="badge bg-body-secondary text-body">{{ config('database.default') }}</span></p>
            <p class="small text-secondary">Em desenvolvimento o sistema usa <strong>SQLite</strong> (arquivo <code>database/database.sqlite</code>). Para produção, defina <code>DB_CONNECTION=mysql</code> no <code>.env</code> com as credenciais do servidor e rode <code>php artisan migrate --force</code>. As migrations são compatíveis com ambos.</p>
            <hr>
            <h6 class="fw-bold">Eventos já instrumentados no site</h6>
            <ul class="small text-secondary mb-0" style="line-height:1.9">
                <li><code>page_view</code> · <code>view_plans</code> · <code>select_plan</code></li>
                <li><code>contact</code> · <code>whatsapp_click</code></li>
                <li><code>generate_lead</code> (após sucesso no servidor) · <code>lead_success</code></li>
            </ul>
        </div></div>
    </div>
</div>
@endsection
