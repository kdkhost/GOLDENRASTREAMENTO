/*
    O sino do painel. A primeira pintura vem do servidor (partial admin/partials/sino); daqui
    em diante este arquivo só pergunta ao JSON e redesenha a lista.

    Duas decisões que não são detalhe:
    - Com a aba escondida o intervalo é longo. Um painel aberto numa aba de fundo não tem por
      que bater no servidor a cada quarenta e cinco segundos.
    - A linha é montada com createElement/textContent, nunca com innerHTML: o texto do aviso
      carrega nome de cliente e mensagem de erro de cron, e isso não pode virar HTML.
*/
(function () {
    'use strict';

    var caixa = document.querySelector('[data-sino]');
    if (!caixa) { return; }

    var lista = caixa.querySelector('[data-sino-lista]');
    var contador = caixa.querySelector('[data-sino-contador]');
    var url = caixa.getAttribute('data-sino-url');
    var intervaloVisivel = parseInt(caixa.getAttribute('data-sino-visivel'), 10) || 45000;
    var intervaloEscondida = parseInt(caixa.getAttribute('data-sino-escondida'), 10) || 300000;
    var totalAnterior = contador ? (parseInt(contador.textContent, 10) || 0) : 0;
    var relogio = null;

    if (!lista || !contador || !url) { return; }

    function linha(dados) {
        var link = document.createElement('a');
        link.className = 'sino-item';
        link.href = String(dados.ficha || '#');

        var icone = document.createElement('span');
        icone.className = 'sino-icone tone-' + String(dados.tom || 'neutral');
        var marca = document.createElement('i');
        marca.className = 'fas ' + String(dados.icone || 'fa-bell');
        marca.setAttribute('aria-hidden', 'true');
        icone.appendChild(marca);

        var texto = document.createElement('span');
        texto.className = 'sino-texto';

        var titulo = document.createElement('span');
        titulo.className = 'sino-titulo';
        titulo.textContent = String(dados.titulo || '');
        texto.appendChild(titulo);

        if (dados.texto) {
            var resumo = document.createElement('span');
            resumo.className = 'sino-resumo';
            resumo.textContent = String(dados.texto);
            texto.appendChild(resumo);
        }

        var quando = document.createElement('span');
        quando.className = 'sino-quando';
        quando.textContent = String(dados.rotulo || '') + ' · ' + String(dados.quando || '');
        texto.appendChild(quando);

        link.appendChild(icone);
        link.appendChild(texto);

        return link;
    }

    function desenhar(dados) {
        var total = parseInt(dados.total, 10) || 0;

        while (lista.firstChild) { lista.removeChild(lista.firstChild); }

        if (!dados.itens || dados.itens.length === 0) {
            var vazio = document.createElement('div');
            vazio.className = 'sino-vazio';
            vazio.textContent = 'Nada novo por aqui.';
            lista.appendChild(vazio);
        } else {
            dados.itens.forEach(function (item) { lista.appendChild(linha(item)); });
        }

        contador.textContent = total > 99 ? '99+' : String(total);
        contador.classList.toggle('d-none', total === 0);

        if (total > totalAnterior && window.GoldenAvisos) {
            window.GoldenAvisos.info(total === 1
                ? 'Você tem 1 notificação nova.'
                : 'Você tem ' + total + ' notificações novas.');
        }

        totalAnterior = total;
    }

    function consultar() {
        if (document.hidden) { return; }

        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (resposta) { return resposta.ok ? resposta.json() : null; })
            .then(function (dados) { if (dados) { desenhar(dados); } })
            .catch(function () { /* sem rede, o sino espera a próxima volta em silêncio */ });
    }

    function agendar() {
        window.clearTimeout(relogio);
        relogio = window.setTimeout(function () {
            consultar();
            agendar();
        }, document.hidden ? intervaloEscondida : intervaloVisivel);
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { consultar(); }
        agendar();
    });

    agendar();
})();
