@extends('admin.layouts.app')
@section('title', $cliente->nome)
@section('page_title', $cliente->nome)
@section('page_description', \App\Models\Customer::STATUS[$cliente->status] . ' · ' . ($cliente->tipo==='juridica'?'Pessoa jurídica':'Pessoa física'))

@section('page_actions')
<a href="{{ route('admin.clientes.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    {{-- Ficha + edição --}}
    <div class="col-lg-5">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="fas fa-address-card text-warning me-1"></i> Ficha do cliente</h6>
            <form method="POST" action="{{ route('admin.clientes.update',$cliente) }}">
                @csrf
                <div class="row g-2">
                    <div class="col-12"><label class="form-label small">Nome</label><input name="nome" value="{{ $cliente->nome }}" class="form-control form-control-sm" required></div>
                    <div class="col-7"><label class="form-label small">Documento</label><input name="documento" value="{{ $cliente->documento }}" class="form-control form-control-sm"></div>
                    <div class="col-5"><label class="form-label small">Tipo</label><select name="tipo" class="form-select form-select-sm"><option value="fisica" @selected($cliente->tipo==='fisica')>Física</option><option value="juridica" @selected($cliente->tipo==='juridica')>Jurídica</option></select></div>
                    <div class="col-6"><label class="form-label small">Telefone</label><input name="telefone" value="{{ $cliente->telefone }}" class="form-control form-control-sm"></div>
                    <div class="col-6"><label class="form-label small">E-mail</label><input name="email" value="{{ $cliente->email }}" class="form-control form-control-sm"></div>
                    <div class="col-12"><label class="form-label small">Endereço</label><input name="endereco" value="{{ $cliente->endereco }}" class="form-control form-control-sm"></div>
                    <div class="col-6"><label class="form-label small">Cidade</label><input name="cidade" value="{{ $cliente->cidade }}" class="form-control form-control-sm"></div>
                    <div class="col-3"><label class="form-label small">UF</label><input name="uf" maxlength="2" value="{{ $cliente->uf }}" class="form-control form-control-sm"></div>
                    <div class="col-3"><label class="form-label small">CEP</label><input name="cep" value="{{ $cliente->cep }}" class="form-control form-control-sm"></div>
                    <div class="col-4"><label class="form-label small">Segmento</label><select name="segmento" class="form-select form-select-sm"><option value="">—</option>@foreach(config('golden.perfis') as $k=>$v)<option value="{{ $k }}" @selected($cliente->segmento===$k)>{{ $v }}</option>@endforeach</select></div>
                    <div class="col-4"><label class="form-label small">Frota</label><input type="number" name="frota_tamanho" value="{{ $cliente->frota_tamanho }}" min="0" class="form-control form-control-sm"></div>
                    <div class="col-4"><label class="form-label small">Status</label><select name="status" class="form-select form-select-sm">@foreach(\App\Models\Customer::STATUS as $k=>$v)<option value="{{ $k }}" @selected($cliente->status===$k)>{{ $v }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label small">Valor anual (R$)</label><input type="number" step="0.01" name="valor_anual" value="{{ $cliente->valor_anual_cents ? $cliente->valor_anual_cents/100 : '' }}" class="form-control form-control-sm"></div>
                    <div class="col-6"><label class="form-label small">Responsável</label><select name="owner_id" class="form-select form-select-sm"><option value="">—</option>@foreach(\App\Models\User::whereIn('role',['admin','vendedor'])->get() as $u)<option value="{{ $u->id }}" @selected($cliente->owner_id==$u->id)>{{ $u->name }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label small">Observações</label><textarea name="observacoes" rows="2" class="form-control form-control-sm">{{ $cliente->observacoes }}</textarea></div>
                </div>
                <button class="btn btn-golden btn-sm mt-3"><i class="fas fa-save me-1"></i> Salvar ficha</button>
            </form>
            @if($cliente->lead)
            <div class="small text-secondary mt-3 border-top pt-2"><i class="fas fa-link me-1"></i> Origem: <a href="{{ route('admin.leads.show',$cliente->lead) }}">Lead #{{ $cliente->lead_id }}</a></div>
            @endif
        </div></div>
    </div>

    {{-- Interações + histórico --}}
    <div class="col-lg-7">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="fas fa-comments text-warning me-1"></i> Registrar interação</h6>
            <form method="POST" action="{{ route('admin.clientes.interacao',$cliente) }}" class="row g-2">
                @csrf
                <div class="col-5"><select name="tipo" class="form-select form-select-sm">@foreach($tipos as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="col-7"><input name="assunto" class="form-control form-control-sm" placeholder="Assunto"></div>
                <div class="col-12"><textarea name="descricao" rows="2" class="form-control form-control-sm" placeholder="Detalhes do contato..."></textarea></div>
                <div class="col-8"><label class="form-label small mb-0">Próxima ação (opcional)</label><input type="date" name="proxima_acao_em" class="form-control form-control-sm"></div>
                <div class="col-4 d-flex align-items-end"><button class="btn btn-golden btn-sm w-100"><i class="fas fa-plus me-1"></i> Registrar</button></div>
            </form>
        </div></div>

        <div class="card shadow-sm mt-3" style="border-radius:16px">
            <div class="card-header bg-body fw-bold">Histórico de interações</div>
            <div class="card-body">
                @forelse($cliente->interactions as $i)
                <div class="d-flex gap-3 pb-3 mb-3 border-bottom">
                    <div class="text-center" style="min-width:44px">
                        <i class="fas {{ ['ligacao'=>'fa-phone','whatsapp'=>'fa-brands fa-whatsapp','email'=>'fa-envelope','reuniao'=>'fa-users','nota'=>'fa-note-sticky'][$i->tipo] ?? 'fa-comment' }} fs-5 text-warning"></i>
                        <div class="small text-secondary" style="font-size:.7rem">{{ $i->created_at->format('d/m') }}</div>
                    </div>
                    <div>
                        <div class="fw-semibold small">{{ $i->assunto ?: $i->tipoLabel() }} <span class="badge bg-body-secondary text-secondary ms-1">{{ $i->tipoLabel() }}</span></div>
                        @if($i->descricao)<p class="small mb-1">{{ $i->descricao }}</p>@endif
                        <div class="small text-secondary">{{ $i->user?->name ?: 'Sistema' }} · {{ $i->created_at->format('d/m/Y H:i') }}@if($i->proxima_acao_em) · próxima: {{ $i->proxima_acao_em->format('d/m/Y') }}@endif</div>
                    </div>
                </div>
                @empty
                <p class="text-secondary small mb-0">Nenhuma interação registrada ainda.</p>
                @endforelse
            </div>
        </div>

        <div class="card shadow-sm mt-3" style="border-radius:16px">
            <div class="card-header bg-body d-flex justify-content-between align-items-center"><span class="fw-bold">Pedidos</span><span class="badge bg-body-secondary text-body">{{ $cliente->orders->count() }}</span></div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr class="text-secondary small"><th>Pedido</th><th class="text-center">Total</th><th class="text-center">Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse($cliente->orders as $o)
                        <tr><td><a href="{{ route('admin.pedidos.show',$o) }}" class="text-decoration-none">{{ $o->numero }}</a><div class="small text-secondary">{{ $o->created_at->format('d/m/Y') }}</div></td>
                        <td class="text-center small">{{ brl($o->total_cents) }}</td>
                        <td class="text-center"><span class="badge-status {{ $o->status==='entregue'||$o->status==='pago'?'st-vendido':($o->status==='cancelado'?'st-perdido':'st-proposta') }}">{{ $o->statusLabel() }}</span></td>
                        <td></td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-secondary py-4">Sem pedidos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div></div>
        </div>
    </div>
</div>
@endsection
