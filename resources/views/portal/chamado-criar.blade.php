@extends('portal.layout')

@section('title', 'Abrir chamado — Portal do cliente')
@section('page_title', 'Abrir um chamado')
@section('page_description', 'Conte o que está acontecendo. A equipe responde por aqui e avisamos no seu e-mail a cada mensagem nova.')

@section('page_actions')
<a href="{{ route('portal.chamados') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Meus chamados</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="Relato do problema" icon="fa-life-ring">
            <form method="POST" action="{{ route('portal.chamados.registrar') }}" class="row g-3" novalidate>
                @csrf
                <x-admin.field name="assunto" label="Assunto" :required="true" maxlength="180"
                    placeholder="Ex.: rastreador do placa ABC1D23 parou de reportar" />

                <x-admin.field name="prioridade" type="select" label="Prioridade" col="col-12 col-md-6"
                    :value="old('prioridade', 'normal')" :options="$prioridades"
                    hint="Use urgente só quando o veículo está impossibilitado de trabalhar." />
                <x-admin.field name="pedido_id" type="select" label="Pedido relacionado" col="col-12 col-md-6"
                    :value="$pedido ?? ''" :options="$pedidos->mapWithKeys(fn ($p) => [$p->id => $p->numero.' — '.$p->created_at->format('d/m/Y').' — '.$p->statusLabel()])->all()"
                    empty="Nenhum" />

                <x-admin.field name="corpo" type="textarea" label="O que está acontecendo" rows="7" :required="true" maxlength="4000"
                    placeholder="Descreva o veículo, o equipamento e o que já tentou fazer." />

                <div class="col-12">
                    <button type="submit" class="btn btn-golden"><i class="fas fa-paper-plane me-1"></i> Abrir chamado</button>
                    <span class="form-hint ms-2">O chamado aparece na sua lista assim que for enviado.</span>
                </div>
            </form>
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Como a equipe atende" icon="fa-circle-info">
            <ul class="timeline mb-0">
                <li>
                    <span class="fw-semibold small">Você descreve o caso</span>
                    <div class="small text-secondary">Placa, equipamento e o que já foi tentado aceleram muito.</div>
                </li>
                <li>
                    <span class="fw-semibold small">A equipe responde no chamado</span>
                    <div class="small text-secondary">Cada resposta chega no seu e-mail de acesso e fica aqui.</div>
                </li>
                <li class="atual">
                    <span class="fw-semibold small">Você confirma e fecha</span>
                    <div class="small text-secondary">Resolveu? Responda por aqui — o chamado fica como resolvido.</div>
                </li>
            </ul>
        </x-admin.card>
    </div>
</div>
@endsection
