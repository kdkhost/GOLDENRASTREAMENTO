/* A gaveta de ajuda do painel.
   Cada ícone de ajuda aponta para um `<template>` da própria página. Clicar copia aquele corpo
   para a gaveta, troca o título e abre — nada vem da rede, nada se move no formulário, e a mesma
   gaveta serve ao cabeçalho da tela e a cada campo. */
(function () {
  var gaveta = document.getElementById('golden-ajuda');
  var corpo = document.getElementById('golden-ajuda-corpo');
  var titulo = document.querySelector('#golden-ajuda-titulo span');
  if (!gaveta || !corpo || typeof bootstrap === 'undefined') return;

  function abrir(id, rotulo) {
    var modelo = document.querySelector('template[data-ajuda-corpo="' + id + '"]');
    if (!modelo) return;

    corpo.replaceChildren(modelo.content.cloneNode(true));
    if (titulo) titulo.textContent = rotulo || 'Ajuda';

    var caixa = corpo.closest('.offcanvas-body');
    if (caixa) caixa.scrollTop = 0;

    // Trocar de assunto com a gaveta já aberta é o caminho mais comum: `show()` sobre o estado
    // aberto não faz nada, e o corpo novo aparece no mesmo gesto que abriu o primeiro.
    bootstrap.Offcanvas.getOrCreateInstance(gaveta).show();
  }

  document.addEventListener('click', function (e) {
    var botao = e.target instanceof Element ? e.target.closest('[data-ajuda]') : null;
    if (!botao) return;
    e.preventDefault();
    abrir(botao.getAttribute('data-ajuda'), botao.getAttribute('data-ajuda-titulo'));
  });
})();
