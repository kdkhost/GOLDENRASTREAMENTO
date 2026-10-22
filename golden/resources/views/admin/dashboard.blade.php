@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('page_title', 'Visão geral de leads')
@section('page_description', 'Acompanhe contatos e conversões das campanhas de tráfego pago.')

@section('page_actions')
<a href="{{ route('admin.leads.export', request()->query()) }}" class="btn btn-golden btn-sm"><i class="fas fa-file-csv me-1"></i> Exportar CSV</a>
@endsection

@section('content')
@php
    $fmt = fn($c)=> 'R$ '.number_format($c/100, 2, ',', '.');
@endphp
{{-- KPIs --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card stat-card shadow-sm"><div class="card-body d-flex align-items-center gap-3">
            <div class="icon" style="background:rgba(31,158,134,.14);color:#1f9e86"><i class="fas fa-users"></i></div>
            <div><div class="value">{{ $total }}</div><div class="label">Leads totais</div></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card shadow-sm"><div class="card-body d-flex align-items-center gap-3">
            <div class="icon" style="background:rgba(224,163,62,.16);color:#c9862a"><i class="fas fa-envelope-circle-check"></i></div>
            <div><div class="value">{{ $novos }}</div><div class="label">Novos / aguardando</div></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card shadow-sm"><div class="card-body d-flex align-items-center gap-3">
            <div class="icon" style="background:rgba(18,105,79,.14);color:#12694f"><i class="fas fa-trophy"></i></div>
            <div><div class="value">{{ $conversao }}%</div><div class="label">Taxa de conversão</div></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card shadow-sm"><div class="card-body d-flex align-items-center gap-3">
            <div class="icon" style="background:rgba(94,53,177,.12);color:#5e35b1"><i class="fas fa-sack-dollar"></i></div>
            <div><div class="value" style="font-size:1.4rem">{{ $fmt($receita) }}</div><div class="label">Receita fechada</div></div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Funil --}}
    <div class="col-lg-5">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h5 class="card-title fw-bold"><i class="fas fa-filter text-warning me-1"></i> Funil de conversão</h5>
            @php $max = max($total,1);
                 $funnel = ['novo'=>$novos,'contatado'=>$contatado,'proposta'=>$proposta,'vendido'=>$vendid,'perdido'=>$perdido]; @endphp
            <div class="funnel mt-3">
                @foreach(['novo'=>'#1c5d99','contatado'=>'#e0a33e','proposta'=>'#5e35b1','vendido'=>'#1f9e86','perdido'=>'#c0392b'] as $st=>$cor)
                @php $c = $funnel[$st]; $w = round($c/$max*100); @endphp
                <div class="funnel-row">
                    <div style="width:96px;font-size:.85rem;color:var(--bs-secondary-color)">{{ \App\Models\Lead::label($st) }}</div>
                    <div class="funnel-bar" style="width:{{ max($w,8) }}%;background:{{ $cor }}">{{ $c }}</div>
                </div>
                @endforeach
            </div>
        </div></div>
    </div>
    {{-- Gráfico --}}
    <div class="col-lg-7">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h5 class="card-title fw-bold"><i class="fas fa-chart-line text-warning me-1"></i> Leads nos últimos 30 dias</h5>
            <canvas id="chartLeads" height="150"></canvas>
        </div></div>
    </div>
</div>

<div class="row g-3">
    {{-- Conversão por campanha --}}
    <div class="col-lg-7">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h5 class="card-title fw-bold"><i class="fas fa-bullhorn text-warning me-1"></i> Conversão por campanha</h5>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr class="text-secondary small"><th>Campanha</th><th>Canal</th><th class="text-center">Leads</th><th class="text-center">Vendidos</th><th class="text-center">Conversão</th></tr></thead>
                    <tbody>
                    @forelse($campanhas as $c)
                        <tr>
                            <td><a href="{{ route('admin.leads.index', ['campanha'=>$c->id]) }}" class="fw-semibold text-decoration-none">{{ $c->name }}</a></td>
                            <td><span class="badge bg-body-secondary text-body">{{ $c->channel }}</span></td>
                            <td class="text-center">{{ $c->leads_count }}</td>
                            <td class="text-center">{{ $c->leads_vendidos }}</td>
                            <td class="text-center"><span class="badge-status {{ $c->taxo_conversao>0?'st-vendido':'st-novo' }}">{{ $c->taxo_conversao }}%</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">Nenhuma campanha com leads ainda.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
    {{-- Recentes --}}
    <div class="col-lg-5">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h5 class="card-title fw-bold"><i class="fas fa-clock text-warning me-1"></i> Leads recentes</h5>
            @foreach($recentes as $l)
            <a href="{{ route('admin.leads.show',$l) }}" class="d-flex justify-content-between align-items-center text-decoration-none py-2 border-bottom">
                <div><span class="fw-semibold text-body">{{ $l->name }}</span><br><small class="text-secondary">{{ $l->campaign?->name ?? 'Sem campanha' }} · {{ $l->created_at->diffForHumans() }}</small></div>
                <span class="badge-status st-{{ $l->status }}">{{ \App\Models\Lead::label($l->status) }}</span>
            </a>
            @endforeach
        </div></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartLeads'),{
  type:'line',
  data:{labels:@json($labels),datasets:[{label:'Leads',data:@json($serie),borderColor:'#c9862a',backgroundColor:'rgba(224,163,62,.12)',fill:true,tension:.35,pointRadius:0,borderWidth:2}]},
  options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{grid:{display:false},ticks:{maxTicksLimit:8}}}}
});
</script>
@endpush
