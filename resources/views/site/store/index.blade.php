@extends('layouts.site')

@section('title', 'Loja Golden — Rastreadores, chips M2M e acessórios')
@section('meta_description', 'Compre rastreadores J16 4G/2G, chips M2M (Vivo, Claro, Algar, TIM) e acessórios para sua frota. Pix, cartão ou boleto, com entrega ou retirada.')

@section('content')
<section class="sec-light" style="padding-bottom:20px">
    <div class="wrap">
        <span class="eyebrow">Loja Golden</span>
        <h1 style="font-size:2rem;margin:.2rem 0">Equipamentos e chips para o seu veículo.</h1>
        <p class="lead-sub">Rastreadores J16 4G/2G, chips M2M dos parceiros {{ implode(', ', $golden['parceiros']) }} e acessórios. Pague com Pix, cartão ou boleto e acompanhe o pedido on-line.</p>

        @if($categorias->isNotEmpty())
        <div id="storeCategoriesNav" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px">
            <a href="{{ route('site.loja.index') }}" data-store-cat-link class="btn btn-sm {{ !$categoriaAtual ? 'btn-gold' : 'btn-ghost' }}">Todos</a>
            @foreach($categorias as $c)
                <a href="{{ route('site.loja.index', ['categoria' => $c['slug']]) }}" data-store-cat-link class="btn btn-sm {{ $categoriaAtual === $c['slug'] ? 'btn-gold' : 'btn-ghost' }}">{{ $c['nome'] }} ({{ $c['total'] }})</a>
            @endforeach
        </div>
        @endif
    </div>
</section>

<section class="sec-alt" style="padding-top:20px">
    <div class="wrap" id="storeCatalogWrap">
        @if($produtos->isEmpty())
        <p class="center" style="padding:40px 0;color:var(--muted)">Nenhum produto disponível nesta categoria. Fale com um especialista para encomendar.</p>
        @else
        <div class="grid-3">
            @foreach($produtos as $p)
                @include('site.store._product-card', ['p' => $p])
            @endforeach
        </div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
@include('site.store._product-card-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const nav = document.getElementById('storeCategoriesNav');
    const catalog = document.getElementById('storeCatalogWrap');
    if (!nav || !catalog) return;

    nav.addEventListener('click', function (e) {
        const link = e.target.closest('a[data-store-cat-link]');
        if (!link) return;
        e.preventDefault();

        const url = link.href;
        nav.querySelectorAll('a[data-store-cat-link]').forEach(function (a) {
            a.classList.remove('btn-gold');
            a.classList.add('btn-ghost');
        });
        link.classList.remove('btn-ghost');
        link.classList.add('btn-gold');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const nextCatalog = doc.getElementById('storeCatalogWrap');
                if (nextCatalog) {
                    catalog.innerHTML = nextCatalog.innerHTML;
                    if (window.history && window.history.pushState) {
                        window.history.pushState({}, '', url);
                    }
                    document.dispatchEvent(new Event('DOMContentLoaded'));
                }
            })
            .catch(function () { window.location.href = url; });
    });
});
</script>
@endpush
