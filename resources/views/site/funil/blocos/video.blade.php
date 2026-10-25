{{--
    Vídeo da campanha. Toca na própria página, sem o cartaz do YouTube: o iframe entra sem
    controles e coberto por um vidro, porque clique e botão direito que caem na superfície do
    YouTube trazem de volta a marca e o menu dele. O único controle que existe é o daqui em
    baixo — play, pause e volume — mais o fechar que só aparece quando o quadro está solto na
    tela. O resto do comportamento (o desprendimento ao rolar) está em public/js/golden-funil.js.
--}}
@php $codigo = \App\Models\Video::codigoDe($b->cta_destino); @endphp
@if($codigo)
@push('scripts')
<script src="{{ asset('js/golden-funil.js') }}?v={{ filemtime(public_path('js/golden-funil.js')) }}"></script>
@endpush
<section class="f-video">
    <div class="wrap">
        @if($b->titulo)<h2>{{ $b->titulo }}</h2>@endif
        @if($b->texto)<p class="lead-sub">{{ $b->texto }}</p>@endif

        <div class="f-video-slot" data-video-id="{{ $codigo }}">
            <button type="button" class="f-video-cartaz" data-farol="video" data-detalhe="{{ $b->titulo }}">
                <img src="{{ \App\Models\Video::thumb($codigo) }}" alt="" width="640" height="360" loading="lazy" decoding="async">
                <span class="f-video-cartaz-play" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                <b>{{ $b->cta_texto ?: 'Assistir a apresentação' }}</b>
            </button>

            <div class="f-video-player" hidden>
                {{-- O buraco onde a API do YouTube troca o div pelo iframe. Fica dentro de
                     .f-video-yt de propósito: o iframe herda o lugar, nunca a classe. --}}
                <div class="f-video-yt"><div class="f-video-quadro"></div></div>
                <div class="f-video-vidro"></div>
                <div class="f-video-controles">
                    <button type="button" class="f-video-botao" data-acao="tocar" aria-label="Pausar o vídeo">
                        <i class="fa-solid fa-pause" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="f-video-botao" data-acao="mudo" aria-label="Silenciar o vídeo" aria-pressed="false">
                        <i class="fa-solid fa-volume-high" aria-hidden="true"></i>
                    </button>
                    <input type="range" class="f-video-volume" data-acao="volume" min="0" max="100" step="1" value="80" aria-label="Volume do vídeo">
                </div>
                <button type="button" class="f-video-fechar" data-acao="fechar" aria-label="Fechar o vídeo">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</section>
@endif
