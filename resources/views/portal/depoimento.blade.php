@extends('portal.layout')
@php
    $notas = [5 => '5 estrelas', 4 => '4 estrelas', 3 => '3 estrelas', 2 => '2 estrelas', 1 => '1 estrela'];
@endphp

@section('title', 'Deixar um depoimento — Portal do cliente')
@section('page_title', 'Deixar um depoimento')
@section('page_description', 'Conte como o rastreamento trabalhou para você. A equipe aprova e o relato entra no carrossel do site junto com os outros.')

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        @if(!$podeEscrever)
            <x-admin.card title="Depoimento indisponível agora" icon="fa-triangle-exclamation">
                <p class="mb-2">Só a ficha ativa na Golden publica um depoimento pelo portal. O seu cadastro está como <strong>{{ $cliente->status }}</strong>.</p>
                <p class="form-hint mb-3">Fale com a equipe pelo <a href="{{ route('site.contato') }}">formulário de contato</a> ou pelo <a href="{{ route('portal.chamados.criar') }}">chamado</a> para reativar a conta — reativada, a escrita abre aqui.</p>
                <a href="{{ route('portal.inicio') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar ao início</a>
            </x-admin.card>
        @else
            <x-admin.card title="Seu relato" icon="fa-quote-left" descricao="Quem lê no site vê o primeiro nome, a cidade e a nota — igual às avaliações do Google.">
                @if($aguardando)
                    <div class="alert alert-warning py-2 px-3 small mb-3">
                        <i class="fas fa-hourglass-half me-1" aria-hidden="true"></i>
                        Já temos um depoimento seu na fila da aprovação. Quando ele for publicado, você pode escrever outro aqui.
                    </div>
                @else
                    <form method="POST" action="{{ route('portal.depoimento.enviar') }}" class="row g-3" novalidate>
                        @csrf
                        <x-admin.field name="nome" label="Nome que aparece no site" col="col-12 col-md-7" :value="$cliente->nome" :required="true" maxlength="120"
                            hint="Pode ser o primeiro nome — é assim que a maioria prefere." />
                        <x-admin.field name="nota" type="select" label="Nota" col="col-12 col-md-5" :value="old('nota', 5)" :options="$notas" :required="true" />

                        <x-admin.field name="papel" label="Papel / segmento" col="col-12 col-md-5" :opcional="true" maxlength="120"
                            placeholder="Transportador autônomo" />
                        <div class="col-12 col-md-7">
                            <div class="alert alert-secondary py-2 px-3 small mb-0">
                                <i class="fas fa-location-dot me-1 text-gold" aria-hidden="true"></i>
                                Cidade que aparece: <strong>{{ $cliente->cidade ?: 'não cadastrada' }}</strong>
                                — vem da sua ficha e muda em <a href="{{ route('portal.dados') }}">Meus dados</a>.
                            </div>
                        </div>

                        <x-admin.field name="texto" type="textarea" label="O que você quer contar" :rows="6" :required="true" maxlength="1200"
                            placeholder="Ex.: instalei o J16 na frota em março e o que mais ajudou foi o relatório de percurso..." />

                        <div class="col-12">
                            <button type="submit" class="btn btn-golden" @if($aguardando) disabled @endif>
                                <i class="fas fa-paper-plane me-1"></i> Enviar depoimento
                            </button>
                            <span class="form-hint ms-2">Mínimo de 20 caracteres. A equipe aprova antes de ir ao ar.</span>
                        </div>
                    </form>
                @endif
            </x-admin.card>
        @endif
    </div>

    <div class="col-lg-4">
        <x-admin.card title="O caminho do seu texto" icon="fa-circle-info">
            <ul class="timeline mb-0">
                <li>
                    <span class="fw-semibold small">Você escreve</span>
                    <div class="small text-secondary">Uma ou duas frases do que mudou no dia a dia já servem.</div>
                </li>
                <li>
                    <span class="fw-semibold small">A equipe aprova</span>
                    <div class="small text-secondary">Nada vai ao ar sem leitura — é o que mantém o carrossel honesto.</div>
                </li>
                <li class="atual">
                    <span class="fw-semibold small">Aparece misturado no site</span>
                    <div class="small text-secondary">Depoimentos do portal, do painel e as avaliações do Google formam o mesmo carrossel.</div>
                </li>
            </ul>
        </x-admin.card>

        <x-admin.card title="O que você já enviou" icon="fa-clock-rotate-left" class="mt-3">
            @forelse($meus as $d)
                <div class="d-flex justify-content-between align-items-start gap-2 py-2 border-bottom">
                    <div>
                        <span class="fw-semibold small">{{ str($d->texto)->limit(80) }}</span>
                        <div class="small text-secondary">{{ $d->created_at->format('d/m/Y') }} · {{ $d->nota }} {{ $d->nota > 1 ? 'estrelas' : 'estrela' }}</div>
                    </div>
                    <x-admin.status :rotulo="$d->aprovado ? 'no ar' : 'aguardando'" :tom="tom_por_flag($d->aprovado)" />
                </div>
            @empty
                <p class="form-hint mb-0">Você ainda não enviou nenhum depoimento.</p>
            @endforelse
        </x-admin.card>
    </div>
</div>
@endsection
