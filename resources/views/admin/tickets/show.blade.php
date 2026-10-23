@extends('admin.layouts.app')
@section('title', 'Chamado '.$chamado->numero)
@section('page_title', $chamado->assunto)
@section('page_description', $chamado->numero.' · aberto por '.($chamado->customer?->nome ?? 'cliente').' pelo '.$chamado->canalLabel())

@section('page_actions')
@if($chamado->customer)
    <a href="{{ route('admin.clientes.show', $chamado->customer) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-address-card me-1"></i> Ficha do cliente</a>
@endif
<a href="{{ route('admin.chamados.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="Conversa" icon="fa-comments"
            :descricao="$chamado->messages->count().' mensagem(ns) — a primeira é o relato que abriu o chamado'">
            <div class="thread">
                @foreach($chamado->messages as $m)
                    <div class="msg {{ $m->daEquipe() ? 'equipe' : 'cliente' }}">
                        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
                            <span class="fw-semibold small">
                                <i class="fas {{ $m->daEquipe() ? 'fa-headset' : 'fa-user' }} me-1 text-gold" aria-hidden="true"></i>
                                {{ $m->autor() }}
                            </span>
                            <small class="text-secondary">{{ $m->created_at->format('d/m/Y H:i') }}</small>
                        </div>
                        <div class="small mt-1" style="white-space:pre-wrap">{{ $m->corpo }}</div>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('admin.chamados.responder', $chamado) }}" class="row g-3 mt-2">
                @csrf
                <x-admin.field name="corpo" type="textarea" label="Responder ao cliente" rows="4" :required="true"
                    placeholder="O que a equipe vai dizer. O cliente recebe por e-mail e vê aqui no portal." />
                <div class="col-12">
                    <button class="btn btn-golden btn-sm"><i class="fas fa-paper-plane me-1"></i> Enviar resposta</button>
                    <span class="form-hint ms-2">Vai para o e-mail de acesso do cliente e marca o chamado como "aguardando você".</span>
                </div>
            </form>
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Atendimento" icon="fa-sliders">
            <form method="POST" action="{{ route('admin.chamados.status', $chamado) }}" class="row g-3">
                @csrf
                <x-admin.field name="status" type="select" label="Situação" col="col-6" :value="$chamado->status" :options="$statuses" />
                <x-admin.field name="prioridade" type="select" label="Prioridade" col="col-6" :value="$chamado->prioridade" :options="$prioridades" />
                <x-admin.field name="owner_id" type="select" label="Responsável" :value="$chamado->owner_id" :options="$responsaveis" empty="Sem responsável" />
                <div class="col-12">
                    <button class="btn btn-golden btn-sm"><i class="fas fa-save me-1"></i> Atualizar chamado</button>
                </div>
            </form>

            <dl class="defs mt-3 mb-0">
                <dt>Aberto em</dt><dd>{{ $chamado->created_at->format('d/m/Y H:i') }}</dd>
                <dt>Primeira resposta</dt><dd>{{ $chamado->respondido_em ? $chamado->respondido_em->format('d/m/Y H:i') : 'ainda sem resposta' }}</dd>
                <dt>Resolvido em</dt><dd>{{ $chamado->resolvido_em ? $chamado->resolvido_em->format('d/m/Y H:i') : '—' }}</dd>
                @if($chamado->order)
                    <dt>Pedido</dt><dd><a href="{{ route('admin.pedidos.show', $chamado->order) }}">{{ $chamado->order->numero }}</a> — {{ $chamado->order->statusLabel() }}</dd>
                @endif
            </dl>

            @can('admin')
                <div class="border-top mt-3 pt-3">
                    <x-admin.delete :action="route('admin.chamados.destroy', $chamado)" label="Excluir chamado" confirm="Excluir o chamado {{ $chamado->numero }} e toda a conversa?" />
                </div>
            @endcan
        </x-admin.card>

        <x-admin.card title="Cliente" icon="fa-address-card" class="mt-3">
            <dl class="defs mb-0">
                <dt>Nome</dt><dd>{{ $chamado->customer?->nome ?? 'Ficha removida' }}</dd>
                <dt>WhatsApp</dt><dd>{{ $chamado->customer?->telefone ?: '—' }}</dd>
                <dt>E-mail de acesso</dt><dd>{{ $chamado->customer?->email_portal ?: 'Sem conta no portal' }}</dd>
                <dt>Documento</dt><dd>{{ $chamado->customer?->documento ?: '—' }}</dd>
            </dl>
            @if($chamado->customer?->telefone)
                <a href="{{ wa_url('Chamado '.$chamado->numero.' — '.$chamado->assunto, $chamado->customer->telefone) }}" target="_blank" rel="noopener"
                   class="btn btn-sm btn-outline-success w-100 mt-2"><i class="fab fa-whatsapp me-1"></i> Chamar no WhatsApp</a>
            @endif
        </x-admin.card>
    </div>
</div>
@endsection
