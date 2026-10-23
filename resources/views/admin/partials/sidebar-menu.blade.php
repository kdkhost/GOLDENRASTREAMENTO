{{-- Sidebar do painel: a árvore vem de config('golden.menu_painel') via MenuPainel::montar().
     O grupo da página aberta já nasce com menu-open; trocar de grupo fecha o anterior porque
     o plugin de treeview do AdminLTE roda em modo accordion. --}}
@foreach($menu_painel as $item)
    @php $ehGrupo = $item['filhos'] !== []; @endphp
    <li class="nav-item{{ $ehGrupo ? ' has-child' : '' }}{{ $item['aberto'] ? ' menu-open' : '' }}">
        <a href="{{ $ehGrupo ? '#' : $item['url'] }}"
           class="nav-link {{ $item['ativo'] ? 'active' : '' }}{{ $item['esmaecido'] ? ' opacity-50' : '' }}"
           @if($ehGrupo) data-lte-toggle="treeview" role="button" aria-expanded="{{ $item['aberto'] ? 'true' : 'false' }}" @endif
           @if($item['externo']) target="_blank" rel="noopener" @endif>
            <i class="nav-icon fas {{ $item['icone'] }}" aria-hidden="true"></i>
            <p>{{ $item['titulo'] }}
                @if($item['bolha'])<span class="badge badge-menu tone-{{ $item['tom'] }} ms-auto">{{ $item['bolha'] }}</span>@endif
                @if($item['aviso'])<span class="badge badge-menu tone-neutral ms-auto">{{ $item['aviso'] }}</span>@endif
                @if($ehGrupo)<i class="nav-arrow fas fa-angle-left" aria-hidden="true"></i>@endif
            </p>
        </a>
        @if($ehGrupo)
            <ul class="nav nav-treeview">
                @foreach($item['filhos'] as $filho)
                    <li class="nav-item">
                        <a href="{{ $filho['url'] }}"
                           class="nav-link {{ $filho['ativo'] ? 'active' : '' }}{{ $filho['esmaecido'] ? ' opacity-50' : '' }}"
                           @if($filho['externo']) target="_blank" rel="noopener" @endif>
                            <i class="nav-icon fas {{ $filho['icone'] }}" aria-hidden="true"></i>
                            <p>{{ $filho['titulo'] }}
                                @if($filho['bolha'])<span class="badge badge-menu tone-{{ $filho['tom'] }} ms-auto">{{ $filho['bolha'] }}</span>@endif
                                @if($filho['aviso'])<span class="badge badge-menu tone-neutral ms-auto">{{ $filho['aviso'] }}</span>@endif
                            </p>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </li>
@endforeach
