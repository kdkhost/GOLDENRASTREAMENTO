@extends('admin.layouts.app')
@section('title', $cliente->nome)
@section('page_title', $cliente->nome)
@section('page_description', \App\Models\Customer::STATUS[$cliente->status].' · '.($cliente->tipo === 'juridica' ? 'Pessoa jurídica' : 'Pessoa física'))

@section('page_actions')
<a href="{{ route('admin.clientes.edit', $cliente) }}" class="btn btn-golden btn-sm"><i class="fas fa-pen me-1"></i> Editar ficha</a>
@if($cliente->telefone)
    <a href="https://wa.me/{{ preg_replace('/\D/', '', $cliente->telefone) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="fab fa-whatsapp me-1"></i> WhatsApp</a>
@endif
<a href="{{ route('admin.clientes.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
@php
    $iconeInteracao = [
        'ligacao' => 'fa-phone',
        'whatsapp' => 'fa-brands fa-whatsapp',
        'email' => 'fa-envelope',
        'reuniao' => 'fa-users',
        'nota' => 'fa-note-sticky',
    ];
    $linhasEndereco = array_filter([
        $cliente->endereco,
        $cliente->cidade ? $cliente->cidade.($cliente->uf ? ' / '.$cliente->uf : '') : null,
        $cliente->cep ? 'CEP '.$cliente->cep : null,
    ]);
@endphp
<div class="row g-3">
    <div class="col-lg-5">
        <x-admin.card title="Ficha do cliente" icon="fa-address-card">
            <dl class="defs mb-0">
                <dt>Documento</dt><dd>{{ $cliente->documento ?: '—' }} ({{ $cliente->tipo === 'juridica' ? 'CNPJ' : 'CPF' }})</dd>
                <dt>WhatsApp</dt><dd>{{ $cliente->telefone ?: '—' }}</dd>
                <dt>E-mail</dt><dd>{{ $cliente->email ?: '—' }}</dd>
                <dt>Endereço</dt><dd>{{ $linhasEndereco ? implode(' · ', $linhasEndereco) : '—' }}</dd>
                <dt>Segmento</dt><dd>{{ config('golden.perfis')[$cliente->segmento] ?? '—' }}</dd>
                <dt>Frota</dt><dd>{{ $cliente->frota_tamanho ? $cliente->frota_tamanho.' veículo(s)' : '—' }}</dd>
                <dt>Valor anual</dt><dd class="fw-semibold">{{ $cliente->valor_anual_cents ? brl($cliente->valor_anual_cents) : '—' }}</dd>
                <dt>Responsável</dt><dd>{{ $cliente->owner?->name ?? 'Sem responsável definido' }}</dd>
                <dt>No funil</dt><dd><span class="badge-status {{ $cliente->status === 'ativo' ? 'st-vendido' : ($cliente->status === 'inativo' ? 'st-perdido' : 'st-proposta') }}">{{ \App\Models\Customer::STATUS[$cliente->status] }}</span></dd>
                @if($cliente->lead)
                    <dt>Origem</dt><dd><a href="{{ route('admin.leads.show', $cliente->lead) }}">Lead #{{ $cliente->lead_id }}</a> — {{ $cliente->lead->name }}</dd>
                @endif
                @if($cliente->observacoes)
                    <dt>Observações</dt><dd>{{ $cliente->observacoes }}</dd>
                @endif
            </dl>
            @if(auth()->user()->isAdmin())
                <div class="border-top mt-3 pt-3">
                    <x-admin.delete :action="route('admin.clientes.destroy', $cliente)" label="Excluir cliente" confirm="Excluir o cliente {{ $cliente->nome }}? As interações e pedidos dele também saem." />
                </div>
            @endif
        </x-admin.card>
    </div>

    <div class="col-lg-7">
        <x-admin.card title="Registrar interação" icon="fa-comments" descricao="Fica na histórico do cliente e conta como atendimento do responsável.">
            <form method="POST" action="{{ route('admin.clientes.interacao', $cliente) }}" class="row g-3">
                @csrf
                <div class="col-6 col-md-4">
                    <label class="form-label" for="f-int-tipo">Tipo</label>
                    <select id="f-int-tipo" name="tipo" class="form-select" required>
                        @foreach($tipos as $chave => $rotulo)<option value="{{ $chave }}">{{ $rotulo }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-8">
                    <label class="form-label" for="f-int-assunto">Assunto</label>
                    <input id="f-int-assunto" name="assunto" class="form-control" maxlength="190" placeholder="Ex.: Envio de proposta de 12 veículos">
                </div>
                <div class="col-12">
                    <label class="form-label" for="f-int-descricao">Detalhes</label>
                    <textarea id="f-int-descricao" name="descricao" rows="2" class="form-control" maxlength="5000" placeholder="O que foi conversado, o que ficou pendente..."></textarea>
                </div>
                <div class="col-8 col-md-9">
                    <label class="form-label" for="f-int-proxima">Próxima ação <span class="form-hint">(opcional)</span></label>
                    <input type="date" id="f-int-proxima" name="proxima_acao_em" class="form-control">
                </div>
                <div class="col-4 d-flex align-items-end">
                    <button class="btn btn-golden w-100"><i class="fas fa-plus me-1"></i> Registrar</button>
                </div>
            </form>
        </x-admin.card>

        <x-admin.card title="Histórico de interações" class="mt-3">
            <ul class="timeline">
                @forelse($cliente->interactions as $i)
                    <li class="{{ $loop->first ? 'atual' : '' }}">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="fw-semibold small">
                                <i class="fas {{ $iconeInteracao[$i->tipo] ?? 'fa-comment' }} me-1 text-gold" aria-hidden="true"></i>
                                {{ $i->assunto ?: $i->tipoLabel() }}
                            </span>
                            <small class="text-secondary text-nowrap">{{ $i->created_at->format('d/m/Y H:i') }}</small>
                        </div>
                        @if($i->descricao)<div class="small text-secondary">{{ $i->descricao }}</div>@endif
                        <div class="small text-secondary">
                            {{ $i->user?->name ?: 'Sistema' }} · {{ $i->tipoLabel() }}
                            @if($i->proxima_acao_em) · <span class="text-gold fw-semibold">próxima: {{ $i->proxima_acao_em->format('d/m/Y') }}</span>@endif
                        </div>
                    </li>
                @empty
                    <li class="text-secondary small">Nenhuma interação registrada ainda.</li>
                @endforelse
            </ul>
        </x-admin.card>

        <x-admin.table class="mt-3" :headers="['Pedido', 'center:Total', 'center:Status', 'center:Data']" empty="Este cliente ainda não tem pedidos na loja.">
            @foreach($cliente->orders as $o)
                <tr>
                    <td><a href="{{ route('admin.pedidos.show', $o) }}" class="text-decoration-none fw-semibold">{{ $o->numero }}</a></td>
                    <td class="text-center small">{{ brl($o->total_cents) }}</td>
                    <td class="text-center"><span class="badge-status {{ $o->status === 'entregue' || $o->status === 'pago' ? 'st-vendido' : ($o->status === 'cancelado' ? 'st-perdido' : 'st-proposta') }}">{{ $o->statusLabel() }}</span></td>
                    <td class="text-center small text-secondary">{{ $o->created_at->format('d/m/Y') }}</td>
                </tr>
            @endforeach
        </x-admin.table>
    </div>
</div>
@endsection
