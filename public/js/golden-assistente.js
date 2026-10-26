/* Atendimento com o assistente — o lado do navegador da conversa.
   O gatilho é o atendente virtual dentro da caixa do WhatsApp (`[data-ia-abrir]`), não um botão
   próprio: o robô é um dos atendentes da casa. Fala com os três endereços JSON (abrir, fala,
   falas) e não sabe nada de IA: quem decide responder, escalar ou calar é o servidor. Esta
   folha só pinta, pergunta e fecha a caixa quando a conversa abre. */
(function () {
  'use strict';

  var cfg = window.GOLDEN_IA;
  var botao = document.querySelector('[data-ia-abrir]');
  var painel = document.getElementById('iaPainel');
  if (!cfg || !botao || !painel) return;

  var corpo = painel.querySelector('[data-ia-corpo]');
  var digitando = painel.querySelector('[data-ia-digitando]');
  var form = painel.querySelector('[data-ia-form]');
  var campo = document.getElementById('iaTexto');
  var envio = painel.querySelector('[data-ia-enviar]');
  var saida = painel.querySelector('[data-ia-humano]');
  var aviso = document.querySelector('[data-ia-aviso]');
  var caixa = document.getElementById('waPopup');
  var flutuante = document.getElementById('waFloat');
  var semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var aberto = false;      // o balão está na tela
  var criada = false;      // a conversa já foi aberta com o servidor
  var de = 0;              // a última fala pintada — o servidor devolve só o que veio depois
  var ocupado = false;     // uma chamada está no ar
  var escalada = false;    // a equipe assumiu: pode chegar resposta humana
  var guardadas = [];      // o que foi pintado, para o resumo levado ao formulário
  var relógio = null;

  /* ---------- a chamada ---------- */

  function token() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  function enviar_post(url, dados) {
    return fetch(url, {
      method: 'POST',
      keepalive: true,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token() },
      body: JSON.stringify(Object.assign({ canal: cfg.canal, pagina: location.pathname }, dados || {}))
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .catch(function () { return {}; });
  }

  function obter_falas() {
    return fetch(cfg.falas + '?de=' + de, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .catch(function () { return {}; });
  }

  /* ---------- a tela ---------- */

  function pintura(fala) {
    var balao = document.createElement('div');
    balao.className = 'ia-fala ' + fala.autor;

    if (fala.autor !== 'sistema') {
      var quem = document.createElement('b');
      quem.textContent = (fala.assinatura || (fala.autor === 'cliente' ? 'Você' : cfg.nome))
        + (fala.em ? ' · ' + fala.em : '');
      balao.appendChild(quem);
    }

    var texto = document.createElement('span');
    // textContent, sempre: é o que o visitante escreveu, e nunca vira HTML na tela.
    texto.textContent = fala.texto || '';
    balao.appendChild(texto);

    return balao;
  }

  function nota(texto) {
    corpo.appendChild(pintura({ autor: 'sistema', texto: texto }));
    rolar();
  }

  function noFim() {
    return corpo.scrollHeight - corpo.scrollTop - corpo.clientHeight < 120;
  }

  function rolar() {
    corpo.scrollTop = corpo.scrollHeight;
  }

  function pinta(falas) {
    var trouxe = false;

    (falas || []).forEach(function (fala) {
      if (!fala || fala.id <= de) return;
      de = fala.id;
      // A fala do cliente volta do servidor com o texto aparado: é ela que fica na tela,
      // no lugar do balão provisório pintado no momento do envio.
      if (fala.autor === 'cliente') limpaPendente();
      corpo.appendChild(pintura(fala));
      guardadas.push(fala);
      if (fala.autor === 'equipe' && !aberto) marcaAviso();
      trouxe = true;
    });

    if (trouxe && aberto && noFim()) rolar();

    return trouxe;
  }

  // O balão desenhado no clique, antes de o servidor confirmar a fala.
  function limpaPendente() {
    var balao = corpo.querySelector('[data-pendente]');
    if (balao) balao.remove();
  }

  function marcaAviso() {
    if (aviso) aviso.hidden = false;
  }

  /* A caixa do WhatsApp e a conversa ocupam o mesmo canto da tela: abrir uma fecha a outra,
     senão os dois painéis se sobrepõem. */
  function fechaCaixa() {
    if (caixa) caixa.classList.remove('show');
    if (flutuante) flutuante.setAttribute('aria-expanded', 'false');
  }

  /* A ficha foi desligada no painel depois de a página ser pintada, ou o servidor disse que este
     visitante não é gente: o atendente sai da caixa e o balão sai de cena inteiro. */
  function desaparece() {
    botao.hidden = true;
    painel.hidden = true;
    fechaCaixa();
    paraVarredura();
  }

  /* ---------- a varredura ---------- */

  function varre() {
    if (ocupado || !criada) return;

    obter_falas().then(function (d) {
      if (d.ativo === false) { desaparece(); return; }
      if (d.status === 'aguardando_equipe') escalada = true;
      pinta(d.falas);
    });
  }

  /* Com o balão aberto a resposta tem que chegar logo; fechado, só quem foi passado para a equipe
     é varrido — e devagar. Ninguém fica martelando o servidor de hospedagem compartilhada. */
  function ajustaVarredura() {
    var intervalo = aberto ? 5000 : 30000;

    if (!aberto && !(escalada && criada)) { paraVarredura(); return; }

    paraVarredura();
    relógio = setInterval(function () {
      if (document.visibilityState === 'visible') varre();
    }, intervalo);
  }

  function paraVarredura() {
    if (relógio) clearInterval(relógio);
    relógio = null;
  }

  /* ---------- abrir e fechar ---------- */

  function abre() {
    fechaCaixa();
    aberto = true;
    painel.classList.add('aberto');
    botao.setAttribute('aria-expanded', 'true');
    if (aviso) aviso.hidden = true;
    ajustaVarredura();

    if (criada) {
      varre();
    } else {
      ocupado = true;
      digitando.hidden = false;
      enviar_post(cfg.abrir, { de: de }).then(function (d) {
        ocupado = false;
        digitando.hidden = true;
        if (!d.ativo) { desaparece(); return; }
        criada = true;
        escalada = d.status === 'aguardando_equipe';
        pinta(d.falas);
        ajustaVarredura();
      });
    }

    // O teclado só sobe em tela larga: no celular ele cobriria o balão recém-aberto.
    if (window.matchMedia('(min-width: 900px)').matches) campo.focus();
  }

  function fecha() {
    aberto = false;
    painel.classList.remove('aberto');
    botao.setAttribute('aria-expanded', 'false');
    ajustaVarredura();
  }

  botao.addEventListener('click', function () { aberto ? fecha() : abre(); });
  painel.querySelectorAll('[data-ia-fechar]').forEach(function (b) { b.addEventListener('click', fecha); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && aberto) fecha(); });
  // O golden-site.js avisa quando a caixa do WhatsApp abre: a conversa sai de cena para não
  // ficar embaixo dela.
  document.addEventListener('golden:wa-aberto', function () { if (aberto) fecha(); });

  /* ---------- a fala do cliente ---------- */

  campo.addEventListener('input', function () {
    campo.style.height = 'auto';
    campo.style.height = Math.min(campo.scrollHeight, 112) + 'px';
  });

  campo.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    var texto = campo.value.trim();
    if (!texto || ocupado) return;

    campo.value = '';
    campo.style.height = 'auto';
    ocupado = true;
    envio.disabled = true;
    digitando.hidden = false;

    // O balão do cliente entra na hora; quando a resposta voltar, a fala real do servidor
    // ocupa o lugar dele. Sem isso, dez segundos de IA pareceriam nada acontecendo.
    var balao = pintura({ autor: 'cliente', texto: texto });
    balao.setAttribute('data-pendente', '');
    corpo.appendChild(balao);
    rolar();

    enviar_post(cfg.fala, { texto: texto }).then(function (d) {
      if (!d.ativo) {
        limpaPendente();
        desaparece();
        return;
      }
      if (!d.falas) {
        limpaPendente();
        nota(d.message || 'A conversa travou aqui. Tente de novo em um instante.');
        return;
      }
      escalada = d.status === 'aguardando_equipe' || escalada;
      pinta(d.falas);
    }).finally(function () {
      ocupado = false;
      envio.disabled = false;
      digitando.hidden = true;
      if (aberto) campo.focus();
      ajustaVarredura();
    });
  });

  /* ---------- a pessoa por trás do robô ---------- */

  /* O cadastro é do formulário que a página já tem: a bolha rola até ele em vez de montar outro.
     Sem formulário na tela, o /contato da casa leva o resumo da conversa — ninguém reescreve
     o que já contou. */
  function temFormulario() {
    return document.querySelector('[data-lead-form]');
  }

  function resumo() {
    var linhas = guardadas.slice(-8).map(function (fala) {
      return (fala.autor === 'cliente' ? 'Eu: ' : (fala.assinatura || cfg.nome) + ': ')
        + String(fala.texto || '').replace(/\s+/g, ' ');
    });

    var texto = 'Conversa com ' + cfg.nome + ' no site:\n' + linhas.join('\n');

    return texto.length > 900 ? texto.slice(0, 900) + '…' : texto;
  }

  saida.addEventListener('click', function (e) {
    var alvo = temFormulario();

    if (!alvo) {
      // Sem preventDefault: é um link comum para /contato, com o resumo no campo de mensagem.
      saida.setAttribute('href', cfg.contato + '?message=' + encodeURIComponent(resumo()));
      return;
    }

    e.preventDefault();
    fecha();
    alvo.scrollIntoView({ behavior: semMovimento ? 'auto' : 'smooth', block: 'center' });

    var primeiro = alvo.querySelector('input:not([type=hidden]):not([type=checkbox]), textarea, select');
    if (primeiro) setTimeout(function () { primeiro.focus({ preventScroll: true }); }, semMovimento ? 0 : 420);
  });
})();
