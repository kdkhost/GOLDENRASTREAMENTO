@extends('admin.layouts.app')
@section('title', 'Relatório do Google Ads')
@section('page_title', 'Relatório do Google Ads')
@section('page_description', 'Os números do período, campanha por campanha, com a leitura do copiloto guardada no histórico.')

@section('page_actions')
<a href="{{ route('admin.google-ads.copiloto.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-robot me-1" aria-hidden="true"></i> Copiloto</a>
<a href="{{ route('admin.google-ads.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chart-line me-1" aria-hidden="true"></i> Métricas</a>
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('css/golden-ads.css') }}?v={{ filemtime(public_path('css/golden-ads.css')) }}">
@endsection

@section('content')
<livewire:relatorio-do-google-ads />
@endsection

@push('scripts')
<script>
//O componente avisa por evento; quem fala com o visitante é o GoldenAvisos, uma vez só.
document.addEventListener('golden-aviso', function (evento) {
    var dados = evento.detail || {};
    if (!window.GoldenAvisos || !dados.texto) { return; }
    (window.GoldenAvisos[dados.tipo] || window.GoldenAvisos.info)(dados.texto);
});
</script>
@endpush
