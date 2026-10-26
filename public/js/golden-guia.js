/*
| O que a tela do guia faz no navegador: procura e acompanha.
|
| Procurar é filtrar o texto que já chegou — a página vem inteira do config, e um campo de busca
| que pergunta ao servidor para filtrar vinte seções seria peso sem ganho.
|
| Acompanhar é marcar na coluna da esquerda a seção que está na frente do leitor, com
| IntersectionObserver em vez de escuta de rolagem: nada é lido quadro a quadro, e a marcação não
| compete com o movimento da página.
*/
(function () {
  var campo = document.getElementById('guia-busca');
  var secoes = [].slice.call(document.querySelectorAll('[data-secao]'));
  if (!secoes.length) return;

  var nav = [].slice.call(document.querySelectorAll('.guia-nav-link'));
  var grupos = [].slice.call(document.querySelectorAll('[data-grupo]'));
  var vazio = document.querySelector('.guia-nav-vazio');

  function limpar(texto) {
    // Acento fora e espaço sobrando fora: "notificacao" encontra "notificação".
    return String(texto || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
  }

  var textoDe = {};
  secoes.forEach(function (secao) {
    textoDe[secao.id] = limpar(secao.textContent);
  });

  function filtrar() {
    var termo = limpar(campo ? campo.value : '');
    var vistas = 0;

    secoes.forEach(function (secao) {
      var mostra = termo === '' || textoDe[secao.id].indexOf(termo) !== -1;
      secao.classList.toggle('guia-secao--oculta', !mostra);
      if (mostra) vistas++;
    });

    nav.forEach(function (link) {
      var alvo = document.getElementById(link.getAttribute('data-alvo'));
      link.classList.toggle('d-none', !alvo || alvo.classList.contains('guia-secao--oculta'));
    });

    // O título do grupo só continua na tela se sobrou alguma seção dele.
    grupos.forEach(function (titulo) {
      var proximo = titulo.nextElementSibling;
      var temAlgo = false;
      while (proximo && !proximo.hasAttribute('data-grupo')) {
        if (proximo.hasAttribute('data-secao') && !proximo.classList.contains('guia-secao--oculta')) temAlgo = true;
        proximo = proximo.nextElementSibling;
      }
      titulo.classList.toggle('d-none', !temAlgo);
    });

    if (vazio) vazio.classList.toggle('d-none', vistas > 0);
  }

  if (campo) campo.addEventListener('input', filtrar);

  if (!('IntersectionObserver' in window)) return;

  var visiveis = {};
  var observador = new IntersectionObserver(function (entradas) {
    entradas.forEach(function (entrada) {
      visiveis[entrada.target.id] = entrada.isIntersecting;
    });

    // A primeira seção ainda visível, na ordem da página — não a que o navegador reportou por
    // último: duas seções se cruzam na dobra, e a marcação pulando entre elas é o tremor que a
    // pessoa vê.
    var atual = secoes.filter(function (s) {
      return !s.classList.contains('guia-secao--oculta') && visiveis[s.id];
    })[0];

    nav.forEach(function (link) {
      link.classList.toggle('is-ativa', !!atual && link.getAttribute('data-alvo') === atual.id);
    });
  }, { rootMargin: '-96px 0px -60% 0px', threshold: 0 });

  secoes.forEach(function (secao) { observador.observe(secao); });
})();
