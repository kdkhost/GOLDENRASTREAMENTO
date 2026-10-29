/* golden-salvar.js — nenhuma gravação do painel recarrega a tela.
 *
 * O painel inteiro sempre gravou por POST de formulário: o navegador mandava a página embora e
 * ela voltava do zero — o scroll pulava, a aba fechava, o toast só aparecia depois da volta, e
 * quem tinha digitado errado perdia o que escreveu. Aqui está o caminho contrário: o mesmo
 * formulário sai por `fetch`, a resposta chega em JSON (ver App\Http\Middleware\ResponderEscritaAjax),
 * e a tela fica onde está.
 *
 * A regra é uma só para os 71 formulários do painel e do portal — nada de chamar função por tela:
 *
 *   · o que muda a própria tela não navega em lugar nenhum;
 *   · o que precisa de valor novo do servidor troca só a região que o formulário declara em
 *     `data-atualiza` (um GET discreto, sem tocar no resto da página);
 *   · o que muda de tela vai buscá-la e troca o conteúdo, se a tela de destino não exigir script
 *     que esta página não tem — aí, e só aí, o navegador vai de verdade, porque tela quebrada
 *     pisca muito mais que tela carregada;
 *   · erro de validação vermelha o campo no lugar, abre a aba onde ele está e devolve o foco, sem
 *     apagar nada do que a pessoa escreveu.
 *
 * Um `<form>` que não é de escrita (GET dos filtros), que pede algo ao navegador de verdade
 * (`data-sem-ajax`: download, OAuth, entrada e saída), que pertence a um componente vivo (o
 * Google Ads é Livewire e manda no próprio DOM) ou que já tem dono (`data-lead-form`, no site)
 * passa direto por aqui.
 *
 * O que cada formulário declara:
 *   · `data-atualiza` — as regiões, separadas por vírgula, que precisam voltar do servidor com o
 *     valor novo (o selo de status, a linha do tempo, o resultado da sonda);
 *   · `data-sem-ajax` — nada de segundo plano aqui. */
