{{-- O sino do topo. Renderizado no servidor com as não lidas desta pessoa e atualizado
     por public/js/admin-notificacoes.js, que consulta admin.notificacoes.sino. O mesmo
     formato de item vem dos dois lados (Notificacao::paraSino()) para a linha não mudar
     de aparência depois da primeira atualização. --}}
<li class="nav-item dropdown sino" data-sino
    data-sino-url="{{ $sino['url'] }}"
    data-sino-visivel="{{ $sino['intervalo_visivel'] }}"
    data-sino-escondida="{{ $sino['intervalo_escondida'] }}">
    <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown"
        aria-expanded="false" aria-label="Notificações">
        <i class="fas fa-bell" aria-hidden="true"></i>
        <span class="sino-contador {{ $sino['total'] > 0 ? '' : 'd-none' }}" data-sino-contador>{{ $sino['total'] > 99 ? '99+' : $sino['total'] }}</span>
    </a>

    <div class="dropdown-menu dropdown-menu-end shadow sino-menu">
        <div class="sino-cabeca">
            <span class="fw-bold">Notificações</span>
            <form method="POST" action="{{ $sino['marcar_todas'] }}" class="d-inline">
                @csrf
                <button class="btn btn-sm btn-link p-0 text-decoration-none">Marcar todas como lidas</button>
            </form>
        </div>

        <div class="sino-lista" data-sino-lista>
            @forelse($sino['itens'] as $item)
                <a class="sino-item" href="{{ $item['ficha'] }}">
                    <span class="sino-icone tone-{{ $item['tom'] }}"><i class="fas {{ $item['icone'] }}" aria-hidden="true"></i></span>
                    <span class="sino-texto">
                        <span class="sino-titulo">{{ $item['titulo'] }}</span>
                        @if($item['texto'])<span class="sino-resumo">{{ $item['texto'] }}</span>@endif
                        <span class="sino-quando">{{ $item['rotulo'] }} · {{ $item['quando'] }}</span>
                    </span>
                </a>
            @empty
                <div class="sino-vazio" data-sino-vazio>Nada novo por aqui.</div>
            @endforelse
        </div>

        <div class="sino-rodape">
            <a href="{{ $sino['ver_todas'] }}">Ver todas</a>
            <a href="{{ $sino['preferencias'] }}">Preferências</a>
        </div>
    </div>
</li>
