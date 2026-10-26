/* Abas do painel — um componente, usado na tela de Configurações (e em qualquer outra que
   marcar data-abas).
 *
 * O Bootstrap já abre e fecha as abas; aqui ficam as três coisas que ele não faz e a tela
 * precisa: devolver a pessoa à aba em que estava, obedecer a um link com hash
 * (#chaves-google-ads abre Chaves → Google Ads) e abrir a aba do primeiro campo com erro —
 * senão o painel salva, volta para a primeira aba e o motivo do erro fica escondido.
 *
 * Nada aqui conhece o nome de uma tela: tudo vem de data-abas, data-aba e data-aba-painel. */
(function () {
    'use strict';

    // O botão e o painel pertencem a este conjunto de abas se o primeiro data-abas deles é ele
    // mesmo — é o que impede a aba de fora de enxergar os botões das abas internas.
    function dono(elemento, grupo) {
        return elemento.closest('[data-abas]') === grupo;
    }

    function botoes(grupo) {
        return Array.prototype.filter.call(grupo.querySelectorAll('[data-aba]'), function (el) {
            return dono(el, grupo);
        });
    }

    function paineis(grupo) {
        return Array.prototype.filter.call(grupo.querySelectorAll('[data-aba-painel]'), function (el) {
            return dono(el, grupo);
        });
    }

    function idsDosPaineis(grupo) {
        return paineis(grupo).map(function (el) { return el.dataset.abaPainel; });
    }

    function ativar(grupo, id) {
        var botao = botoes(grupo).filter(function (el) { return el.dataset.aba === id; })[0];

        if (!botao) {
            return false;
        }

        window.bootstrap.Tab.getOrCreateInstance(botao).show();

        return true;
    }

    // #chaves-google-ads abre a aba "chaves" (o prefixo é o nome de uma aba) e, dentro dela,
    // o grupo "google-ads"; #contato abre a aba de mesmo nome. Um formato só para os dois níveis.
    function idDoHash(grupo, hash) {
        if (!hash) {
            return null;
        }

        var ids = idsDosPaineis(grupo);

        if (ids.indexOf(hash) !== -1) {
            return hash;
        }

        for (var i = 0; i < ids.length; i++) {
            if (hash.indexOf(ids[i] + '-') === 0) {
                return ids[i];
            }
        }

        var prefixo = grupo.dataset.abas + '-';

        if (hash.indexOf(prefixo) === 0) {
            var resto = hash.slice(prefixo.length);

            if (ids.indexOf(resto) !== -1) {
                return resto;
            }
        }

        return null;
    }

    function hashDaAba(grupo, id) {
        var pai = grupo.parentElement ? grupo.parentElement.closest('[data-abas]') : null;

        return pai ? grupo.dataset.abas + '-' + id : id;
    }

    function guardada(chave) {
        try {
            return window.localStorage.getItem(chave);
        } catch (e) {
            return null;
        }
    }

    function guardar(chave, id) {
        try {
            window.localStorage.setItem(chave, id);
        } catch (e) {
            // Navegação privada pode recusar o armazenamento: a aba abre na primeira, e só.
        }
    }

    // A ordem de preferência é: hash (link de outra tela) → a última aba usada → a que veio
    // marcada no HTML. O erro de validação entra depois, e ganha de tudo.
    function escolher(grupo) {
        var porHash = idDoHash(grupo, window.location.hash.replace('#', ''));

        if (porHash) {
            return porHash;
        }

        var memoria = guardada('golden_aba_' + grupo.dataset.abas);
        var ids = idsDosPaineis(grupo);

        if (memoria && ids.indexOf(memoria) !== -1) {
            return memoria;
        }

        var aberta = paineis(grupo).filter(function (el) { return el.classList.contains('active'); })[0];

        return aberta ? aberta.dataset.abaPainel : ids[0];
    }

    // No celular a barra rola de lado, e a aba aberta pode ficar fora do campo de visão — quem
    // volta para a tela veria "Contato" acesa e o conteúdo de Chaves. A barra rola até ela,
    // só na horizontal: o scrollIntoView comum mexeria também na posição vertical da página.
    function revelar(botao) {
        var barra = botao.closest('.abas-nav, .abas-pilulas');

        if (!barra || barra.scrollWidth <= barra.clientWidth) {
            return;
        }

        var alvo = botao.getBoundingClientRect();
        var caixa = barra.getBoundingClientRect();
        var folga = 8;

        if (alvo.left < caixa.left + folga) {
            barra.scrollLeft += alvo.left - caixa.left - folga;
        } else if (alvo.right > caixa.right - folga) {
            barra.scrollLeft += alvo.right - caixa.right + folga;
        }
    }

    function revelarAtiva(grupo) {
        var ativa = botoes(grupo).filter(function (el) { return el.classList.contains('active'); })[0];

        if (ativa) {
            revelar(ativa);
        }
    }

    function idComErro(grupo) {
        var painel = paineis(grupo).filter(function (el) {
            return el.querySelector('.is-invalid, .invalid-feedback.d-block');
        })[0];

        return painel ? painel.dataset.abaPainel : null;
    }

    function montar(grupo) {
        if (!window.bootstrap || !window.bootstrap.Tab) {
            return;
        }

        var chave = 'golden_aba_' + grupo.dataset.abas;

        ativar(grupo, escolher(grupo));
        revelarAtiva(grupo);

        var erro = idComErro(grupo);

        if (erro) {
            ativar(grupo, erro);
            revelarAtiva(grupo);

            var primeiro = document.querySelector('.tab-pane.active .is-invalid, .tab-pane.active .invalid-feedback.d-block');

            if (primeiro && !window.__goldenAbaRolada) {
                window.__goldenAbaRolada = true;
                requestAnimationFrame(function () {
                    primeiro.scrollIntoView({ block: 'center', behavior: 'smooth' });
                });
            }
        }

        botoes(grupo).forEach(function (botao) {
            botao.addEventListener('shown.bs.tab', function () {
                guardar(chave, botao.dataset.aba);
                revelar(botao);

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', '#' + hashDaAba(grupo, botao.dataset.aba));
                }

                // Aba nova pode ser bem mais curta que a anterior: se a barra saiu por cima da
                // tela, ela volta para o topo em vez de deixar a pessoa no meio do vazio.
                var barra = grupo.querySelector('.abas-barra, .abas-pilulas');

                if (barra && barra.getBoundingClientRect().top < 0) {
                    barra.scrollIntoView({ block: 'start', behavior: 'smooth' });
                }
            });
        });

        // Link para uma aba desta mesma tela (#chaves-avaliacoes vindo de um atalho) também age.
        window.addEventListener('hashchange', function () {
            var alvo = idDoHash(grupo, window.location.hash.replace('#', ''));

            if (alvo) {
                ativar(grupo, alvo);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('[data-abas]'), montar);
    });
})();
