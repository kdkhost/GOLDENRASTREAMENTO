@extends('admin.layouts.app')
@section('title', 'Criativo do anúncio')
@section('page_title', 'Criativo do anúncio responsivo')
@section('page_description', 'Títulos, descrições e a prévia do que a busca do Google desenha — com as travas de caracteres aplicadas enquanto se escreve.')

@section('page_actions')
<a href="{{ route('admin.google-ads.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chart-line me-1" aria-hidden="true"></i> Ver métricas</a>
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('css/golden-ads.css') }}?v={{ filemtime(public_path('css/golden-ads.css')) }}">
@endsection

@section('content')
<livewire:rsa-preview />
@endsection
