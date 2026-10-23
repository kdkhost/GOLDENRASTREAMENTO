@extends('admin.layouts.app')
@section('title', 'Pedido ' . $pedido->numero)
@section('page_title', 'Pedido ' . $pedido->numero)
@section('page_description', $pedido->nome . ' · ' . $pedido->created_at->format('d/m/Y H:i'))

@section('page_actions')
<a href="{{ route('admin.pedidos.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm" style="border-radius:16px">
            <div class="card-header bg-body fw-bold">Itens do pedido</div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr class="text-secondary small"><th>Produto</th><th class="text-center">Qtd</th><th class="text-center">Unitário</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                    @foreach($pedido->items as $it)
                        <tr>
                            <td>{{ $it->nome }}@if($it->product)<div class="small text-secondary">{{ $it->product->sku }}</div>@endif</td>
                            <td class="text-center">{{ $it->quantidade }}</td>
                            <td class="text-center small">{{ brl($it->preco_unit_cents) }}</td>
                            <td class="text-end small fw-semibold">{{ brl($it->subtotal_cents) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="border-top">
                        <tr><td colspan="3" class="text-end text-secondary small">Subtotal</td><td class="text-end small">{{ brl($pedido->subtotal_cents) }}</td></tr>
                        @if($pedido->frete_cents)<tr><td colspan="3" class="text-end text-secondary small">Frete</td><td class="text-end small">{{ brl($pedido->frete_cents) }}</td></tr>@endif
                        @if($pedido->desconto_cents)<tr><td colspan="3" class="text-end text-secondary small">Desconto</td><td class="text-end small">- {{ brl($pedido->desconto_cents) }}</td></tr>@endif
                        <tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end text-gold">{{ brl($pedido->total_cents) }}</td></tr>
                    </tfoot>
                </table>
            </div></div>
        </div>

        @if($pedido->notas)
        <div class="card shadow-sm mt-3" style="border-radius:16px"><div class="card-body">
            <h6 class="fw-bold">Observações do cliente</h6><p class="small mb-0">{{ $pedido->notas }}</p>
        </div></div>
        @endif

        <div class="card shadow-sm mt-3" style="border-radius:16px"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="fas fa-paperclip text-warning me-1"></i> Documentos do pedido</h6>
            <form method="POST" action="{{ route('admin.pedidos.documentos',$pedido) }}">
                @csrf
                @include('partials.upload', [
                    'campo' => 'pedido_documento',
                    'name' => 'documentos',
                    'valor' => old('documentos', $pedido->documentos ?? []),
                    'multiplo' => true,
                    'dica' => 'Ficam no painel; o cliente acompanha o pedido pela linha do tempo.',
                ])
                <button class="btn btn-golden btn-sm mt-2"><i class="fas fa-save me-1"></i> Salvar documentos</button>
            </form>
        </div></div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm" style="border-radius:16px"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="fas fa-user text-warning me-1"></i> Comprador e entrega</h6>
            <p class="small mb-1"><strong>{{ $pedido->nome }}</strong></p>
            <p class="small text-secondary mb-1">{{ $pedido->telefone }} @if($pedido->email)· {{ $pedido->email }}@endif</p>
            @if($pedido->documento)
            <p class="small text-secondary mb-1"><i class="fas fa-id-card me-1"></i> {{ $pedido->documentoFormatado() }} · {{ $pedido->tipo_pessoa === 'juridica' ? 'pessoa jurídica' : 'pessoa física' }}</p>
            @endif
            <p class="small text-secondary mb-1"><i class="fas fa-truck me-1"></i> {{ $pedido->entregaLabel() }}</p>
            @if($pedido->entrega !== 'retira')
            <p class="small text-secondary">{{ $pedido->enderecoFormatado() }}</p>
            @endif
            @if($pedido->consent_given)
            <p class="small text-success"><i class="fas fa-shield-halved me-1"></i> Consentimento LGPD registrado em {{ $pedido->created_at->format('d/m/Y H:i') }}</p>
            @endif

            <div class="border-top pt-2 mt-2">
                <label class="form-label small mb-1">Link público do pedido (o cliente acompanha por aqui)</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control font-monospace" readonly value="{{ $pedido->registroUrl() }}" id="publicLink">
                    <button class="btn btn-outline-secondary" type="button" onclick="const i=document.getElementById('publicLink');i.select();navigator.clipboard&&navigator.clipboard.writeText(i.value);this.innerHTML='ok'"><i class="fas fa-copy"></i></button>
                </div>
            </div>

            @if($pedido->customer)
            <div class="border-top pt-2 mt-2 small"><i class="fas fa-users me-1"></i> Cliente no CRM: <a href="{{ route('admin.clientes.show',$pedido->customer) }}">{{ $pedido->customer->nome }}</a></div>
            @else
            <form method="POST" action="{{ route('admin.clientes.store') }}" class="border-top pt-2 mt-2">
                @csrf
                <input type="hidden" name="nome" value="{{ $pedido->nome }}">
                <input type="hidden" name="telefone" value="{{ $pedido->telefone }}">
                <input type="hidden" name="email" value="{{ $pedido->email }}">
                <input type="hidden" name="documento" value="{{ $pedido->documento }}">
                <input type="hidden" name="endereco" value="{{ $pedido->endereco }}">
                <input type="hidden" name="cidade" value="{{ $pedido->cidade }}">
                <input type="hidden" name="uf" value="{{ $pedido->uf }}">
                <input type="hidden" name="cep" value="{{ $pedido->cep }}">
                <input type="hidden" name="tipo" value="{{ $pedido->tipo_pessoa ?: 'fisica' }}">
                <input type="hidden" name="status" value="ativo">
                <input type="hidden" name="valor_anual" value="{{ $pedido->total_cents/100 }}">
                <button class="btn btn-sm btn-outline-success"><i class="fas fa-user-plus me-1"></i> Criar cliente deste pedido</button>
            </form>
            @endif
        </div></div>

        <div class="card shadow-sm mt-3" style="border-radius:16px"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="fas fa-timeline text-warning me-1"></i> Linha do tempo</h6>
            <ul class="list-unstyled small mb-0" style="position:relative;padding-left:18px">
                <span style="position:absolute;left:4px;top:6px;bottom:6px;width:2px;background:rgba(0,0,0,.1)"></span>
                @forelse($pedido->histories->sortByDesc('created_at') as $h)
                <li style="position:relative;padding:0 0 12px">
                    <span style="position:absolute;left:-18px;top:5px;width:9px;height:9px;border-radius:50%;background:{{ $loop->first ? '#e0a33e' : '#319e86' }}"></span>
                    <strong>{{ $h->statusLabel() }}</strong>
                    <span class="text-secondary">· {{ $h->created_at->format('d/m/Y H:i') }}</span>
                    @if($h->observacao)<div class="text-secondary">{{ $h->observacao }}</div>@endif
                    @if($h->user)<div class="text-secondary fst-italic">por {{ $h->user->name }}</div>@endif
                </li>
                @empty
                <li class="text-secondary">Nenhum marco registrado.</li>
                @endforelse
            </ul>
        </div></div>

        <div class="card shadow-sm mt-3" style="border-radius:16px"><div class="card-body">
            <h6 class="fw-bold mb-3">Gerenciar pedido</h6>
            <form method="POST" action="{{ route('admin.pedidos.status',$pedido) }}">
                @csrf
                <div class="row g-2">
                    <div class="col-6"><label class="form-label small">Status</label><select name="status" class="form-select form-select-sm">@foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected($pedido->status===$k)>{{ $v }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label small">Pagamento</label><select name="pagamento" class="form-select form-select-sm">@foreach(\App\Models\Order::PAGAMENTOS as $k=>$v)<option value="{{ $k }}" @selected($pedido->pagamento===$k)>{{ $v }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label small">Recado para a linha do tempo do cliente (opcional)</label><input name="observacao" class="form-control form-control-sm" maxlength="255" placeholder="Ex.: Pagamento confirmado, enviamos hoje."></div>
                    <div class="col-12"><label class="form-label small">Notas internas</label><textarea name="notas" rows="3" class="form-control form-control-sm">{{ $pedido->notas }}</textarea></div>
                </div>
                <button class="btn btn-golden btn-sm mt-3"><i class="fas fa-save me-1"></i> Atualizar pedido</button>
            </form>
            @if(auth()->user()->isAdmin())
            <form method="POST" action="{{ route('admin.pedidos.destroy',$pedido) }}" class="mt-2" onsubmit="return confirm('Excluir o pedido {{ $pedido->numero }}?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-trash me-1"></i> Excluir pedido</button></form>
            @endif
        </div></div>
    </div>
</div>
@endsection
