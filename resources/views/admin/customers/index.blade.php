@extends('admin.layouts.app')
@section('title', 'Clientes')
@section('page_title', 'Clientes (CRM)')
@section('page_description', 'Carteira de clientes convertidos a partir dos leads e cadastros diretos.')

@section('page_actions')
<button class="btn btn-golden btn-sm" data-bs-toggle="modal" data-bs-target="#novoCliente"><i class="fas fa-plus me-1"></i> Novo cliente</button>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card shadow-sm" style="border-radius:14px"><div class="card-body py-3"><div class="small text-secondary">Ativos</div><div class="fs-4 fw-bold">{{ $totalAtivos }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm" style="border-radius:14px"><div class="card-body py-3"><div class="small text-secondary">Prospectos</div><div class="fs-4 fw-bold">{{ \App\Models\Customer::where('status','prospecto')->count() }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm" style="border-radius:14px"><div class="card-body py-3"><div class="small text-secondary">Total na base</div><div class="fs-4 fw-bold">{{ \App\Models\Customer::count() }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm" style="border-radius:14px"><div class="card-body py-3"><div class="small text-secondary">Com pedidos</div><div class="fs-4 fw-bold">{{ \App\Models\Customer::has('orders')->count() }}</div></div></div></div>
</div>

<div class="card shadow-sm" style="border-radius:16px">
    <div class="card-header bg-body"><form method="GET" class="d-flex gap-2 flex-wrap">
        <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nome, telefone ou e-mail...">
        <select name="status" class="form-select form-select-sm" style="max-width:160px">
            <option value="">Todos os status</option>
            @foreach(\App\Models\Customer::STATUS as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
        </select>
        <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
    </form></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr class="text-secondary small"><th>Cliente</th><th>Contato</th><th>Segmento</th><th class="text-center">Frota</th><th class="text-center">Status</th><th class="text-center">Pedidos</th><th class="text-center">Interações</th></tr></thead>
            <tbody>
            @forelse($clientes as $c)
                <tr>
                    <td><a href="{{ route('admin.clientes.show',$c) }}" class="fw-semibold text-decoration-none text-body">{{ $c->nome }}</a><div class="small text-secondary">{{ $c->cidade }}{{ $c->uf ? ' / '.$c->uf : '' }}</div></td>
                    <td class="small">{{ $c->telefone }}<div class="text-secondary">{{ $c->email }}</div></td>
                    <td class="small">{{ config('golden.perfis')[$c->segmento] ?? $c->segmento ?: '—' }}</td>
                    <td class="text-center small">{{ $c->frota_tamanho ?: '—' }}</td>
                    <td class="text-center"><span class="badge-status {{ $c->status==='ativo'?'st-vendido':($c->status==='inativo'?'st-perdido':'st-proposta') }}">{{ \App\Models\Customer::STATUS[$c->status] }}</span></td>
                    <td class="text-center">{{ $c->orders_count }}</td>
                    <td class="text-center">{{ $c->interactions_count }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-5">Nenhum cliente ainda. Converta um lead marcado como "Vendido" ou cadastre acima.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="card-footer bg-body">{{ $clientes->links() }}</div>
</div>

{{-- Modal novo cliente --}}
<div class="modal fade" id="novoCliente" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><form method="POST" action="{{ route('admin.clientes.store') }}" class="modal-content">
    @csrf
    <div class="modal-header"><h5 class="modal-title">Novo cliente</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2">
            <div class="col-md-8"><label class="form-label small">Nome *</label><input name="nome" class="form-control form-control-sm" required></div>
            <div class="col-md-4"><label class="form-label small">Tipo</label><select name="tipo" class="form-select form-select-sm"><option value="fisica">Pessoa física</option><option value="juridica">Pessoa jurídica</option></select></div>
            <div class="col-md-6"><label class="form-label small">CPF/CNPJ</label><input name="documento" class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label small">Telefone</label><input name="telefone" class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label small">E-mail</label><input type="email" name="email" class="form-control form-control-sm"></div>
            <div class="col-md-6"><label class="form-label small">Endereço</label><input name="endereco" class="form-control form-control-sm"></div>
            <div class="col-md-4"><label class="form-label small">Cidade</label><input name="cidade" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small">UF</label><input name="uf" maxlength="2" class="form-control form-control-sm"></div>
            <div class="col-md-4"><label class="form-label small">Segmento</label><select name="segmento" class="form-select form-select-sm"><option value="">—</option>@foreach(config('golden.perfis') as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label small">Frota</label><input type="number" name="frota_tamanho" min="0" class="form-control form-control-sm"></div>
            <div class="col-md-5"><label class="form-label small">Status</label><select name="status" class="form-select form-select-sm">@foreach(\App\Models\Customer::STATUS as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-6 mt-2"><label class="form-label small">Valor anual (R$)</label><input type="number" step="0.01" name="valor_anual" class="form-control form-control-sm"></div>
            <div class="col-md-6 mt-2"><label class="form-label small">Responsável</label><select name="owner_id" class="form-select form-select-sm"><option value="">—</option>@foreach(\App\Models\User::whereIn('role',['admin','vendedor'])->get() as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
            <div class="col-12 mt-2"><label class="form-label small">Observações</label><textarea name="observacoes" rows="2" class="form-control form-control-sm"></textarea></div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-golden btn-sm"><i class="fas fa-save me-1"></i> Cadastrar cliente</button></div>
</form></div></div>
@endsection
