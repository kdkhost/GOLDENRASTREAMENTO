/* Golden Rastreamento — o comportamento que só existe na página de campanha (/c/…).
   Carregado depois do golden-site.js, apenas nesta superfície, para o resto do site não
   pagar por ele. */
(function () {
  'use strict';

  var ENDERECO_DA_API = 'https://www.youtube.com/iframe_api';
  var aguardando = null;

  // A API do YouTube só é pedida no primeiro toque em assistir: até ali a página não tem
  // um byte de terceiros a mais no caminho crítico.
  function comAYouTube(cb) {
    if (window.YT && window.YT.Player) { cb(); return; }

    aguardando = aguardando || [];
    aguardando.push(cb);

    if (document.querySelector('script[data-youtube-api]')) return;

    var script = document.createElement('script');
    script.src = ENDERECO_DA_API;
    script.async = true;
    script.setAttribute('data-youtube-api', '');
    document.head.appendChild(script);

    window.onYouTubeIframeAPIReady = function () {
      var fila = aguardando || [];
      aguardando = null;
      fila.forEach(function (f) { f(); });
    };
  }

  function montar(slot) {
    var cartaz = slot.querySelector('.f-video-cartaz');
    var player = slot.querySelector('.f-video-player');
    var quadro = slot.querySelector('.f-video-quadro');
    var vidro = slot.querySelector('.f-video-vidro');
    var botaoTocar = player.querySelector('[data-acao="tocar"]');
    var botaoMudo = player.querySelector('[data-acao="mudo"]');
    var botaoFechar = player.querySelector('[data-acao="fechar"]');
    var regua = player.querySelector('[data-acao="volume"]');
    var icone = botaoTocar.querySelector('i');

    var inst = null;
    var iniciado = false;
    var encerrado = false;
    // Pausa manual é a que o visitante fez no nosso botão. A pausa automática de voltar ao
    // lugar não entra aqui — é justamente o que permite retomar de onde parou ao rolar de novo.
    var pausaManual = false;
    var flutuando = false;

    function tocando() {
      return !!(inst && inst.getPlayerState && inst.getPlayerState() === window.YT.PlayerState.PLAYING);
    }

    function pintar() {
      var tocandoAgora = tocando();
      icone.className = 'fa-solid ' + (tocandoAgora ? 'fa-pause' : 'fa-play');
      botaoTocar.setAttribute('aria-label', tocandoAgora ? 'Pausar o vídeo' : 'Tocar o vídeo');

      var mudo = !!(inst && inst.isMuted && inst.isMuted());
      botaoMudo.querySelector('i').className = 'fa-solid ' + (mudo ? 'fa-volume-xmark' : 'fa-volume-high');
      botaoMudo.setAttribute('aria-pressed', mudo ? 'true' : 'false');
      botaoMudo.setAttribute('aria-label', mudo ? 'Tirar o silêncio' : 'Silenciar o vídeo');
    }

    function alternar() {
      if (!inst) return;
      if (tocando()) {
        inst.pauseVideo();
        pausaManual = true;
      } else {
        inst.playVideo();
        pausaManual = false;
      }
    }

    function flutuar(deve) {
      if (flutuando === deve) return;
      flutuando = deve;
      player.classList.toggle('flutuante', deve);
    }

    function voltarParaOCartaz() {
      flutuar(false);
      player.hidden = true;
      cartaz.hidden = false;
      if (inst && inst.seekTo) inst.seekTo(0, true);
    }

    // Fechar é um pedido do visitante, não um pausa de percurso: o quadro volta ao cartaz,
    // para o zero, e fica parado até ele tocar de novo — rolar a página não o solta outra vez.
    function fechar() {
      if (inst && inst.pauseVideo) inst.pauseVideo();
      iniciado = false;
      encerrado = true;
      pausaManual = false;
      voltarParaOCartaz();
    }

    cartaz.addEventListener('click', function () {
      cartaz.hidden = true;
      player.hidden = false;

      comAYouTube(function () {
        if (inst) {
          encerrado = false;
          inst.playVideo();

          return;
        }

        inst = new window.YT.Player(quadro, {
          videoId: slot.getAttribute('data-video-id'),
          // O mesmo endereço da galeria do site: sem cookie de terceiros no quadro.
          host: 'https://www.youtube-nocookie.com',
          playerVars: {
            // controls:0 e o vidro por cima são o que tira o YouTube da tela: sem a barra
            // dele não há marca nem link, e o clique nunca cai na superfície de fora.
            autoplay: 1,
            controls: 0,
            disablekb: 1,
            fs: 0,
            iv_load_policy: 3,
            modestbranding: 1,
            playsinline: 1,
            rel: 0,
            origin: window.location.origin
          },
          events: {
            onReady: function () {
              // O volume que está na régua é o que o vídeo toca: a régua manda, o player obedece.
              inst.setVolume(Number(regua.value));
              inst.playVideo();
              pintar();
            },
            onStateChange: function (e) {
              var estado = window.YT.PlayerState;

              if (e.data === estado.ENDED) {
                encerrado = true;
                iniciado = false;
                pausaManual = false;
                voltarParaOCartaz();
              }

              if (e.data === estado.PLAYING) {
                iniciado = true;
                encerrado = false;
              }

              pintar();
            }
          }
        });
      });
    });

    botaoTocar.addEventListener('click', alternar);
    botaoFechar.addEventListener('click', fechar);
    // O vidro é a área do vídeo em si: tocar ali também é play/pause, como em qualquer player.
    vidro.addEventListener('click', alternar);

    botaoMudo.addEventListener('click', function () {
      if (!inst) return;
      if (inst.isMuted && inst.isMuted()) inst.unMute(); else inst.mute();
      pintar();
    });

    regua.addEventListener('input', function () {
      if (!inst) return;
      inst.setVolume(Number(regua.value));
      if (Number(regua.value) > 0 && inst.isMuted && inst.isMuted()) inst.unMute();
      pintar();
    });

    // Botão direito nunca chega ao YouTube: o vidro é nosso e ele bloqueia o menu aqui.
    [vidro, player.querySelector('.f-video-controles')].forEach(function (camada) {
      camada.addEventListener('contextmenu', function (e) {
        e.preventDefault();

        return false;
      });
    });

    if (!('IntersectionObserver' in window)) return;

    new IntersectionObserver(function (entradas) {
      var visivel = entradas[0].isIntersecting;

      if (visivel) {
        // Voltou ao lugar de origem: para. A pausa é do movimento, não do visitante, então
        // a próxima vez que ele rolar para fora o vídeo retoma do ponto em que ficou.
        if (flutuando) {
          flutuar(false);
          if (inst && !pausaManual) inst.pauseVideo();
        }

        return;
      }

      if (!iniciado || encerrado || pausaManual) return;

      flutuar(true);
      if (inst) inst.playVideo();
    }, { threshold: 0.4 }).observe(slot);
  }

  function iniciar() {
    document.querySelectorAll('.f-video-slot[data-video-id]').forEach(montar);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
})();
