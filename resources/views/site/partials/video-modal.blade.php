{{--
    O modal da galeria de tutoriais. É o mesmo player da casa da faixa do funil
    (site.partials.video-player): sem controles do YouTube, com o vidro cobrindo o quadro
    enquanto o vídeo não toca, e sem autoplay — abrir o tutorial mostra o quadro pronto, e
    ele só começa no toque do visitante no play.

    O player só entra em ação quando o visitante abre um vídeo: a página carrega leve e o
    YouTube não grava nada em quem apenas passou os olhos pela galeria.
--}}
<div class="video-modal" id="videoModal" role="dialog" aria-modal="true" aria-labelledby="videoModalTitulo" aria-hidden="true">
    <button type="button" class="video-modal-veu" data-video-close aria-label="Fechar o vídeo"></button>
    <div class="video-modal-caixa">
        <div class="video-modal-topo">
            <h3 id="videoModalTitulo">&nbsp;</h3>
            <button type="button" class="video-modal-fechar" data-video-close aria-label="Fechar o vídeo"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        @include('site.partials.video-player', ['rotulo' => 'Tutorial em vídeo'])
    </div>
</div>
