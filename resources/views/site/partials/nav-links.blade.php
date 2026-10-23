{{-- Links do menu — fonte única usada pelo header (desktop) e pela gaveta (mobile) --}}
<a href="{{ route('site.solucoes') }}" class="{{ request()->routeIs('site.solucoes') ? 'active' : '' }}">Soluções</a>
<a href="{{ route('site.segmentos') }}" class="{{ request()->routeIs('site.segmentos') ? 'active' : '' }}">Para quem é</a>
<a href="{{ route('site.planos') }}" class="{{ request()->routeIs('site.planos') ? 'active' : '' }}">Planos</a>
<a href="{{ route('site.como-funciona') }}" class="{{ request()->routeIs('site.como-funciona') ? 'active' : '' }}">Como funciona</a>
@if($golden['loja_ativa'])
<a href="{{ route('site.loja.index') }}" class="{{ request()->routeIs('site.loja.*') ? 'active' : '' }}">Loja</a>
@endif
@foreach($golden['menu'] as $pg)
<a href="{{ route('site.pagina', $pg->slug) }}" class="{{ request()->routeIs('site.pagina') && request()->route('slug') === $pg->slug ? 'active' : '' }}">{{ $pg->titulo }}</a>
@endforeach
<a href="{{ route('site.faq') }}" class="{{ request()->routeIs('site.faq') ? 'active' : '' }}">Dúvidas</a>
