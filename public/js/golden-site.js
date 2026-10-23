/* Golden Rastreamento — front do site */
(function () {
  'use strict';

  var G = window.GOLDEN || {};
  var trackingOn = G.tracking === true || G.tracking === '1' || G.tracking === 1;

  /* ---------- UTMs + identificadores de clique ---------- */
  var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid'];

  function readQuery() {
    var p = new URLSearchParams(window.location.search), out = {};
    UTM_KEYS.forEach(function (k) { if (p.get(k)) out[k] = p.get(k); });
    return out;
  }
  function saveAttribution() {
    var q = readQuery();
    if (Object.keys(q).length) {
      var stored = {};
      try { stored = JSON.parse(sessionStorage.getItem('golden_attr') || '{}'); } catch (e) {}
      sessionStorage.setItem('golden_attr', JSON.stringify(Object.assign(stored, q)));
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

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var alertBox = form.querySelector('.form-alert');
        var btn = form.querySelector('button[type="submit"]');
        if (alertBox) { alertBox.className = 'form-alert'; alertBox.style.display = 'none'; }

        // injeta atribuição de campanha nos campos ocultos
        var attr = getAttribution();
        UTM_KEYS.forEach(function (k) {
          var input = form.querySelector('[name="' + k + '"]');
          if (input && attr[k]) input.value = attr[k];
        });

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
            if (alertBox) {
              alertBox.style.display = 'block';
              if (res.ok && res.d.success) {
                alertBox.className = 'form-alert ok';
                alertBox.textContent = res.d.message || 'Recebemos sua solicitação.';
                if (res.d.whatsapp_url) {
                  var link = document.createElement('a');
                  link.href = res.d.whatsapp_url;
                  link.target = '_blank';
                  link.rel = 'noopener noreferrer';
                  link.textContent = ' Continuar no WhatsApp ↗';
                  alertBox.appendChild(link);
                }
                form.reset();
                track('lead_success', {});
              } else {
                alertBox.className = 'form-alert err';
                var msg = res.d && res.d.message ? res.d.message : 'Não foi possível enviar agora. Tente novamente.';
                if (res.d && res.d.errors) {
                  var first = Object.keys(res.d.errors)[0];
                  msg = res.d.errors[first][0];
                }
                alertBox.textContent = msg;
              }
            }
          })
          .catch(function () {
            if (alertBox) { alertBox.style.display = 'block'; alertBox.className = 'form-alert err'; alertBox.textContent = 'Erro de conexão. Tente novamente.'; }
          })
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
      });
    });
  }

  /* ---------- Menu deslizante (gaveta da esquerda) ---------- */
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
      document.body.classList.add('nav-open');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', 'Fechar menu');
    }

    function fechar() {
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

  /* ---------- CTAs com rastreamento ---------- */
  function initCtas() {
    document.querySelectorAll('[data-track]').forEach(function (el) {
      el.addEventListener('click', function () {
        track(this.getAttribute('data-track'), { cta: this.getAttribute('data-cta') || '' });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initCookie(); initForms(); initKits(); initNav(); initCtas();
    track('page_view', { page: location.pathname });
  });
})();
