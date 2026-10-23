@extends('admin.layouts.app')
@section('title', 'Produtos')
@section('page_title', 'Produtos e estoque')
@section('page_description', 'Catálogo da loja: rastreadores, chips M2M, acessórios e planos.')

@section('page_actions')
<a href="{{ route('admin.catalogo.produtos.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Novo produto</a>
@endsection

@section('content')
<div class="card shadow-sm" style="border-radius:16px">
    <div class="card-header bg-body d-flex flex-wrap gap-2 align-items-center">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Buscar produto...">
            <select name="categoria" class="form-select form-select-sm">
                <option value="">Todas categorias</option>
                @foreach($categorias as $c)<option value="{{ $c->id }}" @selected(request('categoria')==$c->id)>{{ $c->nome }}</option>@endforeach
            </select>
            <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-filter"></i></button>
        </form>
    </div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr class="text-secondary small"><th>Produto</th><th>Categoria</th><th class="text-center">Preço</th><th class="text-center">Estoque</th><th class="text-center">Loja</th><th></th></tr></thead>
            <tbody>
            @forelse($produtos as $p)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded bg-body-secondary d-flex align-items-center justify-content-center" style="width:38px;height:38px;overflow:hidden">
                                @if($p->imagem)<img src="{{ asset('storage/'.$p->imagem) }}" style="width:100%;height:100%;object-fit:cover" alt="">@else<i class="fas fa-box text-secondary"></i>@endif
                            </div>
                            <div><span class="fw-semibold">{{ $p->nome }}</span><div class="small text-secondary">{{ $p->sku ?: '—' }}</div></div>
                        </div>
                    </td>
                    <td class="small">{{ $p->category?->nome ?: '—' }}</td>
                    <td class="text-center small">{{ brl($p->preco()) }}@if($p->mensalidade_cents)<div class="text-secondary">+{{ brl($p->mensalidade_cents) }}/mês</div>@endif</td>
                    <td class="text-center">
                        @if(!$p->controle_estoque)<span class="badge bg-body-secondary text-secondary">livre</span>
                        @elseif($p->estoque > $p->estoque_minimo)<span class="badge-status st-vendido">{{ $p->estoque }}</span>
                        @elseif($p->estoque > 0)<span class="badge bg-warning text-dark">{{ $p->estoque }}</span>
                        @else<span class="badge bg-danger">0</span>@endif
                    </td>
                    <td class="text-center"><span class="badge bg-body-secondary text-body">{{ $p->ativo ? 'ativo':'oculto' }}</span></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#estoque{{ $p->id }}" title="Movimentar estoque"><i class="fas fa-boxes-stacked"></i></button>
                        <a href="{{ route('admin.catalogo.produtos.edit',$p) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                        <form method="POST" action="{{ route('admin.catalogo.produtos.destroy',$p) }}" class="d-inline" onsubmit="return confirm('Excluir produto {{ $p->nome }}?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                    </td>
                </tr>
                {{-- Modal estoque --}}
                <div class="modal fade" id="estoque{{ $p->id }}" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('admin.catalogo.produtos.estoque',$p) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Estoque: {{ $p->nome }}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="small text-secondary mb-3">Saldo atual: <strong>{{ $p->estoque }}</strong> un.</p>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label small">Tipo</label><select name="tipo" class="form-select form-select-sm"><option value="entrada">Entrada</option><option value="saida">Saída</option></select></div>
                            <div class="col-6"><label class="form-label small">Quantidade</label><input type="number" name="quantidade" min="1" value="1" class="form-control form-control-sm" required></div>
                        </div>
                        <div class="mt-2"><label class="form-label small">Motivo</label><input name="motivo" class="form-control form-control-sm" placeholder="Ex.: compra, venda avulsa, ajuste"></div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-sm btn-golden"><i class="fas fa-save me-1"></i> Registrar</button></div>
                </form></div></div>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-5">Nenhum produto. <a href="{{ route('admin.catalogo.produtos.create') }}">Cadastre o primeiro</a>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
</div>
@endsection
