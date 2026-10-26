{{-- Os dois servidores de rastreamento são plataformas diferentes, com endereços diferentes:
     por isso a escolha aparece antes do login, num modal, em vez de um único link que metade
     dos clientes abriria no lugar errado. Cada endereço vem do config e pode ser trocado em
     Configurações, no painel. --}}
@php
    $servidores = [
        ['titulo' => $golden['acessos']['servidor_1_titulo'], 'url' => $golden['acessos']['servidor_1_url'], 'icone' => $golden['acessos']['servidor_1_icone']],
        ['titulo' => $golden['acessos']['servidor_2_titulo'], 'url' => $golden['acessos']['servidor_2_url'], 'icone' => $golden['acessos']['servidor_2_icone']],
    ];
@endphp
<div class="acesso-modal" id="acessoPlataformas" role="dialog" aria-modal="true" aria-labelledby="acessoPlataformasTitulo" aria-hidden="true">
    <button type="button" class="acesso-veu" data-acesso-fechar tabindex="-1" aria-label="Fechar"></button>
    <div class="acesso-caixa">
        <div class="acesso-topo">
            <span class="acesso-selo"><i class="fa-solid fa-satellite-dish" aria-hidden="true"></i></span>
            <div class="acesso-titulos">
                <h3 id="acessoPlataformasTitulo">{{ $golden['acessos']['modal_titulo'] }}</h3>
                <p>{{ $golden['acessos']['modal_desc'] }}</p>
            </div>
            <button type="button" class="acesso-fechar" data-acesso-fechar aria-label="Fechar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>

        <div class="acesso-opcoes">
            @foreach($servidores as $servidor)
                @continue(blank($servidor['url']))
                <a class="acesso-opcao" href="{{ $servidor['url'] }}" target="_blank" rel="noopener">
                    <span class="acesso-opcao-icone"><i class="fa-solid {{ $servidor['icone'] }}" aria-hidden="true"></i></span>
                    <span class="acesso-opcao-texto"><b>{{ $servidor['titulo'] }}</b></span>
                    <span class="acesso-opcao-abrir">Abrir <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span>
                </a>
            @endforeach
        </div>

        <p class="acesso-ajuda">{{ $golden['acessos']['ajuda'] }} <a href="{{ route('site.contato') }}" data-track="contact" data-cta="acesso">{{ $golden['acessos']['ajuda_link'] }}</a>.</p>
    </div>
</div>
