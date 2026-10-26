@extends('admin.layouts.app')
@section('title', 'Google Ads')
@section('page_title', 'Google Ads')
@section('page_description', 'A conta conectada, o resumo do período e as campanhas — lidos da cache que a fila preenche.')

@section('page_actions')
<a href="{{ route('admin.google-ads.criativo') }}" class="btn btn-golden btn-sm"><i class="fas fa-wand-magic-sparkles me-1" aria-hidden="true"></i> Criativo do anúncio</a>
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('css/golden-ads.css') }}?v={{ filemtime(public_path('css/golden-ads.css')) }}">
{{-- O cartão "Como configurar" desta tela desenha blocos do guia — a mesma folha, o mesmo texto. --}}
<link rel="stylesheet" href="{{ asset('css/golden-guia.css') }}?v={{ filemtime(public_path('css/golden-guia.css')) }}">
@endsection

@section('content')
<livewire:google-ads-dashboard />
@endsection
