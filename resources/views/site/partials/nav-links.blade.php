{{-- Menu em dois níveis — fonte única usada pelo header (dropdown) e pela gaveta (acordeão) --}}
@php $escopo = $escopo ?? 'nav'; @endphp
@foreach($golden['nav'] as $nv)
  @php $temFilhos = !empty($nv['filhos']); $sub = 'sub-' . $escopo . '-' . $loop->index; @endphp
  <div class="nav-item{{ $temFilhos ? ' has-sub' : '' }}{{ $nv['ativo'] ? ' current' : '' }}">
    @if($nv['url'])
      <a href="{{ $nv['url'] }}" class="nav-link">{{ $nv['titulo'] }}@if($temFilhos) <i class="nav-arrow fa-solid fa-chevron-down" aria-hidden="true"></i>@endif</a>
    @else
      <button type="button" class="nav-link nav-group" data-sub-toggle aria-expanded="false" aria-controls="{{ $sub }}">{{ $nv['titulo'] }} <i class="nav-arrow fa-solid fa-chevron-down" aria-hidden="true"></i></button>
    @endif
    @if($temFilhos)
      @if($nv['url'])
        <button type="button" class="nav-caret" data-sub-toggle aria-expanded="false" aria-controls="{{ $sub }}" aria-label="Abrir os itens de {{ $nv['titulo'] }}"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
      @endif
      <div class="nav-sub" id="{{ $sub }}">
        @foreach($nv['filhos'] as $f)
          <a href="{{ $f['url'] }}" class="{{ $f['ativo'] ? 'active' : '' }}">{{ $f['titulo'] }}</a>
        @endforeach
      </div>
    @endif
  </div>
@endforeach
