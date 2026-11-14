@extends('admin.layouts.app')
@section('title', 'Criativo do anúncio')
@section('page_title', 'Estúdio de Criativo RSA')
@section('page_description', 'Monte anúncios responsivos da busca do Google com força em tempo real, modelos prontos da Golden e prévia para desktop e celular.')

@section('page_actions')
<div class="d-flex flex-wrap gap-2">
    <a href="{{ route('admin.google-ads.copiloto.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-robot me-1" aria-hidden="true"></i> Copiloto IA
    </a>
    <a href="{{ route('admin.google-ads.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-chart-line me-1" aria-hidden="true"></i> Ver métricas
    </a>
</div>
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('css/golden-ads.css') }}?v={{ filemtime(public_path('css/golden-ads.css')) }}">
@endsection

@section('content')
<livewire:rsa-preview />
@endsection
