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
    drawer.addEventListener('click', function (e) { if (e.target.closest('a')) fechar(); });
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

  /* ---------- Galeria de vídeos: o player só entra no DOM quando o visitante abre ---------- */
  function initVideos() {
    var modal = document.getElementById('videoModal');
    var cards = document.querySelectorAll('[data-video]');
    if (!modal || !cards.length) return;

    var titulo = document.getElementById('videoModalTitulo');
    var player = modal.querySelector('.video-player');
    var yt = modal.querySelector('.video-modal-yt');
    var botaoFechar = modal.querySelector('.video-modal-fechar');
    var ultimoCard = null;

    function abrir(card) {
      ultimoCard = card;
      titulo.textContent = card.getAttribute('data-titulo') || 'Tutorial em vídeo';
      yt.href = card.getAttribute('href');
      var iframe = document.createElement('iframe');
      // autoplay dentro do modal: o visitante já disse que quer ver este vídeo ao clicar.
      iframe.src = 'https://www.youtube-nocookie.com/embed/' + card.getAttribute('data-video') + '?autoplay=1&rel=0';
      iframe.title = titulo.textContent;
      iframe.allow = 'autoplay; fullscreen; picture-in-picture';
      iframe.setAttribute('allowfullscreen', '');
      player.replaceChildren(iframe);
      modal.classList.add('aberta');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('video-aberto');
      botaoFechar.focus();
    }

    function fecharModal() {
      if (!modal.classList.contains('aberta')) return;
      modal.classList.remove('aberta');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('video-aberto');
      // Esvaziar o player é o que corta o áudio: só esconder o modal deixaria o iframe tocando.
      player.replaceChildren();
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
    initCookie(); initForms(); initKits(); initNav(); initSubmenus(); initWhatsBox(); initCarrosseis(); initVideos(); initContagem(); initPrefetch(); initCtas(); initFarol();
    track('page_view', { page: location.pathname });
  });
})();
