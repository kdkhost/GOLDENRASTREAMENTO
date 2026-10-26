/*
| O que a tela do guia faz no navegador: troca de seção e procura.
|
| O índice à esquerda continua sendo o índice: clicar numa seção troca o que aparece à direita,
| em vez de rolar até ela no meio de uma lista longa. O endereço recebe a seção em `#id` por
| `history.replaceState`, que muda o endereço sem pedir ao navegador que role até a âncora — é o
| que mantém a janela exatamente onde estava. Quem rola, quando o texto é maior que a tela, é o
| corpo do cartão da leitura, por dentro.
|
| A busca filtra o índice e abre a primeira seção que casa; um endereço com ?busca=… chega já
| filtrado, porque o campo é o `x-admin.filter` da tela.
*/
(function () {
  var raiz = document.querySelector('[data-guia]');
  var conteudo = document.querySelector('[data-guia-conteudo]');
  var campo = document.getElementById('guia-busca');
  var secoes = [].slice.call(document.querySelectorAll('[data-secao]'));
  if (!raiz || !conteudo || !secoes.length) return;

  var nav = [].slice.call(document.querySelectorAll('.guia-nav-link'));
  var listaNav = document.querySelector('.guia-nav');
  var grupos = [].slice.call(document.querySelectorAll('[data-grupo]'));
  var titulosDoIndice = [].slice.call(document.querySelectorAll('[data-nav-grupo]'));
  var vazio = document.querySelector('.guia-nav-vazio');
  var ativaId = null;

  var porId = {};
  secoes.forEach(function (secao) { porId[secao.id] = secao; });

  function limpar(texto) {
    // Acento fora e espaço sobrando fora: "notificacao" encontra "notificação".
    return String(texto || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
  }

  var textoDe = {};
  secoes.forEach(function (secao) {
    textoDe[secao.id] = limpar(secao.textContent);
  });

  function casa(id, termo) {
    return termo === '' || textoDe[id].indexOf(termo) !== -1;
  }

  // O índice tem rolagem própria: a seção ativa pode estar além da dobra do cartão, e aí é o
  // índice que anda, nunca a página.
  function rolarIndice(link) {
    if (!listaNav || !link || listaNav.scrollHeight <= listaNav.clientHeight) return;

    var topo = link.offsetTop;
    if (topo < listaNav.scrollTop) {
      listaNav.scrollTop = Math.max(0, topo - 8);
    } else if (topo + link.offsetHeight > listaNav.scrollTop + listaNav.clientHeight) {
      listaNav.scrollTop = topo + link.offsetHeight - listaNav.clientHeight + 8;
    }
  }

  function recuarLeitura() {
    // Cada grupo tem o próprio corpo rolável; trocar de seção sempre começa do alto.
    [].slice.call(conteudo.querySelectorAll('.card-body')).forEach(function (corpo) {
      corpo.scrollTop = 0;
    });
  }

  function abrir(id, mudarEndereco, levarAoPainel) {
    var secao = porId[id] || secoes[0];
    if (!secao) return;

    ativaId = secao.id;

    var grupo = secao.closest('[data-grupo]');
    secoes.forEach(function (s) { s.classList.toggle('is-ativa', s === secao); });
    grupos.forEach(function (cartao) { cartao.classList.toggle('is-ativa', cartao === grupo); });

    nav.forEach(function (link) {
      var esta = link.getAttribute('data-alvo') === secao.id;
      link.classList.toggle('is-ativa', esta);
      if (esta) {
        link.setAttribute('aria-current', 'true');
        rolarIndice(link);
      } else {
        link.removeAttribute('aria-current');
      }
    });

    recuarLeitura();

    // `replaceState` troca o endereço sem rolar: o `#` de uma âncora é justamente o que faz a
    // janela pular, e a seção já está aberta aqui. A busca digitada vai junto, sem sumir da URL.
    if (mudarEndereco && window.history && window.history.replaceState) {
      try {
        window.history.replaceState(null, '', window.location.pathname + window.location.search + '#' + secao.id);
      } catch (e) {}
    }

    // No celular não existe "à direita": o painel fica abaixo do índice, e levá-lo à tela é a
    // única forma de a seção clicada aparecer. No desktop isso não roda — a janela fica parada.
    if (levarAoPainel && window.matchMedia('(max-width: 991.98px)').matches) {
      var suave = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
      conteudo.scrollIntoView({ behavior: suave, block: 'start' });
    }
  }

  function filtrar() {
    var termo = limpar(campo ? campo.value : '');
    var vistas = 0;

    nav.forEach(function (link) {
      var id = link.getAttribute('data-alvo');
      var mostra = !!porId[id] && casa(id, termo);
      link.classList.toggle('d-none', !mostra);
      if (mostra) vistas++;
    });

    // Cada grupo do índice some com o rótulo quando não sobrou nenhuma seção dele: rótulo sozinho
    // é engano.
    titulosDoIndice.forEach(function (titulo) {
      var proximo = titulo.nextElementSibling;
      var temAlgo = false;
      while (proximo && !proximo.hasAttribute('data-nav-grupo')) {
        if (proximo.classList.contains('guia-nav-link') && !proximo.classList.contains('d-none')) temAlgo = true;
        proximo = proximo.nextElementSibling;
      }
      titulo.classList.toggle('d-none', !temAlgo);
    });

    if (vazio) vazio.classList.toggle('d-none', vistas > 0);

    // A busca escolhe o que abrir; ela não despeja os resultados no painel. Quando a seção aberta
    // deixa de casar, abre a primeira que casa, e o índice continua mostrando o resto.
    if (termo !== '' && (!ativaId || !casa(ativaId, termo))) {
      var primeira = secoes.filter(function (secao) { return casa(secao.id, termo); })[0];
      if (primeira) abrir(primeira.id, true, false);
    }
  }

  // A altura da leitura é a da tela, não a da seção: trocar de seção não muda o tamanho do cartão
  // e não mexe na janela. O rodapé do painel entra na conta para a página não ganhar rolagem.
  function medir() {
    raiz.style.removeProperty('--guia-altura');
    raiz.classList.remove('guia-medido');

    if (window.matchMedia('(min-width: 992px)').matches) {
      var topo = conteudo.getBoundingClientRect().top + window.pageYOffset;
      var rodape = document.querySelector('.app-footer');
      var altura = window.innerHeight - topo - (rodape ? rodape.offsetHeight : 0) - 24;

      raiz.style.setProperty('--guia-altura', Math.max(320, Math.round(altura)) + 'px');
      raiz.classList.add('guia-medido');
    }

    // Com a altura já aplicada, o item ativo pode ter ficado fora da dobra do índice: um endereço
    // com #secao lá embaixo abriria a leitura certa com o índice mostrando o começo da lista.
    rolarIndice(document.querySelector('.guia-nav-link.is-ativa'));
  }

  nav.forEach(function (link) {
    link.addEventListener('click', function (evento) {
      evento.preventDefault();
      abrir(link.getAttribute('data-alvo'), true, true);
    });
  });

  if (campo) campo.addEventListener('input', filtrar);

  // Um endereço com #secao — o que o "Como usar esta tela" de todas as telas gera — abre direto
  // nela; sem seção no endereço, abre a primeira.
  var inicial = (window.location.hash || '').replace(/^#/, '');
  abrir(porId[inicial] ? inicial : secoes[0].id, false, false);
  filtrar();

  conteudo.setAttribute('data-pronta', '');
  medir();

  var espera;
  window.addEventListener('resize', function () {
    clearTimeout(espera);
    espera = setTimeout(medir, 150);
  });
  window.addEventListener('load', medir);
})();
