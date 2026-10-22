@extends('admin.layouts.app')
@section('title', 'Campanhas')
@section('page_title', 'Campanhas de tráfego pago')
@section('page_description', 'Origem dos leads e taxa de conversão por campanha.')

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-plus text-warning me-1"></i> Nova campanha</h5>
            <form method="POST" action="{{ route('admin.campanhas.store') }}">
                @csrf
                <div class="mb-2"><label class="form-label small">Nome</label><input name="name" class="form-control form-control-sm" required placeholder="Ex.: Meta Ads — Motoristas"></div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label small">Source</label><input name="source" class="form-control form-control-sm" placeholder="facebook"></div>
                    <div class="col-6"><label class="form-label small">Medium</label><input name="medium" class="form-control form-control-sm" placeholder="paid-social"></div>
                </div>
                <div class="mb-2 mt-2"><label class="form-label small">Content / criativo</label><input name="content" class="form-control form-control-sm"></div>
                <div class="mb-3"><label class="form-label small">Canal</label>
                    <select name="channel" class="form-select form-select-sm"><option value="pago">Pago</option><option value="organico">Orgânico</option><option value="direto">Direto</option><option value="referencia">Indicação</option></select>
                </div>
                <button class="btn btn-golden btn-sm"><i class="fas fa-save me-1"></i> Criar campanha</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr class="text-secondary small"><th>Campanha</th><th>Canal</th><th class="text-center">Leads</th><th class="text-center">Novos</th><th class="text-center">Vendidos</th><th class="text-center">Conversão</th><th class="text-center">Receita</th><th></th></tr></thead>
                    <tbody>
                    @forelse($campanhas as $c)
                        <tr>
                            <td><a href="{{ route('admin.leads.index',['campanha'=>$c->id]) }}" class="fw-semibold text-decoration-none text-body">{{ $c->name }}</a><div class="small text-secondary">{{ $c->source ?: '—' }} · {{ $c->medium ?: '—' }}</div></td>
                            <td><span class="badge bg-body-secondary text-body">{{ $c->channel }}</span></td>
                            <td class="text-center">{{ $c->leads_count }}</td>
                            <td class="text-center">{{ $c->leads_novos }}</td>
                            <td class="text-center">{{ $c->leads_vendidos }}</td>
                            <td class="text-center"><span class="badge-status {{ $c->leads_count && $c->leads_vendidos ? 'st-vendido':'st-novo' }}">{{ $c->leads_count ? round($c->leads_vendidos/$c->leads_count*100,1):0 }}%</span></td>
                            <td class="text-center small">{{ $c->leads_receita ? 'R$ '.number_format($c->leads_receita/100,0,',','.') : '—' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.campanhas.toggle',$c) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary" title="{{ $c->ativa ? 'Pausar':'Ativar' }}"><i class="fas fa-{{ $c->ativa ? 'pause':'play' }}"></i></button></form>
                                @if(auth()->user()->isAdmin())
                                <form method="POST" action="{{ route('admin.campanhas.destroy',$c) }}" class="d-inline" onsubmit="return confirm('Excluir campanha? Os leads permanecem, apenas perdem o vínculo.')">@csrf@method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-secondary py-5">Nenhuma campanha cadastrada.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>
@endsection
