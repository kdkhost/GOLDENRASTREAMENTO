{{--
    O player da casa — uma peça só, servindo à faixa de vídeo do funil e ao modal da galeria.

    Nada nele é do YouTube por vontade de quem assiste: o iframe entra sem controles, e o
    vidro por cima fica OPACO enquanto o vídeo não está tocando. É esse vidro que esconde o
    botão de play vermelho, a faixa de título com o nome do canal e a tela final de vídeos
    sugeridos — o que o visitante vê em repouso é sempre a nossa moldura com o nosso selo.
    Os únicos controles são os daqui embaixo (tocar/pausar, mudo, volume) e o selo do vidro.

    E nada toca sozinho: abrir o quadro não toca. Tocar é sempre um toque dado em algo nosso —
    o play da barra ou o card da galeria, que abre e já toca no mesmo toque. O comportamento
    está em public/js/golden-site.js.

    Onde o vidro não alcança — porque o vídeo já está tocando e tem de aparecer — a casa fecha
    a passagem de outro jeito: a máscara de topo segura o cartão de título que o YouTube mostra
    nos primeiros segundos de cada reprodução, e as duas esquinas da barra cobrem o botão de link
    e a marca dele, que nenhum playerVar tira da tela.

    Propriedades: $codigo (onze caracteres, vazio no modal), $thumb (miniatura; sem ela o
    quadro já aparece aberto, como no modal), $rotulo, $cta e $farol (medição do funil).
--}}
@php
    $codigo = $codigo ?? '';
    $thumb = $thumb ?? null;
    $rotulo = $rotulo ?? 'Vídeo';
    $cta = $cta ?? 'Assistir a apresentação';
@endphp
{{-- Só quem tem vídeo na página adianta a conexão com o player: nas outras páginas seria uma
     mão dada a um servidor que nunca chega a ser consultado. --}}
@once
@push('styles')
<link rel="preconnect" href="https://www.youtube-nocookie.com">
@endpush
@endonce
<div class="video-slot"@if($codigo) data-video-id="{{ $codigo }}"@endif data-rotulo="{{ $rotulo }}">
    @if($thumb)
    <button type="button" class="video-cartaz"@if(isset($farol)) data-farol="video" data-detalhe="{{ $farol }}"@endif>
        <img src="{{ $thumb }}" alt="" width="640" height="360" loading="lazy" decoding="async">
        <span class="video-cartaz-play" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
        <b>{{ $cta }}</b>
    </button>
    @endif

    <div class="video-player"@if($thumb) hidden @endif>
        {{-- O buraco onde a API do YouTube troca o div pelo iframe. Fica dentro de .video-yt
             de propósito: o iframe herda o lugar, nunca a classe. --}}
        <div class="video-yt"><div class="video-quadro"></div></div>
        <div class="video-mascara" aria-hidden="true"></div>
        <div class="video-vidro" aria-hidden="true"><span class="video-vidro-selo"><i class="fa-solid fa-play"></i></span></div>
        <div class="video-controles">
            <button type="button" class="video-botao" data-acao="tocar" aria-label="Tocar o vídeo">
                <i class="fa-solid fa-play" aria-hidden="true"></i>
            </button>
            <button type="button" class="video-botao" data-acao="mudo" aria-label="Silenciar o vídeo" aria-pressed="false">
                <i class="fa-solid fa-volume-high" aria-hidden="true"></i>
            </button>
            <input type="range" class="video-volume" data-acao="volume" min="0" max="100" step="1" value="80" aria-label="Volume do vídeo">
        </div>
    </div>
</div>