(function () {
    'use strict';

    var REGIAO_CONTEUDO = ['.app-content-header', '.app-content'];
    // Um script de dentro do conteúdo, ou um componente vivo: os dois inicializam a própria tela no
    // carregamento, e trocar por cima deixaria a página muda. É o que proíbe a troca em casa.
    var O_QUE_PRECISA_NASCER = REGIAO_CONTEUDO.map(function (s) { return s + ' script'; }).join(', ')
        + ', [wire\\:skeleton], [wire\\:snapshot]';
    var emVoo = new WeakMap();   // formulário => AbortController do pedido que ele disparou
    var buscaEmAndamento = null; // o GET de fundo em curso, para não empilhar refresh

    function tokenDe(form) {
        var campo = form.querySelector('input[name="_token"]');

        return campo ? campo.value : '';
    }

    // Um evento já barrado por outra folha não é nosso: é o upload segurando o envio até o
    // último arquivo terminar, ou o SweetAlert2 ainda perguntando se pode.
    function jaTemDono(form, evento) {
        if (evento.defaultPrevented || !(form instanceof HTMLFormElement)) {
            return true;
        }

        if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') {
            return true;
        }

        return form.hasAttribute('data-sem-ajax') || form.hasAttribute('data-lead-form')
            || !!form.closest('[data-sem-ajax]') || !form.querySelector('input[name="_token"]');
    }

    function ocupado(controle, ligado) {
        if (!(controle instanceof HTMLElement)) {
            return;
        }

        controle.classList.toggle('salvando', ligado);
        controle.disabled = ligado;

        if (ligado) {
            controle.setAttribute('aria-busy', 'true');
        } else {
            controle.removeAttribute('aria-busy');
        }
    }

    // O botão que disparou o envio é o que mostra o trabalho; os irmãos dele ficam quietos para
    // ninguém gravar duas vezes o mesmo formulário sem querer.
    function botoesDo(form, submitter) {
        var alvo = submitter instanceof HTMLElement ? submitter : form.querySelector('[type="submit"]');
        var lista = Array.prototype.slice.call(form.querySelectorAll('button, input[type="submit"]'));

        return lista.filter(function (b) { return b.type !== 'button'; }).map(function (b) {
            return { botao: b, principal: b === alvo };
        });
    }

    function cabecalho(form) {
        return {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': tokenDe(form),
            // A tela onde a pessoa está é o que decide se o destino do redirect é "fica" ou "vai":
            // o servidor não a conhece pelo endereço do POST (ver ResponderEscritaAjax).
            'X-Golden-Tela': window.location.href
        };
    }

    function enviar(form, evento) {
        var submitter = evento.submitter;
        var acao = form.getAttribute('action') || window.location.href;
        var alvo = submitter instanceof HTMLElement ? submitter : form.querySelector('[type="submit"]');
        var controles = botoesDo(form, submitter).map(function (par) {
            return { botao: par.botao, principal: par.botao === alvo, quieto: par.botao.disabled };
        });

        evento.preventDefault();
        limparErros(form);

        var pedido = new AbortController();
        emVoo.set(form, pedido);
        controles.forEach(function (par) {
            if (par.principal) { ocupado(par.botao, true); } else { par.botao.disabled = true; }
        });

        window.fetch(acao, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: cabecalho(form),
            signal: pedido.signal
        })
            .then(ler)
            .then(function (resposta) { aplicar(form, resposta); })
            .catch(function (erro) {
                if (erro && erro.name === 'AbortError') { return; }
                avisar([{ tipo: 'erro', texto: 'Não foi possível gravar agora. Confira a conexão e tente de novo.' }]);
            })
            .then(function () {
                emVoo.delete(form);
                // Volta exatamente ao estado de antes: um botão que já estava desligado na tela
                // (o status em que o lead já está, por exemplo) não pode acender porque gravou.
                controles.forEach(function (par) { ocupado(par.botao, false); par.botao.disabled = par.quieto; });
            });
    }

    function ler(resposta) {
        var tipo = resposta.headers.get('content-type') || '';

        if (resposta.status === 419) {
            return { sessao_morta: true };
        }

        if (tipo.indexOf('json') === -1) {
            return { nao_levou: true, status: resposta.status };
        }

        return resposta.json().then(function (dados) {
            if (resposta.status === 422 && dados && dados.errors) {
                return { ok: false, mensagens: [], campos: dados.errors, mesma_tela: true, redirecionar: null };
            }

            return dados;
        });
    }

    function aplicar(form, resposta) {
        if (!resposta) { return; }

        if (resposta.sessao_morta) {
            avisar([{ tipo: 'aviso', texto: 'A sessão desta janela expirou. Entre de novo para gravar.' }]);
            window.setTimeout(function () { window.location.assign(entrada()); }, 900);

            return;
        }

        if (resposta.nao_levou) {
            avisar([{ tipo: 'erro', texto: 'O servidor respondeu fora do combinado (' + resposta.status + '). Nada foi gravado duas vezes: recarregue a tela e confira.' }]);

            return;
        }

        avisar(resposta.mensagens || []);

        if (resposta.campos && Object.keys(resposta.campos).length) {
            pintarErros(form, resposta.campos);

            return;
        }

        if (!resposta.ok && !resposta.redirecionar) {
            return;
        }

        if (resposta.redirecionar) {
            irPara(resposta.redirecionar);

            return;
        }

        atualizar(form, resposta.mensagens || []);
    }

    function entrada() {
        var meta = document.querySelector('meta[name="golden-entrada"]');

        return meta ? meta.getAttribute('content') : window.location.href;
    }

    function avisar(mensagens) {
        if (!window.GoldenAvisos) { return; }

        mensagens.forEach(function (m) {
            var metodo = window.GoldenAvisos[m.tipo] ? m.tipo : 'info';
            window.GoldenAvisos[metodo](m.texto);
        });
    }

    /* ---------------------------------------------------------------- erro no campo */

    // `itens.0.nome` é como o validador guarda o erro; o campo no HTML se chama `itens[0][nome]`.
    function campoDo(form, nome) {
        var seletor = nome.replace(/\[(\d+)\]/g, '.$1').split('.').reduce(function (acumulado, parte, i) {
            return acumulado + (i ? '[' : '') + parte + (i ? ']' : '');
        }, '');

        return form.querySelector('[name="' + seletor + '"]')
            || form.querySelector('[name="' + seletor + '[]"]')
            || form.querySelector('[name="' + nome + '"]');
    }

    function pintarErros(form, campos) {
        var primeiro = null;

        Object.keys(campos).forEach(function (nome) {
            var controle = campoDo(form, nome);

            if (!controle) { return; }

            controle.classList.add('is-invalid');
            controle.setAttribute('aria-invalid', 'true');

            var caixa = irmaoDeErro(controle);
            caixa.textContent = (campos[nome] || [])[0] || '';
            caixa.hidden = false;

            if (!primeiro) { primeiro = controle; }
        });

        if (!primeiro) { return; }

        abrirAbaDe(primeiro);
        window.requestAnimationFrame(function () {
            primeiro.scrollIntoView({ block: 'center', behavior: 'smooth' });
            try { primeiro.focus({ preventScroll: true }); } catch (e) { primeiro.focus(); }
        });
    }

    function irmaoDeErro(controle) {
        var caixa = controle.parentNode.querySelector('.invalid-feedback');

        if (!caixa) {
            caixa = document.createElement('div');
            caixa.className = 'invalid-feedback d-block';
            controle.parentNode.insertBefore(caixa, controle.nextSibling);
        }

        return caixa;
    }

    function limparErros(form) {
        Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'), function (c) {
            c.classList.remove('is-invalid');
            c.removeAttribute('aria-invalid');
        });
        Array.prototype.forEach.call(form.querySelectorAll('.invalid-feedback'), function (c) {
            c.textContent = '';
            c.hidden = true;
        });
    }

    // O campo errado pode estar numa aba fechada: sem abrir a dela, a pessoa veria o vermelho
    // borda de um painel que não está na tela.
    function abrirAbaDe(controle) {
        var painel = controle.closest('[data-aba-painel]');

        if (!painel) { return; }

        var grupo = painel.closest('[data-abas]');
        var botao = grupo && grupo.querySelector('[data-aba="' + painel.dataset.abaPainel + '"]');

        if (botao) { botao.click(); }
    }

    /* ------------------------------------------------------- o que muda sem navegar */

    function atualizar(form, mensagens) {
        var seletores = (form.getAttribute('data-atualiza') || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);

        if (!seletores.length) {
            // Sem região declarada, a tela já diz a verdade: o que a pessoa gravou é o que está
            // nos campos dela. Um GET a mais por nada é exatamente o congestionamento evitado.
            if (mensagens.length === 0) { avisar([{ tipo: 'sucesso', texto: 'Salvo.' }]); }

            return;
        }

        recarregarRegioes(seletores);
    }

    function recarregarRegioes(seletores) {
        var url = window.location.href;

        if (buscaEmAndamento) { buscaEmAndamento.abort(); }

        var pedido = new AbortController();
        buscaEmAndamento = pedido;

        window.fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            signal: pedido.signal,
            cache: 'no-store'
        })
            .then(function (r) { return r.ok ? r.text() : ''; })
            .then(function (html) {
                if (pedido !== buscaEmAndamento) { return; }
                buscaEmAndamento = null;

                var novo = html ? new DOMParser().parseFromString(html, 'text/html') : null;

                // A mesma tela pode ter ganhado um componente vivo que precisa nascer do zero
                // (Livewire, um script de dentro do conteúdo). Trocar por cima deixaria a página
                // muda: aí a recarga é o caminho honesto, e ela acontece uma vez, depois de gravar.
                if (!novo || !podeTrocar(novo)) {
                    window.location.reload();

                    return;
                }

                seletores.forEach(function (seletor) {
                    var destino = document.querySelector(seletor);
                    var origem = novo.querySelector(seletor);

                    if (destino && origem) { destino.replaceWith(importar(origem)); }
                });

                document.dispatchEvent(new CustomEvent('golden:trocou'));
            })
            .catch(function () { /* a tela continua mostrando o que foi gravado: nada a reclamar */ });
    }

    /* ------------------------------------------------------------- troca de tela */

    function irPara(url) {
        var mesmaOrigem = url.indexOf(window.location.origin) === 0;

        if (!mesmaOrigem) {
            window.location.assign(url);

            return;
        }

        window.fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'text/html' },
            cache: 'no-store'
        })
            .then(function (r) { return r.ok ? r.text() : ''; })
            .then(function (html) {
                var novo = html ? new DOMParser().parseFromString(html, 'text/html') : null;

                if (!novo || !podeTrocar(novo)) {
                    window.location.assign(url);

                    return;
                }

                trocar(novo, url);
            })
            .catch(function () { window.location.assign(url); });
    }

    /**
     * Só troca em casa o que não precisa de nada que esta janela não tem. Uma tela com script
     * próprio (o painel de métricas, o editor do funil, um componente vivo) precisa do navegador
     * carregando-a de verdade — trocar e deixar o script de fora é a pior das duas telas.
     */
    function podeTrocar(doc) {
        var regioes = REGIAO_CONTEUDO.map(function (s) { return doc.querySelector(s); });

        if (regioes.some(function (r) { return !r; })) {
            return false;
        }

        var jaCarregados = {};

        Array.prototype.forEach.call(document.querySelectorAll('script[src]'), function (s) {
            jaCarregados[s.src] = true;
        });

        // Biblioteca nova nesta janela não se carrega do ar: teria que ser o navegador.
        if (Array.prototype.some.call(doc.querySelectorAll('script[src]'), function (s) {
            return !jaCarregados[s.src];
        })) {
            return false;
        }

        // Componente vivo (Livewire) ou script de dentro do conteúdo: os dois inicializam a própria
        // tela no carregamento, e trocar por cima deixaria a página muda.
        return !doc.querySelector(O_QUE_PRECISA_NASCER);
    }

    function importar(no) {
        return document.importNode(no, true);
    }

    function trocar(doc, url) {
        REGIAO_CONTEUDO.forEach(function (seletor) {
            var destino = document.querySelector(seletor);
            var origem = doc.querySelector(seletor);

            if (destino && origem) { destino.replaceWith(importar(origem)); }
        });

        if (doc.title) { document.title = doc.title; }

        if (window.history && window.history.pushState) {
            window.history.pushState({ golden: true }, '', url);
        }

        marcarMenu(url);
        window.scrollTo({ top: 0, behavior: 'auto' });
        document.dispatchEvent(new CustomEvent('golden:trocou'));
    }

    // A sidebar é do layout e não foi trocada com o conteúdo: sem isto ela continuaria apontando
    // para a tela de onde a pessoa veio.
    function marcarMenu(url) {
        var caminho = new URL(url, window.location.origin).pathname;
        var links = Array.prototype.slice.call(document.querySelectorAll('.sidebar-menu a[href]'));
        var atual = null;

        links.forEach(function (a) {
            var mesmo = a.getAttribute('href').replace(/^https?:\/\/[^/]+/, '').split('#')[0] === caminho;

            a.classList.remove('active');

            if (mesmo && !atual) { atual = a; }
        });

        if (!atual) { return; }

        atual.classList.add('active');

        var grupo = atual.closest('.nav-treeview');

        if (grupo) {
            var puxador = grupo.parentElement.querySelector(':scope > .nav-link');

            if (puxador) { puxador.classList.add('active'); }
            grupo.parentElement.classList.add('menu-open');
        }
    }

    document.addEventListener('submit', function (evento) {
        var form = evento.target;

        if (jaTemDono(form, evento)) { return; }

        if (emVoo.has(form)) {
            evento.preventDefault();

            return;
        }

        enviar(form, evento);
    });
})();
