{{-- O player só entra no DOM quando o visitante abre o vídeo: a página carrega leve e o
     YouTube não grava nada em quem apenas passou os olhos pela galeria. Serve à galeria
     de tutoriais e ao bloco de vídeo de vendas do funil — um modal, duas telas. --}}
<div class="video-modal" id="videoModal" role="dialog" aria-modal="true" aria-labelledby="videoModalTitulo" aria-hidden="true">
    <button type="button" class="video-modal-veu" data-video-close aria-label="Fechar o vídeo"></button>
    <div class="video-modal-caixa">
        <div class="video-modal-topo">
            <h3 id="videoModalTitulo">&nbsp;</h3>
            <div class="video-modal-acoes">
                <a href="#" target="_blank" rel="noopener" class="video-modal-yt"><i class="fa-brands fa-youtube" aria-hidden="true"></i> Assistir no YouTube</a>
                <button type="button" class="video-modal-fechar" data-video-close aria-label="Fechar o vídeo"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
        </div>
        <div class="video-player"></div>
    </div>
</div>
