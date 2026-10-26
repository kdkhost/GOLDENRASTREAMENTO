/* Golden Rastreamento — front do site */
(function () {
  'use strict';

  var G = window.GOLDEN || {};
  var trackingOn = G.tracking === true || G.tracking === '1' || G.tracking === 1;
  var semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- UTMs + identificadores de clique ---------- */
  var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
  // Os nomes que as plataformas de anúncio colam na URL de chegada. A classificação de
  // origem é do servidor (config/golden.php > ids_clique); o navegador só precisa guardar
  // o par {nome, valor} para o lead não nascer cego quando a visita expirou.
  var CLIQUE_KEYS = ['gclid', 'gbraid', 'wbraid', 'dclid', 'gaplid', 'fbclid', 'ttclid', 'msclkid', 'twclid', 'yclid', 'igsh', 'igshid', 'li_fat_id'];

  function readQuery() {
    var p = new URLSearchParams(window.location.search), out = {};
    UTM_KEYS.concat(CLIQUE_KEYS).forEach(function (k) { if (p.get(k)) out[k] = p.get(k); });
    return out;
  }
  function saveAttribution() {
    var q = readQuery();
    if (Object.keys(q).length) {
      // Chegada marcada nova = toque novo: substitui, não mistura. Guardar o bloco antigo
      // junto faria um clique do Google herdar o utm_medium do Reels e a campanha sairia
      // com duas origens costuradas — o servidor já abre visita nova pelo mesmo motivo.
      sessionStorage.setItem('golden_attr', JSON.stringify(q));
    }
  }
  function getAttribution() {
    try { return JSON.parse(sessionStorage.getItem('golden_attr') || '{}'); } catch (e) { return {}; }
  }
  saveAttribution();

  /* ---------- Tracking de eventos ---------- */
  function track(event, params) {
    params = params || {};
    if (trackingOn) {
      if (window.gtag) window.gtag('event', event, params);
      if (window.fbq) window.fbq('track', event, params);
    } else {
      // Modo local: imprime no console enquanto a coleta está desativada.
      console.log('[golden:track]', event, params);
    }
  }
  window.goldenTrack = track;

  /**
   * A conversão devolvida à plataforma de anúncio: cada uma quer o próprio nome de
   * evento, e só dispara se aquele pixel foi carregado na página. O cadastro já é
   * contado do lado do servidor (farol 'lead' + lead no painel) — isto aqui é o eco
   * que alimenta o leilão do Google, do Meta e do TikTok.
   */
  function conversaoDeAnuncio() {
    var f = G.funil || {};
    if (f.conversao_google_ads && window.gtag) {
      window.gtag('event', 'conversion', {
        send_to: f.conversao_google_ads,
        value: f.preco_cents ? f.preco_cents / 100 : 1,
        currency: 'BRL'
      });
    }
    if (window.fbq) window.fbq('track', 'Lead');
    if (window.ttq) window.ttq.track('Lead');
  }

  /* ---------- Farol próprio (primeira mão, sem third party) ---------- */
  // O mesmo gesto que já alimenta gtag/fbq alimenta a jornada que o painel mostra.
  // Os nomes aqui são os tipos declarados em config/golden.php > rastreio.eventos.
  var FAROL_DE_EVENTO = {
    contact: 'cta',
    view_plans: 'cta',
    store_open: 'cta',
    internal_link: 'cta',
    whatsapp_click: 'whatsapp',
    select_plan: 'kit'
  };

  /**
   * Manda um passo da jornada para o servidor. fetch com keepalive (e não sendBeacon)
   * porque a rota é POST e precisa do token CSRF; keepalive entrega mesmo quando o
   * clique leva o visitante para fora da página.
   */
  function farol(tipo, destino, detalhe) {
    if (!G.farol || !tipo) return;
    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    try {
      fetch(G.farol, {
        method: 'POST',
        keepalive: true,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify({ tipo: tipo, path: location.pathname, destino: destino || null, detalhe: detalhe || null })
      });
    } catch (e) { /* medição nunca bloqueia o visitante */ }
  }

  /**
   * Telefones, e-mails e saídas do site: a origem do clique só aparece aqui.
   * Um elemento pode declarar o próprio passo da jornada com data-farol="tipo"
   * (a loja usa isso na sacola e no checkout); o resto é deduzido do href.
   */
  function initFarol() {
    document.addEventListener('click', function (e) {
      var alvo = e.target instanceof Element ? e.target.closest('a[href], button, form[data-farol]') : null;
      if (!alvo) return;

      // O passo declarado no próprio elemento vence o do formulário; assim "Comprar agora"
      // conta checkout e "Adicionar" conta sacola na mesma form.
      var caixa = alvo.closest('[data-farol]');
      var tipo = alvo.getAttribute('data-farol') || (alvo.tagName === 'BUTTON' && caixa !== alvo ? caixa.getAttribute('data-farol') : null);

      if (tipo) {
        farol(tipo, location.pathname, alvo.getAttribute('data-detalhe') || caixa.getAttribute('data-detalhe') || null);
        return;
      }

      if (alvo.tagName !== 'A' || alvo.hasAttribute('data-track')) return; // já medido pelo initCtas

      var href = alvo.getAttribute('href') || '';
      if (href.indexOf('tel:') === 0) farol('telefone', href.slice(4));
      else if (href.indexOf('mailto:') === 0) farol('email', href.slice(7));
      else if (/^(https?:)?\/\/wa\.me|whatsapp\.com\//i.test(href)) farol('whatsapp', href);
      else if (/^https?:\/\//i.test(href) && alvo.hostname !== location.hostname) farol('link_externo', alvo.href);
    }, { passive: true, capture: true });
  }

  /* ---------- Máscara de telefone ---------- */
  function maskPhone(v) {
    v = v.replace(/\D/g, '').slice(0, 11);
    if (v.length > 10) return v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    if (v.length > 6) return v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    if (v.length > 2) return v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
    return v;
  }

  /* ---------- Cookie de consentimento ---------- */
  function consentGiven() { return localStorage.getItem('golden_consent'); }
  function initCookie() {
    var bar = document.getElementById('cookieBar');
    if (!bar || consentGiven()) return;
    setTimeout(function () { bar.classList.add('show'); }, 900);
    var accept = document.getElementById('cookieAccept');
    var reject = document.getElementById('cookieReject');
    if (accept) accept.addEventListener('click', function () { localStorage.setItem('golden_consent', 'all'); bar.classList.remove('show'); track('cookie_consent', { choice: 'all' }); });
    if (reject) reject.addEventListener('click', function () { localStorage.setItem('golden_consent', 'necessary'); bar.classList.remove('show'); track('cookie_consent', { choice: 'necessary' }); });
  }

  /* ---------- Formulário de lead ---------- */
  function initForms() {
    document.querySelectorAll('form[data-lead-form]').forEach(function (form) {
      var phone = form.querySelector('[name="phone"]');
      if (phone) phone.addEventListener('input', function () { this.value = maskPhone(this.value); });

      // O primeiro toque no formulário vale tanto quanto o envio: é aqui que o painel
      // consegue mostrar "começou e não terminou".
      var iniciado = false;
      form.addEventListener('input', function () {
        if (iniciado) return;
        iniciado = true;
        farol('form_inicio', location.pathname, (this.querySelector('[name="kit"]') || {}).value || null);
      }, { passive: true });

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = form.querySelector('button[type="submit"]');

        // injeta atribuição de campanha nos campos ocultos
        var attr = getAttribution();
        UTM_KEYS.concat(CLIQUE_KEYS).forEach(function (k) {
          var input = form.querySelector('[name="' + k + '"]');
          if (input && attr[k]) input.value = attr[k];
        });
        // O par genérico traz a rede que não tem coluna própria (ttclid, msclkid...):
        // o nome do campo de chegada é o que diz de quem foi o clique.
        var clicado = CLIQUE_KEYS.filter(function (k) { return attr[k]; })[0];
        var campoClique = form.querySelector('[name="clique_id"]');
        var campoValor = form.querySelector('[name="clique_valor"]');
        if (clicado && campoClique && campoValor) {
          campoClique.value = clicado;
          campoValor.value = attr[clicado];
        }

        var payload = {};
        new FormData(form).forEach(function (v, k) { payload[k] = v; });
        if (!payload.consent) payload.consent = form.querySelector('[name="consent"]') ? (form.querySelector('[name="consent"]').checked ? '1' : '') : '';

        var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        if (btn) btn.disabled = true;
        track('generate_lead', { kit: payload.kit, perfil: payload.profile, quantidade: payload.fleet_size });

        fetch(form.action, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
          body: JSON.stringify(payload)
        })
          .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
          .then(function (res) {
            if (res.ok && res.d.success) {
              GoldenAvisos.sucesso(
                res.d.message || 'Recebemos sua solicitação.',
                res.d.whatsapp_url ? { url: res.d.whatsapp_url, rotulo: 'Continuar no WhatsApp ↗' } : null
              );
              form.reset();
              track('lead_success', {});
              // O eco para a plataforma de anúncio só acontece com o cadastro gravado:
              // é este o evento que o Google, o Meta e o TikTok usam para otimizar o leilão.
              conversaoDeAnuncio();
            } else {
              var msg = res.d && res.d.message ? res.d.message : 'Não foi possível enviar agora. Tente novamente.';
              if (res.d && res.d.errors) {
                var first = Object.keys(res.d.errors)[0];
                msg = res.d.errors[first][0];
              }
              GoldenAvisos.erro(msg);
            }
          })
          .catch(function () { GoldenAvisos.erro('Erro de conexão. Tente novamente.'); })
          .finally(function () { if (btn) btn.disabled = false; });
      });
    });
  }

  /* ---------- Seleção de kit ---------- */
  function initKits() {
    document.querySelectorAll('[data-kit]').forEach(function (el) {
      el.addEventListener('click', function () {
        var kit = this.getAttribute('data-kit');
        var sel = document.getElementById('leadKit');
        var fleet = document.getElementById('leadFleet');
        if (sel) sel.value = kit;
        if (fleet) fleet.value = this.getAttribute('data-fleet') || '';
        track('select_plan', { plan: kit });
        farol('kit', this.getAttribute('href'), kit);
      });
    });
  }

  /* ---------- Menu deslizante (gaveta da esquerda) ---------- */
  var fecharSubmenus = null;

  function initNav() {
    var toggle = document.getElementById('navToggle');
    var drawer = document.getElementById('navDrawer');
    var backdrop = document.getElementById('navBackdrop');
    if (!toggle || !drawer) return;

    var estreita = window.matchMedia('(max-width: 960px)');
    var ultimoFoco = null;

    function aberto() { return document.body.classList.contains('nav-open'); }

    function abrir() {
      if (aberto() || !estreita.matches) return;
      ultimoFoco = document.activeElement;
      if (fecharSubmenus) fecharSubmenus(drawer);
      document.body.classList.add('nav-open');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', 'Fechar menu');
    }

    function fechar() {
      if (fecharSubmenus) fecharSubmenus(drawer);
      if (!aberto()) return;
      document.body.classList.remove('nav-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Abrir menu');
      if (ultimoFoco && typeof ultimoFoco.focus === 'function') ultimoFoco.focus();
      ultimoFoco = null;
    }

    toggle.addEventListener('click', function () { aberto() ? fechar() : abrir(); });
    if (backdrop) backdrop.addEventListener('click', fechar);
    drawer.querySelectorAll('[data-nav-close]').forEach(function (b) { b.addEventListener('click', fechar); });
    drawer.addEventListener('click', function (e) {
      if (e.target.closest('a, [data-acesso-plataformas]')) fechar();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fechar(); });
    estreita.addEventListener('change', function (e) { if (!e.matches) fechar(); });
  }

  /* ---------- Submenus (dropdown no desktop, acordeão na gaveta) ---------- */
  function initSubmenus() {
    var grupos = document.querySelectorAll('.nav-item.has-sub');
    if (!grupos.length) return;

    var estreita = window.matchMedia('(max-width: 960px)');

    function fechar(itens) {
      itens.forEach(function (item) {
        item.classList.remove('open');
        item.querySelectorAll('[data-sub-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
      });
    }

    function fecharTodos(scope) {
      fechar((scope || document).querySelectorAll('.nav-item.open'));
    }

    function abrir(item) {
      fecharTodos(item.parentElement);
      item.classList.add('open');
      item.querySelectorAll('[data-sub-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
    }

    document.querySelectorAll('[data-sub-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var item = btn.closest('.nav-item');
        if (item.classList.contains('open')) fechar([item]); else abrir(item);
      });
    });

    document.addEventListener('click', function (e) {
      if (e.target instanceof Element && !e.target.closest('.nav-item.has-sub')) fecharTodos();
    });

    document.addEventListener('keydown', function (e) {
      var lista = document.querySelector('.nav-links');
      if (e.key === 'Escape' && !estreita.matches && lista) fecharTodos(lista);
    });

    estreita.addEventListener('change', function () { fecharTodos(); });

    fecharSubmenus = fecharTodos;
  }

  /* ---------- Caixa multicanal do WhatsApp (padrão foco7) ---------- */
  function initWhatsBox() {
    var btn = document.getElementById('waFloat');
    var popup = document.getElementById('waPopup');
    if (!btn || !popup) return;

    function abrir() {
      popup.classList.add('show');
      btn.setAttribute('aria-expanded', 'true');
      // A conversa do assistente mora no mesmo canto: ela fecha ao ouvir este aviso.
      document.dispatchEvent(new CustomEvent('golden:wa-aberto'));
    }

    function fechar() {
      popup.classList.remove('show');
      btn.setAttribute('aria-expanded', 'false');
    }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      popup.classList.contains('show') ? fechar() : abrir();
    });

    popup.querySelectorAll('[data-wa-close]').forEach(function (b) {
      b.addEventListener('click', function (e) { e.stopPropagation(); fechar(); });
    });

    popup.addEventListener('click', function (e) {
      if (e.target instanceof Element && e.target.closest('a')) fechar();
    });

    document.addEventListener('click', function (e) {
      if (e.target instanceof Element && !e.target.closest('#waPopup, #waFloat')) fechar();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') fechar();
    });
  }

  /* ---------- Carrosséis: um init para toda lista que desliza ----------
     A marcação é sempre a mesma (.carrossel > .swiper > .swiper-wrapper > .swiper-slide,
     com os opcionais .carro-dots / .carro-prev / .carro-next na caixa). O que muda entre um
     carrossel e outro vem dos atributos do próprio .js-carrossel, para a regra ficar em um
     lugar só:

       data-avanco   ms entre um slide e o próximo; ausente = não avança sozinho
       data-sentido  'reverso' para o avanço andar para trás
       data-passos   JSON dos breakpoints, no formato do Swiper

     Sem o Swiper carregado a lista continua legível: o CSS dela própria abre os slides em
     flex-wrap, então nenhuma tela depende do script para mostrar o conteúdo. */
  function initCarrosseis() {
    if (typeof window.Swiper === 'undefined') return;

    document.querySelectorAll('.js-carrossel').forEach(function (el) {
      if (el.swiper) return;

      var caixa = el.closest('.carrossel') || el.parentElement;
      var passos;
      try {
        passos = JSON.parse(el.getAttribute('data-passos') || 'null');
      } catch (e) {
        passos = null;
      }
      passos = passos || {};

      var avanco = parseInt(el.getAttribute('data-avanco') || '0', 10);
      // O nome do que desliza sai do próprio carrossel: o leitor de tela ouve
      // "Depoimento anterior" na fila de depoimentos e "Benefício anterior" na de cards.
      var rotulo = el.getAttribute('data-rotulo') || 'item';
      var dots = caixa.querySelector('.carro-dots');
      var anterior = caixa.querySelector('.carro-prev');
      var proximo = caixa.querySelector('.carro-next');
      var andando = avanco > 0 && el.querySelectorAll('.swiper-slide').length > 1;

      var carrossel = new window.Swiper(el, {
        slidesPerView: 1,
        spaceBetween: 16,
        grabCursor: true,
        simulateTouch: true,
        touchRatio: 1.2,
        threshold: 5,
        watchOverflow: true,
        // rewind, e não loop: com rewind o Swiper não duplica slides, então a fila serve
        // igual na tela em que tudo cabe (fica parada) e na estreita (volta ao fim ao
        // chegar no começo) sem o aviso de "slides insuficientes para o modo loop".
        rewind: true,
        speed: semMovimento ? 0 : 600,
        keyboard: { enabled: true, onlyInViewport: true },
        a11y: { prevSlideMessage: rotulo + ' anterior', nextSlideMessage: 'Próximo ' + rotulo },
        autoplay: (!andando || semMovimento) ? false : {
          delay: avanco,
          disableOnInteraction: false,
          // No notebook basta passar o mouse para a fila parar de andar.
          pauseOnMouseEnter: true,
          reverseDirection: el.getAttribute('data-sentido') === 'reverso'
        },
        pagination: dots ? { el: dots, clickable: true } : false,
        navigation: (anterior && proximo) ? { prevEl: anterior, nextEl: proximo } : false,
        breakpoints: passos
      });

      // Travado = nada a deslizar nesta largura (os cards todos cabem em uma linha). A
      // caixa recebe a classe para os pontos sumirem, e o avanço automático se desliga:
      // sem espaço para andar ele só ficaria batendo no fim da fila. Ao reduzir a janela,
      // o resize reavalia e a fila volta a andar sozinha.
      function travado() {
        var parado = carrossel.isLocked === true;
        caixa.classList.toggle('travado', parado);
        if (!parado) return;
        if (carrossel.autoplay) carrossel.autoplay.stop();
      }
      function solto() {
        if (carrossel.isLocked === true) return;
        if (andando && !semMovimento && carrossel.autoplay) carrossel.autoplay.start();
      }
      carrossel.on('resize', function () { travado(); solto(); });
      travado();

      // No celular "segurar" é o gesto de pausa: o dedo parado congela o avanço e ele só
      // volta quando solta. O hover do desktop já está coberto pelo pauseOnMouseEnter.
      el.addEventListener('touchstart', function () {
        if (carrossel.autoplay) carrossel.autoplay.stop();
      }, { passive: true });
      el.addEventListener('touchend', function () {
        solto();
      }, { passive: true });
    });
  }

  /* ---------- O player da casa ----------
     Uma peça só, servindo à faixa de vídeo do funil e ao modal da galeria. Duas regras
     mandam nele, e as duas são do visitante:

     1. nada põe o vídeo pra rodar por conta própria. O quadro abre coberto e calado, e o play
        sempre vem de um gesto do visitante: o toque no card da galeria (que abre e já toca), o
        selo do vidro ou o botão da barra. O iframe nasce com autoplay: 0 e a casa não chama
        playVideo() em nenhum onReady — ela só atende o toque que foi pedido antes dele;
     2. nada do YouTube aparece. Os controles dele estão desligados, o endereço é o de
        sem cookies, o vidro por cima não deixa clique nem hover caírem na superfície dele
        e — enquanto o vídeo não está tocando — o vidro fica opaco, cobrindo o play
        vermelho, a faixa de título e a tela de vídeos sugeridos. Até o nome que o YouTube
        escreve no iframe vai embora: leva o rótulo da casa.
     3. e quando o vídeo ESTÁ tocando, quem esconde o que o YouTube ainda pinta é a máscara:
        ela cai no instante do tocar e sobe quando a janela dele passa, porque o player repete
        o cartão de título no topo e a bolha cinza de play/pause no centro em toda reprodução,
        inclusive na retomada do meio. Durante essa janela o selo da casa fica grande, por cima
        da bolha — é o play/pause dele que se vê, nunca o do YouTube. O botão de link e a marca
        da esquina inferior direita ficam cobertos pela própria barra da casa, em CSS.

     A API só é pedida no primeiro abrir, para a página não carregar um byte de terceiros
     antes de alguém querer assistir. */
  var ENDERECO_DA_API = 'https://www.youtube.com/iframe_api';
  // Medido no player, em cada retomada de reprodução: o cartão de título do topo some por
  // volta de 7 s, e a bolha cinza de play/pause no centro ainda está inteira aos 5 s e já
  // foi aos 9. Uma janela só cobre as duas — e cobre com o selo da casa por cima da bolha,
  // que é justamente o único controle que pode aparecer.
  var JANELA_DAS_MARCAS = 9000;
  var filaDaApi = null;
  var controladores = new WeakMap();

  function comAYouTube(cb) {
    if (window.YT && window.YT.Player) { cb(); return; }

    filaDaApi = filaDaApi || [];
    filaDaApi.push(cb);

    if (document.querySelector('script[data-youtube-api]')) return;

    var script = document.createElement('script');
    script.src = ENDERECO_DA_API;
    script.async = true;
    script.setAttribute('data-youtube-api', '');
    document.head.appendChild(script);

    window.onYouTubeIframeAPIReady = function () {
      var fila = filaDaApi || [];
      filaDaApi = null;
      fila.forEach(function (f) { f(); });
    };
  }

  // Celular e tablet não têm tela para um tutorial de painel pequeno: o toque que dá play é o
  // mesmo que deita o vídeo. `tela-cheia` é o estado (o quadro passa a ser a janela inteira);
  // `giro` só entra quando o navegador recusa o pedido nativo — no iOS elemento nenhum vai para
  // tela cheia — e aí a casa gira o próprio quadro em CSS, sem depender da boa vontade dele.
  var palcoDeTelaCheia = [];

  function deitarTela(palco) {
    if (!window.matchMedia('(max-width: 960px)').matches) return false;
    if (palco.classList.contains('tela-cheia')) return true;

    palco.classList.add('tela-cheia');
    palcoDeTelaCheia.push(palco);

    var pedido = null;

    try {
      if (palco.requestFullscreen) pedido = palco.requestFullscreen();
      else if (palco.webkitRequestFullscreen) palco.webkitRequestFullscreen();
      else palco.classList.add('giro');
    } catch (recusado) {
      palco.classList.add('giro');
    }

    if (pedido && pedido.catch) pedido.catch(function () { palco.classList.add('giro'); });

    // A trava de orientação só vale dentro de tela cheia nativa; fora dela o Google devolve
    // rejeição, e quem decide o girar passa a ser o CSS.
    if (window.screen && window.screen.orientation && window.screen.orientation.lock) {
      window.screen.orientation.lock('landscape').catch(function () {});
    }

    return true;
  }

  function levantarTela(palco) {
    palco.classList.remove('tela-cheia', 'giro');
    palcoDeTelaCheia = palcoDeTelaCheia.filter(function (p) { return p !== palco; });

    if (document.fullscreenElement === palco || document.webkitFullscreenElement === palco) {
      if (document.exitFullscreen) document.exitFullscreen().catch(function () {});
      else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
    }

    if (window.screen && window.screen.orientation && window.screen.orientation.unlock) {
      window.screen.orientation.unlock();
    }
  }

  // Sair por gestos do sistema (o «voltar» do Android, o Esc do desktop) não passa pela casa:
  // sem este listener o quadro ficaria marcado como deitado depois que a tela cheia acabasse.
  function amarrarSaidaDaTelaCheia() {
    ['fullscreenchange', 'webkitfullscreenchange'].forEach(function (evento) {
      document.addEventListener(evento, function () {
        if (document.fullscreenElement || document.webkitFullscreenElement) return;
        palcoDeTelaCheia.slice().forEach(function (p) {
          if (!p.classList.contains('giro')) levantarTela(p);
        });
      });
    });
  }

  function montarVideo(slot) {
    var palco = slot.closest('.video-modal') || slot;
    function deitar() { return deitarTela(palco); }
    function levantar() { levantarTela(palco); }
    var cartaz = slot.querySelector('.video-cartaz');
    var player = slot.querySelector('.video-player');
    var quadro = slot.querySelector('.video-quadro');
    var vidro = slot.querySelector('.video-vidro');
    var botaoTocar = player.querySelector('[data-acao="tocar"]');
    var botaoMudo = player.querySelector('[data-acao="mudo"]');
    var regua = player.querySelector('[data-acao="volume"]');
    var icone = botaoTocar.querySelector('i');
    var selo = vidro.querySelector('.video-vidro-selo i');

    var inst = null;
    var codigo = slot.getAttribute('data-video-id') || '';
    var noQuadro = '';
    var pediuTocar = false;
    var marcas = null;

    function cobrirMarcas() {
      window.clearTimeout(marcas);
      slot.classList.add('marca');
      // A bolha de play/pause do YouTube dura o mesmo que o cartão de título: o selo da casa
      // entra grande, exatamente por cima dela, e sai junto com a máscara.
      vidro.classList.add('pulso');
      marcas = window.setTimeout(descobrirMarcas, JANELA_DAS_MARCAS);
    }

    function descobrirMarcas() {
      window.clearTimeout(marcas);
      marcas = null;
      slot.classList.remove('marca');
      vidro.classList.remove('pulso');
    }

    function tocando() {
      return !!(inst && inst.getPlayerState && inst.getPlayerState() === window.YT.PlayerState.PLAYING);
    }

    function pintar() {
      var tocandoAgora = tocando();
      icone.className = 'fa-solid ' + (tocandoAgora ? 'fa-pause' : 'fa-play');
      selo.className = icone.className;
      botaoTocar.setAttribute('aria-label', tocandoAgora ? 'Pausar o vídeo' : 'Tocar o vídeo');
      vidro.classList.toggle('cobre', !tocandoAgora);

      var mudo = !!(inst && inst.isMuted && inst.isMuted());
      botaoMudo.querySelector('i').className = 'fa-solid ' + (mudo ? 'fa-volume-xmark' : 'fa-volume-high');
      botaoMudo.setAttribute('aria-pressed', mudo ? 'true' : 'false');
      botaoMudo.setAttribute('aria-label', mudo ? 'Tirar o silêncio' : 'Silenciar o vídeo');
    }

    // O rótulo é da casa, nunca o título que o YouTube dá ao vídeo — e o nome do iframe é o
    // que um leitor de tela anuncia, então "YouTube video player" também tem de sair.
    function etiquetar() {
      var iframe = inst && inst.getIframe ? inst.getIframe() : null;
      if (!iframe) return;

      function nomeDaCasa() {
        return slot.getAttribute('data-rotulo') || 'Vídeo';
      }
      if (iframe.getAttribute('title') !== nomeDaCasa()) iframe.setAttribute('title', nomeDaCasa());

      // O YouTube escreve o nome do vídeo no iframe quando os metadados chegam, depois do
      // nosso pronto. O observador devolve o rótulo da casa a cada troca: nem na árvore de
      // acessibilidade a marca entra.
      if (iframe.dataset.rotuloAmarrado) return;
      iframe.dataset.rotuloAmarrado = '1';
      new MutationObserver(function () {
        if (iframe.getAttribute('title') !== nomeDaCasa()) iframe.setAttribute('title', nomeDaCasa());
      }).observe(iframe, {attributes: true, attributeFilter: ['title']});
    }

    function criar() {
      if (!codigo) return;
      inst = new window.YT.Player(quadro, {
        videoId: codigo,
        // O mesmo endereço da galeria: sem cookie de terceiros no quadro.
        host: 'https://www.youtube-nocookie.com',
        playerVars: {
          // controls:0 e o vidro por cima são o que tira o YouTube da tela. autoplay:0 é o
          // que garante que nem a superfície dele, nem a casa, põem o vídeo pra rodar sozinhas.
          autoplay: 0,
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
            noQuadro = codigo;
            etiquetar();
            // O toque dado enquanto a API estava a caminho não se perde: ele é o play pedido.
            if (pediuTocar) { pediuTocar = false; inst.playVideo(); }
            pintar();
          },
          onStateChange: function (e) {
            // O cartão de título do YouTube nasce a cada reprodução, inclusive na retomada do
            // meio: a máscara cai agora e sobe sozinha quando a janela dele passa.
            if (e.data === window.YT.PlayerState.PLAYING) cobrirMarcas();
            if (e.data === window.YT.PlayerState.PAUSED) descobrirMarcas();
            if (e.data === window.YT.PlayerState.ENDED) terminar();

            pintar();
          }
        }
      });
    }

    // O vídeo que terminou volta ao estado de antes e para no zero: em casa, o cartaz; no
    // modal, o quadro coberto. É o que não deixa a tela final do YouTube na frente de ninguém.
    function terminar() {
      pediuTocar = false;
      descobrirMarcas();
      levantar();
      if (inst) {
        inst.pauseVideo();
        inst.seekTo(0, true);
      }
      if (cartaz) {
        player.hidden = true;
        cartaz.hidden = false;
      }
    }

    function alternar() {
      // O play é sempre um toque da casa, e é nele — não no onReady — que a tela deita.
      if (!tocando()) deitar();

      if (!inst) {
        pediuTocar = true;

        return;
      }
      pediuTocar = false;
      if (tocando()) {
        inst.pauseVideo();
      } else {
        inst.playVideo();
      }
      pintar();
    }

    // Na galeria, abrir já é tocar: o card escolhido entra em cena e roda no mesmo gesto
    // (`tocarJa`). Na faixa do funil o quadro abre coberto e calado, e o play fica para o
    // próximo toque — ali o visitante está lendo a página, não assistindo.
    function abrir(novoCodigo, tocarJa) {
      if (novoCodigo) codigo = novoCodigo;
      if (cartaz) cartaz.hidden = true;
      player.hidden = false;
      if (tocarJa) deitar();

      comAYouTube(function () {
        if (!inst) {
          // O toque via com o player a caminho: pediuTocar é a memória dele, atendida no onReady.
          if (codigo) { pediuTocar = !!tocarJa; criar(); }

          return;
        }
        if (codigo !== noQuadro) {
          noQuadro = codigo;
          // cue deixa o vídeo pronto sem tocar; load é o cue que já atende o toque dado.
          if (tocarJa) inst.loadVideoById(codigo); else inst.cueVideoById(codigo);
        } else if (tocarJa) {
          inst.playVideo();
        }
        etiquetar();
        pintar();
      });
    }

    if (cartaz) cartaz.addEventListener('click', function () { abrir(); });
    botaoTocar.addEventListener('click', alternar);
    // O vidro é a área do vídeo em si: tocar no selo também é play/pause.
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
    [vidro, player.querySelector('.video-controles')].forEach(function (camada) {
      camada.addEventListener('contextmenu', function (e) {
        e.preventDefault();

        return false;
      });
    });

    pintar();

    return {
      abrir: abrir,
      // Parar corta o áudio sem despeçar o player: é o que fechar a página precisa de fazer.
      parar: terminar,
      estado: function () { return { codigo: codigo, noQuadro: noQuadro, tocando: tocando() }; }
    };
  }

  function initVideo() {
    amarrarSaidaDaTelaCheia();

    document.querySelectorAll('.video-slot').forEach(function (slot) {
      controladores.set(slot, montarVideo(slot));
    });
  }

  /* ---------- Galeria de vídeos: o quadro só se abre quando o visitante escolhe ---------- */
  function initVideos() {
    var modal = document.getElementById('videoModal');
    var cards = document.querySelectorAll('[data-video]');
    if (!modal || !cards.length) return;

    var titulo = document.getElementById('videoModalTitulo');
    var slot = modal.querySelector('.video-slot');
    var botaoFechar = modal.querySelector('.video-modal-fechar');
    var video = controladores.get(slot);
    var ultimoCard = null;

    function abrir(card) {
      ultimoCard = card;
      titulo.textContent = card.getAttribute('data-titulo') || 'Tutorial em vídeo';
      slot.setAttribute('data-rotulo', titulo.textContent);
      // O modal abre primeiro: o quadro precisa estar visível no instante do play, senão o
      // navegador descarta o pedido de tela cheia feito no mesmo toque.
      modal.classList.add('aberta');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('video-aberto');
      // O card é o play: o vídeo do tutorial entra rodando, e em tela cheia na horizontal
      // quando a tela é de celular ou tablet.
      if (video) video.abrir(card.getAttribute('data-video'), true);
      botaoFechar.focus();
    }

    function fecharModal() {
      if (!modal.classList.contains('aberta')) return;
      modal.classList.remove('aberta');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('video-aberto');
      // Parar o vídeo é o que corta o áudio: só esconder o modal deixaria ele tocando.
      if (video) video.parar();
      if (ultimoCard) ultimoCard.focus();
    }

    cards.forEach(function (card) {
      card.addEventListener('click', function (e) {
        // Com qualquer tecla modificada o visitante quer a aba nova — deixa o link seguir.
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        e.preventDefault();
        abrir(card);
      });
    });

    modal.querySelectorAll('[data-video-close]').forEach(function (b) {
      b.addEventListener('click', fecharModal);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') fecharModal();
    });

    // aria-modal sem armadilha de foco jogaria o visitante para trás do véu.
    modal.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') return;
      var focos = modal.querySelectorAll('.video-modal-caixa a[href], .video-modal-caixa button');
      var primeiro = focos[0], ultimo = focos[focos.length - 1];
      if (e.shiftKey && document.activeElement === primeiro) { e.preventDefault(); ultimo.focus(); }
      else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primeiro.focus(); }
    });
  }

  /* ---------- Contagem regressiva da oferta ---------- */
  function initContagem() {
    document.querySelectorAll('[data-contagem]').forEach(function (caixa) {
      var alvo = Date.parse(caixa.getAttribute('data-contagem'));
      if (isNaN(alvo)) return;

      var bloco = caixa.closest('section') || caixa;
      var paineis = {};
      ['dias', 'horas', 'minutos', 'segundos'].forEach(function (k) {
        paineis[k] = caixa.querySelector('[data-cont="'+k+'"]');
      });

      var relogio;
      function pintar() {
        var falta = alvo - Date.now();

        if (falta <= 0) {
          // A data passou: a contagem sai de cena e entra o aviso de condição encerrada,
          // que é o que a página mostra para quem chegou por um anúncio ainda ativo.
          bloco.classList.add('encerrada');
          if (relogio) clearInterval(relogio);
          return;
        }

        var seg = Math.floor(falta / 1000);
        if (paineis.dias) paineis.dias.textContent = String(Math.floor(seg / 86400));
        if (paineis.horas) paineis.horas.textContent = String(Math.floor(seg / 3600) % 24).padStart(2, '0');
        if (paineis.minutos) paineis.minutos.textContent = String(Math.floor(seg / 60) % 60).padStart(2, '0');
        if (paineis.segundos) paineis.segundos.textContent = String(seg % 60).padStart(2, '0');
      }

      // Com a aba em segundo plano o navegador segura os intervalos; o visibilitychange
      // pinta o valor certo no instante em que o visitante volta, sem esperar o próximo tique.
      document.addEventListener('visibilitychange', function () { if (!document.hidden) pintar(); });
      pintar();
      relogio = setInterval(pintar, 1000);
    });
  }

  /* ---------- Conta do cliente e servidores de rastreamento ----------
     Duas portas de entrada, dois gestos: o ícone da conta abre o submenu no hover (e no
     toque, no celular) com o portal da plataforma e o financeiro antigo; o botão dourado
     abre o modal que escolhe entre os dois servidores, porque são plataformas diferentes.
     Um painel suspenso e um modal, nenhum framework: o site não paga por isso. */
  function initAcesso() {
    var conta = document.querySelector('[data-acesso]');
    if (conta) {
      var botaoConta = conta.querySelector('.nav-login');
      var menuConta = conta.querySelector('.nav-acesso-menu');

      var contaAberta = function () { return conta.classList.contains('aberto'); };
      var abrirConta = function () {
        conta.classList.add('aberto');
        if (botaoConta) botaoConta.setAttribute('aria-expanded', 'true');
      };
      var fecharConta = function () {
        conta.classList.remove('aberto');
        if (botaoConta) botaoConta.setAttribute('aria-expanded', 'false');
      };

      if (botaoConta && menuConta) {
        // Hover é atalho de quem tem mouse; clique é o caminho do toque e do teclado.
        // Sem mouse (celular), o navegador ainda dispara mouseenter no toque: escutar
        // hover só onde ele existe evita abrir e fechar no mesmo gesto.
        if (!window.matchMedia || window.matchMedia('(hover: hover)').matches) {
          conta.addEventListener('mouseenter', abrirConta);
          conta.addEventListener('mouseleave', fecharConta);
        }
        conta.addEventListener('focusout', function (e) {
          if (!(e.relatedTarget instanceof Node) || !conta.contains(e.relatedTarget)) fecharConta();
        });

        botaoConta.addEventListener('click', function (e) {
          e.stopPropagation();
          if (contaAberta()) fecharConta(); else abrirConta();
        });

        document.addEventListener('click', function (e) {
          if (e.target instanceof Element && !e.target.closest('[data-acesso]')) fecharConta();
        });
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') fecharConta();
        });
      }
    }

    var modal = document.getElementById('acessoPlataformas');
    var abridores = document.querySelectorAll('[data-acesso-plataformas]');
    if (!modal || !abridores.length) return;

    var ultimoFoco = null;

    function fechar() {
      if (!modal.classList.contains('aberta')) return;
      modal.classList.remove('aberta');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('acesso-aberto');
      // O abridor da gaveta desaparece junto com ela: sem destino visível, o foco
      // volta para o botão do menu, que é o que o visitante vê na tela.
      var destino = ultimoFoco;
      if (!destino || !destino.getClientRects().length) destino = document.querySelector('.nav-toggle');
      if (destino && typeof destino.focus === 'function') destino.focus();
      ultimoFoco = null;
    }

    function abrir(disparador) {
      // Quem abre pela gaveta perde o botão quando ela fecha: o retorno do foco
      // combinado na abertura é o botão do menu, não um elemento saindo da tela.
      ultimoFoco = disparador && !disparador.closest('.nav-drawer') ? disparador : null;
      if (!ultimoFoco) ultimoFoco = document.querySelector('.nav-toggle');
      modal.classList.add('aberta');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('acesso-aberto');
      var primeiro = modal.querySelector('.acesso-opcao') || modal.querySelector('.acesso-fechar');
      if (primeiro) primeiro.focus();
    }

    abridores.forEach(function (b) {
      b.addEventListener('click', function (e) {
        e.preventDefault();
        abrir(b);
      });
    });

    modal.querySelectorAll('[data-acesso-fechar]').forEach(function (b) {
      b.addEventListener('click', fechar);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') fechar();
    });

    // aria-modal sem armadilha de foco jogaria o visitante para trás do véu.
    modal.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') return;
      var focos = modal.querySelectorAll('.acesso-caixa a[href], .acesso-caixa button');
      var primeiro = focos[0], ultimo = focos[focos.length - 1];
      if (e.shiftKey && document.activeElement === primeiro) { e.preventDefault(); ultimo.focus(); }
      else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primeiro.focus(); }
    });
  }

  /* ---------- CTAs com rastreamento ---------- */
  function initCtas() {
    document.querySelectorAll('[data-track]').forEach(function (el) {
      el.addEventListener('click', function () {
        var evento = this.getAttribute('data-track');
        var cta = this.getAttribute('data-cta') || '';
        track(evento, { cta: cta });
        // Só o que o painel entende vai para o farol; generate_lead é o servidor que
        // registra (lead/pedido), para não contar duas vezes o mesmo cadastro.
        if (FAROL_DE_EVENTO[evento]) farol(FAROL_DE_EVENTO[evento], this.getAttribute('href'), cta);
      });
    });
  }

  /* ---------- Abertura rápida: antecipa o HTML do destino no primeiro gesto ---------- */
  function initPrefetch() {
    var c = navigator.connection;
    if (c && (c.saveData || /(^|-)2g$/.test(c.effectiveType || ''))) return;

    var ja = Object.create(null);

    function antecipar(alvo) {
      var a = alvo && alvo.closest ? alvo.closest('a[href]') : null;
      if (!a || a.target === '_blank' || a.hasAttribute('download')) return;

      var url;
      try { url = new URL(a.href, location.href); } catch (e) { return; }
      if (url.origin !== location.origin || url.pathname === location.pathname || url.hash || ja[url.href]) return;

      ja[url.href] = 1;
      var l = document.createElement('link');
      l.rel = 'prefetch';
      l.href = url.href;
      document.head.appendChild(l);
    }

    ['pointerover', 'focusin'].forEach(function (tipo) {
      document.addEventListener(tipo, function (e) { antecipar(e.target); }, { passive: true, capture: true });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initCookie(); initForms(); initKits(); initNav(); initSubmenus(); initAcesso(); initWhatsBox(); initCarrosseis(); initVideo(); initVideos(); initContagem(); initPrefetch(); initCtas(); initFarol();
    track('page_view', { page: location.pathname });
  });
})();
