@extends('admin.layouts.app')
@section('title', 'Lead — '.$lead->name)
@section('page_title', e($lead->name))
@section('page_description', 'Ficha do lead, funil e trilha de atendimento.')

@section('page_actions')
<a href="{{ route('admin.leads.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@if($lead->customer_id)
<a href="{{ route('admin.clientes.show', $lead->customer_id) }}" class="btn btn-sm btn-outline-success"><i class="fas fa-user-check me-1"></i> Ver cliente no CRM</a>
@else
<form method="POST" action="{{ route('admin.leads.converter', $lead) }}" class="d-inline">
    @csrf
    <button class="btn btn-sm btn-success" title="Criar cliente no CRM a partir deste lead"><i class="fas fa-user-plus me-1"></i> Converter em cliente</button>
</form>
@endif
<a href="https://wa.me/{{ preg_replace('/\D/','',$lead->phone) }}" target="_blank" rel="noopener" class="btn btn-sm btn-success"><i class="fab fa-whatsapp me-1"></i> WhatsApp</a>
@endsection

@section('content')
@php $fmt = fn($c)=> 'R$ '.number_format($c/100,2,',','.'); @endphp
<div class="row g-3">
    <div class="col-lg-7">
        {{-- Funil --}}
        <div class="card shadow-sm mb-3" style="border-radius:16px"><div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-filter text-warning me-1"></i> Mover no funil</h5>
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach(\App\Models\Lead::STATUSES as $s)
                <form method="POST" action="{{ route('admin.leads.status',$lead) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="status" value="{{ $s }}">
                    <button class="btn btn-sm {{ $lead->status===$s ? 'btn-golden' : 'btn-outline-secondary' }}" {{ $lead->status===$s ? 'disabled':'' }}>
                        {{ \App\Models\Lead::label($s) }}
                    </button>
                </form>
                @endforeach
            </div>
            <form method="POST" action="{{ route('admin.leads.nota',$lead) }}" class="d-flex gap-2">
                @csrf
                <input name="note" class="form-control form-control-sm" placeholder="Registrar nota de atendimento..." maxlength="1000" required>
                <button class="btn btn-sm btn-golden"><i class="fas fa-note-sticky"></i></button>
            </form>
        </div></div>

        {{-- Edição do cadastro --}}
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-pen-to-square text-warning me-1"></i> Dados do lead</h5>
            <form method="POST" action="{{ route('admin.leads.update',$lead) }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label small">Nome</label><input name="name" class="form-control form-control-sm" value="{{ $lead->name }}" required></div>
                    <div class="col-md-6"><label class="form-label small">WhatsApp</label><input name="phone" class="form-control form-control-sm" value="{{ $lead->phone }}" required></div>
                    <div class="col-md-6"><label class="form-label small">E-mail</label><input name="email" class="form-control form-control-sm" value="{{ $lead->email }}"></div>
                    <div class="col-md-6"><label class="form-label small">Perfil</label>
                        <select name="profile" class="form-select form-select-sm"><option value="">—</option>@foreach(config('golden.perfis') as $v=>$r)<option value="{{ $v }}" @selected($lead->profile==$v)>{{ $r }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-6"><label class="form-label small">Frota</label><input name="fleet_size" class="form-control form-control-sm" value="{{ $lead->fleet_size }}"></div>
                    <div class="col-md-6"><label class="form-label small">Kit</label><input name="kit" class="form-control form-control-sm" value="{{ $lead->kit }}"></div>
                    <div class="col-md-6"><label class="form-label small">Campanha</label>
                        <select name="campaign_id" class="form-select form-select-sm"><option value="">Sem campanha</option>@foreach($campanhas as $c)<option value="{{ $c->id }}" @selected($lead->campaign_id==$c->id)>{{ Str::limit($c->name,26) }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-6"><label class="form-label small">Responsável</label>
                        <select name="owner_id" class="form-select form-select-sm"><option value="">—</option>@foreach($responsaveis as $r)<option value="{{ $r->id }}" @selected($lead->owner_id==$r->id)>{{ $r->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-6"><label class="form-label small">Valor fechado (R$)</label><input type="number" step="0.01" min="0" name="value" class="form-control form-control-sm" value="{{ $lead->value ? $lead->value/100 : '' }}" placeholder="ex.: 1290.00"></div>
                    <div class="col-md-6"><label class="form-label small">Mensagem do lead</label><input name="message" class="form-control form-control-sm" value="{{ Str::limit($lead->message,120,'') }}"></div>
                </div>
                <button class="btn btn-golden btn-sm mt-3"><i class="fas fa-save me-1"></i> Salvar alterações</button>
            </form>
        </div></div>
    </div>

    <div class="col-lg-5">
        {{-- Atribuição --}}
        <div class="card shadow-sm mb-3" style="border-radius:16px"><div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-bullseye text-warning me-1"></i> Atribuição de campanha</h5>
            <ul class="list-unstyled small mb-0" style="line-height:1.9">
                <li><span class="text-secondary">Campanha:</span> <strong>{{ $lead->campaign?->name ?? 'Sem campanha' }}</strong></li>
                <li><span class="text-secondary">utm_source:</span> {{ $lead->utm_source ?: '—' }}</li>
                <li><span class="text-secondary">utm_medium:</span> {{ $lead->utm_medium ?: '—' }}</li>
                <li><span class="text-secondary">utm_campaign:</span> {{ $lead->utm_campaign ?: '—' }}</li>
                <li><span class="text-secondary">utm_content:</span> {{ $lead->utm_content ?: '—' }}</li>
                <li><span class="text-secondary">gclid:</span> {{ $lead->gclid ?: '—' }}</li>
                <li><span class="text-secondary">fbclid:</span> {{ $lead->fbclid ?: '—' }}</li>
                <li><span class="text-secondary">Visita:</span> <code>{{ $lead->visitor_id ?: '—' }}</code></li>
                <li><span class="text-secondary">Origem:</span> {{ $lead->referrer ?: '—' }}</li>
                <li><span class="text-secondary">Consentimento LGPD:</span> @if($lead->consent_given)<span class="text-success fw-bold">Autorizado</span>@else<span class="text-danger fw-bold">Não autorizado</span>@endif</li>
                <li><span class="text-secondary">IP (pseudonimizado):</span> <code>{{ Str::limit($lead->ip_hash,12,'') }}</code></li>
            </ul>
        </div></div>

        {{-- Linha do tempo --}}
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-timeline text-warning me-1"></i> Histórico</h5>
            <ul class="timeline">
                @forelse($lead->histories as $h)
                <li>
                    <div class="d-flex justify-content-between"><span class="fw-semibold small">
                        @if($h->from_status && $h->from_status!==$h->to_status){{ \App\Models\Lead::label($h->from_status) }} → {{ \App\Models\Lead::label($h->to_status) }}@elseif($h->from_status===$h->to_status)Nota@else{{ \App\Models\Lead::label($h->to_status) }}@endif
                    </span><small class="text-secondary">{{ $h->created_at->format('d/m H:i') }}</small></div>
                    @if($h->note)<div class="small text-secondary">{{ $h->note }}</div>@endif
                    @if($h->user)<div class="small text-secondary">— {{ $h->user->name }}</div>@endif
                </li>
                @empty
                <li class="text-secondary small">Sem registros.</li>
                @endforelse
            </ul>
            @if(auth()->user()->isAdmin())
            <form method="POST" action="{{ route('admin.leads.destroy',$lead) }}" class="mt-2" onsubmit="return confirm('Excluir definitivamente este lead?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i> Excluir lead</button>
            </form>
            @endif
        </div></div>
    </div>
</div>
@endsection
