@extends('admin.layouts.app')
@php
    $rotulosFonte = ['site' => 'Painel', 'google' => 'Google', 'cliente' => 'Portal'];
    $iconesFonte = ['site' => 'fa-pen-nib', 'google' => 'fa-brands fa-google', 'cliente' => 'fa-user-pen'];
@endphp
@section('title', 'Depoimentos')
@section('page_title', 'Depoimentos de clientes')
@section('page_description', 'Painel, Google e portal se misturam no mesmo carrossel da home — o que decide o ar é a aprovação.')

@section('page_actions')
<form method="POST" action="{{ route('admin.conteudo.depoimentos.importar') }}" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-sm btn-outline-secondary"
        title="{{ $google_configurado ? 'Ler as avaliações do perfil no Google e atualizar as que já estão aqui' : 'Faltam GOOGLE_BUSINESS_ACCOUNT, GOOGLE_BUSINESS_LOCATION e GOOGLE_BUSINESS_TOKEN no .env' }}">
        <i class="fa-brands fa-google me-1"></i> Atualizar do Google
    </button>
</form>
<a href="{{ route('admin.conteudo.depoimentos.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Novo depoimento</a>
@endsection

@section('content')
@if($pendentes > 0)
<div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2">
    <i class="fas fa-hourglass-half" aria-hidden="true"></i>
    <span><strong>{{ $pendentes }}</strong> depoimento(s) aguardando a sua aprovação.</span>
    <a href="{{ route('admin.conteudo.depoimentos.index', ['situacao' => 'pendente']) }}" class="btn btn-sm btn-outline-secondary ms-auto">Ver os pendentes</a>
</div>
@endif

@if(!$google_configurado)
<div class="alert alert-secondary py-2 small">
    <i class="fa-brands fa-google me-1" aria-hidden="true"></i>
    A leitura das avaliações do Google usa três chaves do <code>.env</code>:
    <code>GOOGLE_BUSINESS_ACCOUNT</code>, <code>GOOGLE_BUSINESS_LOCATION</code> e <code>GOOGLE_BUSINESS_TOKEN</code>.
    Enquanto elas não existirem, o botão "Atualizar do Google" e o <code>php artisan depoimentos:google</code> avisam em vez de chamar a API.
</div>
@endif

<x-admin.filters :reset="route('admin.conteudo.depoimentos.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-4" placeholder="Nome, papel, cidade ou texto..." />
    <x-admin.filter name="fonte" type="select" label="Origem" col="col-6 col-md-3" :options="$rotulosFonte" empty="Todas" />
    <x-admin.filter name="nota" type="select" label="Nota" col="col-6 col-md-2" :options="['5' => '5 estrelas', '4' => '4 estrelas', '3' => '3 estrelas', '2' => '2 estrelas', '1' => '1 estrela']" empty="Todas" />
    <x-admin.filter name="situacao" type="select" label="Situação" col="col-6 col-md-3" :options="['aprovado' => 'Visível', 'pendente' => 'Oculto']" empty="Todas" />
</x-admin.filters>

<x-admin.table :headers="['Depoimento', 'center:Origem', 'center:Nota', 'center:Status', 'end:Ações']"
    empty="Nenhum depoimento com esse filtro."
    :paginator="$depoimentos">
    @foreach($depoimentos as $d)
        <tr>
            <td>
                <div class="d-flex align-items-start gap-2">
                    @if($d->avatarUrl())
                        <img src="{{ $d->avatarUrl() }}" alt="" width="34" height="34" loading="lazy" class="thumb-avatar">
                    @else
                        <span class="avatar-letter">{{ mb_substr($d->iniciais(), 0, 1) }}</span>
                    @endif
                    <div>
                        <div class="fw-semibold">{{ $d->nome }} <span class="text-secondary small">· {{ $d->papel ?: $d->cidade }}</span></div>
                        <div class="small text-secondary">“{{ Str::limit($d->texto, 90) }}”</div>
                        @if($d->customer)
                            <div class="small text-secondary"><i class="fas fa-id-card me-1" aria-hidden="true"></i>Ficha: {{ $d->customer->nome }}</div>
                        @endif
                    </div>
                </div>
            </td>
            <td class="text-center">
                <span class="badge-status tone-neutral"><i class="{{ $iconesFonte[$d->fonte] ?? 'fa-pen-nib' }} me-1" aria-hidden="true"></i>{{ $rotulosFonte[$d->fonte] ?? 'Painel' }}</span>
            </td>
            <td class="text-center text-warning small">{{ str_repeat('★', $d->nota) }}</td>
            <td class="text-center"><x-admin.status :rotulo="$d->aprovado ? 'visível' : 'oculto'" :tom="tom_por_flag($d->aprovado)" /></td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.conteudo.depoimentos.edit', $d) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    @can('admin')
                        <x-admin.delete :action="route('admin.conteudo.depoimentos.destroy', $d)" confirm="Remover este depoimento?" />
                    @endcan
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
