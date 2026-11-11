/*
   Os grupos do editor de plano: adicionar e tirar um bloco sem recarregar a tela.

   O bloco novo é copiado do `<template>` que a própria view desenha em Blade — nada de estrutura
   de formulário em string de JavaScript, senão o campo passaria a existir em dois lugares e o
   padrão da casa é um só. Depois de copiado, cada controle recebe o índice da sua posição: é o
   índice que o validador devolve como `grupos.3.palavras`, e um erro precisa cair exatamente na
   linha que a pessoa está vendo.

   Entrar e sair usam a mesma curva do resto do painel (`--ease`), e a saída só remove o bloco
   depois do movimento terminar — arrancar o nó no clique deixaria o buraco fechar de repente.
*/
(function () {
    montarTodos();

    // O painel troca de tela sem recarregar (golden-salvar.js): um editor que chegou na troca não
    // tinha DOM no carregamento, e sem esta escuta os botões ficariam mudos.
    document.addEventListener('golden:trocou', montarTodos);

    function montarTodos() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-grupos]'), iniciar);
    }

    function iniciar(raiz) {
        if (raiz.dataset.gruposMontado === '1') { return; }

        raiz.dataset.gruposMontado = '1';

        var modelo = document.querySelector('[data-grupo-modelo]');
        var adicionar = document.querySelector('[data-adiciona-grupo]');

        if (!modelo || !adicionar) { return; }

        var limite = parseInt(raiz.getAttribute('data-max') || '0', 10);

        adicionar.addEventListener('click', function () {
            var bloco = modelo.content.firstElementChild.cloneNode(true);

            raiz.appendChild(bloco);
            renumerar(raiz);

            bloco.classList.add('is-entrando');
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () { bloco.classList.remove('is-entrando'); });
            });

            checarLimite(raiz, limite);
        });

        raiz.addEventListener('click', function (evento) {
            var botao = evento.target.closest('[data-remove-grupo]');

            if (!botao) { return; }

            var bloco = botao.closest('[data-grupo]');

            if (!bloco || !raiz.contains(bloco) || linhas(raiz).length <= 1) { return; }

            bloco.classList.add('is-saindo');

            // Sem o fim do movimento (movimento reduzido, aba em segundo plano) o bloco precisa sair
            // mesmo assim: o prazo é o jeito de a pessoa não ficar olhando para um grupo já tirado.
            window.setTimeout(function () {
                if (bloco.parentNode) { bloco.parentNode.removeChild(bloco); }

                renumerar(raiz);
                checarLimite(raiz, limite);
            }, 320);
        });

        renumerar(raiz);
        checarLimite(raiz, limite);
    }

    function linhas(raiz) {
        return Array.prototype.slice.call(raiz.querySelectorAll(':scope > [data-grupo]'));
    }

    function renumerar(raiz) {
        var total = linhas(raiz);

        total.forEach(function (bloco, indice) {
            Array.prototype.forEach.call(bloco.querySelectorAll('[data-campo]'), function (campo) {
                var nome = campo.getAttribute('data-campo');
                var antigo = campo.id;
                var novo = 'f-grupos-' + indice + '-' + nome + '-';

                campo.setAttribute('name', 'grupos[' + indice + '][' + nome + ']');
                campo.setAttribute('id', novo);

                var rotulo = bloco.querySelector('label[for="' + antigo + '"]');

                if (rotulo) { rotulo.setAttribute('for', novo); }
            });

            // Um grupo sozinho não se tira: nenhum grupo no Google pode existir sem palavra-chave,
            // e o plano sem grupo é recusado inteiro.
            var tirar = bloco.querySelector('[data-remove-grupo]');

            if (tirar) { tirar.hidden = total.length <= 1; }
        });
    }

    function checarLimite(raiz, limite) {
        var botao = document.querySelector('[data-adiciona-grupo]');

        if (!botao) { return; }

        botao.disabled = limite > 0 && linhas(raiz).length >= limite;
    }
})();
