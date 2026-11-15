/* golden-summernote.js — Inicializa o editor Summernote em todos os <textarea>
 * do sistema, sincroniza em tempo real com previews e formulários AJAX, evita HTML
 * escapando e acompanha dinamicamente o tema Claro/Escuro da Golden. */
(function () {
    'use strict';

    var SELETOR_ALVOS = 'textarea:not([readonly]):not([disabled]):not([data-summernote="false"]):not([data-summernote-ativo="1"]):not(.note-codable):not(#iaTexto)';
    var ENTIDADE_TAG_REGEX = /&lt;\s*\/?(?:p|br|strong|b|em|i|u|s|strike|ul|ol|li|a|span|div|h[1-6]|blockquote|table|hr|font)\b/i;

    var BARRA_COMPLETA = [
        ['style', ['style']],
        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['table', ['table']],
        ['insert', ['link', 'picture', 'hr']],
        ['view', ['fullscreen', 'codeview', 'help']]
    ];

    var BARRA_COMPACTA = [
        ['font', ['bold', 'italic', 'underline', 'clear']],
        ['para', ['ul', 'ol']],
        ['insert', ['link']],
        ['view', ['codeview']]
    ];

    function jqueryDisponivel() {
        return typeof window.jQuery !== 'undefined' && window.jQuery.fn && typeof window.jQuery.fn.summernote === 'function';
    }

    function temaAtualEscuro() {
        var html = document.documentElement;
        var body = document.body;

        if (html && html.getAttribute('data-bs-theme') === 'dark') {
            return true;
        }

        if (html && (html.classList.contains('dark') || html.getAttribute('data-theme') === 'dark')) {
            return true;
        }

        return !!(body && (body.classList.contains('dark-mode') || body.getAttribute('data-bs-theme') === 'dark'));
    }

    function aplicarTemaNosEditores() {
        var escuro = temaAtualEscuro();
        document.querySelectorAll('.note-editor').forEach(function (editor) {
            editor.classList.toggle('note-theme-dark', escuro);
            editor.classList.toggle('note-theme-light', !escuro);
        });
    }

    function decodificarEntidades(texto) {
        if (!texto || !ENTIDADE_TAG_REGEX.test(texto)) {
            return texto || '';
        }

        var decodificador = document.createElement('textarea');
        decodificador.innerHTML = texto;

        return decodificador.value;
    }

    function escaparHtml(texto) {
        return String(texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function prepararHtmlInicial(textarea) {
        var bruto = textarea.value || '';

        if (bruto.trim() === '') {
            return '';
        }

        var decodificado = decodificarEntidades(bruto);

        if (!/<[a-z][\s\S]*>/i.test(decodificado)) {
            return escaparHtml(decodificado).replace(/\r?\n/g, '<br>');
        }

        return decodificado;
    }

    function conteudoVisualmenteVazio(html) {
        if (!html) {
            return true;
        }

        if (/<(?:img|hr|table|iframe|svg|video|audio)\b/i.test(html)) {
            return false;
        }

        var textoLimpo = html
            .replace(/<[^>]*>/g, '')
            .replace(/&nbsp;/gi, ' ')
            .replace(/\u00a0/g, ' ')
            .trim();

        return textoLimpo === '';
    }

    function textoPuroDoEditor(html) {
        var temp = document.createElement('div');
        temp.innerHTML = String(html || '')
            .replace(/<\s*br\s*\/?>/gi, '\n')
            .replace(/<\/\s*(?:p|div|li|tr|h[1-6])\s*>/gi, '\n');

        return (temp.textContent || temp.innerText || '')
            .replace(/\u00a0/g, ' ')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    function calcularAltura(textarea) {
        if (textarea.id === 'corpo_html') {
            return 340;
        }

        var linhas = parseInt(textarea.getAttribute('rows'), 10);

        if (!Number.isFinite(linhas) || linhas <= 0) {
            linhas = 4;
        }

        return Math.max(115, Math.min(420, linhas * 28));
    }

    function deveUsarBarraCompacta(textarea) {
        var linhas = parseInt(textarea.getAttribute('rows'), 10);

        if (textarea.id === 'corpo_html') {
            return false;
        }

        return Number.isFinite(linhas) && linhas > 0 && linhas <= 3;
    }

    function editorDoTextarea(textarea) {
        var proximo = textarea ? textarea.nextElementSibling : null;

        if (proximo && proximo.classList && proximo.classList.contains('note-editor')) {
            return proximo;
        }

        return null;
    }

    function sincronizarParaTextarea(textarea, dispararInput, dispararChange) {
        if (!textarea || !jqueryDisponivel()) {
            return;
        }

        var $ta = window.jQuery(textarea);
        if (!$ta.data('summernote')) {
            return;
        }

        var editor = editorDoTextarea(textarea);
        var codable = editor ? editor.querySelector('.note-codable') : null;
        var emCodeview = !!(editor && editor.classList.contains('codeview') && codable);
        var html = emCodeview ? codable.value : $ta.summernote('code');
        var valorFinal = conteudoVisualmenteVazio(html) ? '' : html;

        if (textarea.value !== valorFinal) {
            textarea.value = valorFinal;
        }

        if (dispararInput && !textarea._goldenSnSilencioso) {
            textarea._goldenSnSilencioso = true;
            try {
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                textarea.dispatchEvent(new CustomEvent('golden:summernote-input', { bubbles: true }));
                if (dispararChange) {
                    textarea.dispatchEvent(new Event('change', { bubbles: true }));
                }
            } finally {
                textarea._goldenSnSilencioso = false;
            }
        }
    }

    function sincronizarDoTextareaParaEditor(textarea) {
        if (!textarea || !jqueryDisponivel()) {
            return;
        }

        var $ta = window.jQuery(textarea);
        if (!$ta.data('summernote')) {
            return;
        }

        var html = prepararHtmlInicial(textarea);
        textarea._goldenSnSilencioso = true;
        try {
            $ta.summernote('code', html);
        } finally {
            textarea._goldenSnSilencioso = false;
        }
        sincronizarParaTextarea(textarea, true, true);
    }

    function focarEditor(textarea) {
        var editor = editorDoTextarea(textarea);
        if (!editor) {
            return;
        }

        var alvo = editor.classList.contains('codeview')
            ? editor.querySelector('.note-codable')
            : editor.querySelector('.note-editable');

        editor.scrollIntoView({ block: 'center', behavior: 'smooth' });
        if (alvo && typeof alvo.focus === 'function') {
            try { alvo.focus({ preventScroll: true }); } catch (e) { alvo.focus(); }
        }
    }

    function ehTextareaElegivel(textarea) {
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return false;
        }

        if (textarea.dataset.summernoteAtivo === '1') {
            return false;
        }

        if (textarea.classList.contains('note-codable')) {
            return false;
        }

        if (textarea.closest('.note-editor, .note-frame, .note-modal, .note-popover, .note-dialog, template')) {
            return false;
        }

        return textarea.matches(SELETOR_ALVOS);
    }

    function inicializarTextarea(textarea) {
        if (!ehTextareaElegivel(textarea)) {
            return;
        }

        var $ta = window.jQuery(textarea);
        if ($ta.data('summernote')) {
            textarea.dataset.summernoteAtivo = '1';
            return;
        }

        // Remove editor órfão caso um DOM morph ou replace tenha deixado markup anterior.
        while (editorDoTextarea(textarea)) {
            editorDoTextarea(textarea).remove();
        }

        if (textarea.hasAttribute('required')) {
            textarea.dataset.summernoteRequired = '1';
            textarea.removeAttribute('required');
        }

        var htmlInicial = prepararHtmlInicial(textarea);
        var altura = calcularAltura(textarea);
        var toolbar = deveUsarBarraCompacta(textarea) ? BARRA_COMPACTA : BARRA_COMPLETA;
        var placeholder = textarea.getAttribute('placeholder') || 'Digite o conteúdo aqui...';

        textarea.dataset.summernoteAtivo = '1';

        $ta.summernote({
            lang: 'pt-BR',
            height: altura,
            minHeight: Math.min(altura, 110),
            maxHeight: 560,
            dialogsInBody: true,
            disableDragAndDrop: false,
            placeholder: placeholder,
            toolbar: toolbar,
            styleTags: ['p', 'blockquote', 'pre', 'h2', 'h3', 'h4', 'h5'],
            callbacks: {
                onInit: function () {
                    if (htmlInicial !== '') {
                        textarea._goldenSnSilencioso = true;
                        try {
                            $ta.summernote('code', htmlInicial);
                        } finally {
                            textarea._goldenSnSilencioso = false;
                        }
                    }
                    sincronizarParaTextarea(textarea, false, false);
                    aplicarTemaNosEditores();

                    var editor = editorDoTextarea(textarea);
                    var editable = editor ? editor.querySelector('.note-editable') : null;
                    var codable = editor ? editor.querySelector('.note-codable') : null;

                    if (editable) {
                        editable.addEventListener('input', function () {
                            sincronizarParaTextarea(textarea, true, false);
                        });
                        editable.addEventListener('keyup', function () {
                            sincronizarParaTextarea(textarea, true, false);
                        });
                        editable.addEventListener('paste', function () {
                            window.setTimeout(function () {
                                sincronizarParaTextarea(textarea, true, false);
                            }, 0);
                        });
                        editable.addEventListener('cut', function () {
                            window.setTimeout(function () {
                                sincronizarParaTextarea(textarea, true, false);
                            }, 0);
                        });
                    }

                    if (editor) {
                        editor.addEventListener('click', function (ev) {
                            var alvo = ev.target instanceof Element ? ev.target : null;
                            if (alvo && alvo.closest('.note-toolbar, .note-popover')) {
                                window.setTimeout(function () {
                                    sincronizarParaTextarea(textarea, true, true);
                                }, 15);
                            }
                        });
                    }

                    if (codable) {
                        codable.setAttribute('data-summernote', 'false');
                        codable.dataset.summernoteAtivo = '1';
                        codable.addEventListener('input', function () {
                            sincronizarParaTextarea(textarea, true, false);
                        });
                        codable.addEventListener('keyup', function () {
                            sincronizarParaTextarea(textarea, true, false);
                        });
                        codable.addEventListener('paste', function () {
                            window.setTimeout(function () {
                                sincronizarParaTextarea(textarea, true, false);
                            }, 0);
                        });
                        codable.addEventListener('change', function () {
                            sincronizarParaTextarea(textarea, true, true);
                        });
                    }
                },
                onChange: function () {
                    sincronizarParaTextarea(textarea, true, false);
                },
                onKeyup: function () {
                    sincronizarParaTextarea(textarea, true, false);
                },
                onPaste: function () {
                    window.setTimeout(function () {
                        sincronizarParaTextarea(textarea, true, false);
                    }, 0);
                },
                onBlur: function () {
                    sincronizarParaTextarea(textarea, true, true);
                },
                onBlurCodeview: function () {
                    sincronizarParaTextarea(textarea, true, true);
                }
            }
        });

        textarea.addEventListener('focus', function () {
            focarEditor(textarea);
        });
    }

    function inicializarTodos(raiz) {
        if (!jqueryDisponivel()) {
            return;
        }

        var escopo = raiz && raiz.querySelectorAll ? raiz : document;
        escopo.querySelectorAll(SELETOR_ALVOS).forEach(function (textarea) {
            inicializarTextarea(textarea);
        });
        aplicarTemaNosEditores();
    }

    function sincronizarFormulario(form) {
        if (!form || !jqueryDisponivel()) {
            return;
        }

        form.querySelectorAll('textarea[data-summernote-ativo="1"]').forEach(function (textarea) {
            sincronizarParaTextarea(textarea, false, false);
        });
    }

    function inserirTexto(textarea, texto) {
        if (!textarea) {
            return;
        }

        if (!jqueryDisponivel() || !window.jQuery(textarea).data('summernote')) {
            var inicio = textarea.selectionStart != null ? textarea.selectionStart : textarea.value.length;
            var fim = textarea.selectionEnd != null ? textarea.selectionEnd : textarea.value.length;
            textarea.value = textarea.value.slice(0, inicio) + texto + textarea.value.slice(fim);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = inicio + texto.length;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            return;
        }

        var editor = editorDoTextarea(textarea);
        var codable = editor ? editor.querySelector('.note-codable') : null;

        if (editor && editor.classList.contains('codeview') && codable) {
            var cInicio = codable.selectionStart != null ? codable.selectionStart : codable.value.length;
            var cFim = codable.selectionEnd != null ? codable.selectionEnd : codable.value.length;
            codable.value = codable.value.slice(0, cInicio) + texto + codable.value.slice(cFim);
            codable.focus();
            codable.selectionStart = codable.selectionEnd = cInicio + texto.length;
            sincronizarParaTextarea(textarea, true, false);
            return;
        }

        var $ta = window.jQuery(textarea);
        $ta.summernote('focus');
        $ta.summernote('insertText', texto);
        sincronizarParaTextarea(textarea, true, false);
    }

    // Sincroniza antes que golden-salvar.js ou submit nativo leia FormData(form).
    document.addEventListener('submit', function (evento) {
        if (evento.target instanceof HTMLFormElement) {
            sincronizarFormulario(evento.target);
        }
    }, true);

    // Compatibilidade com o botão "Incluir meu IP atual" de /admin/manutencao:
    // entrega texto puro ao handler da página e reescreve o editor visual após o clique.
    document.addEventListener('click', function (evento) {
        var alvo = evento.target instanceof Element ? evento.target : null;
        if (!alvo || !alvo.closest('[data-incluir-ip]')) {
            return;
        }
        var campoIps = document.getElementById('f-ips-permitidos');
        if (!campoIps || !jqueryDisponivel() || !window.jQuery(campoIps).data('summernote')) {
            return;
        }
        var htmlAtual = window.jQuery(campoIps).summernote('code');
        campoIps.value = textoPuroDoEditor(htmlAtual);
    }, true);

    document.addEventListener('click', function (evento) {
        var alvo = evento.target instanceof Element ? evento.target : null;
        if (!alvo || !alvo.closest('[data-incluir-ip]')) {
            return;
        }
        var campoIps = document.getElementById('f-ips-permitidos');
        if (campoIps && campoIps.dataset.summernoteAtivo === '1') {
            window.setTimeout(function () {
                sincronizarDoTextareaParaEditor(campoIps);
            }, 0);
        }
    }, false);

    // Eventos globais do painel e Bootstrap Tabs
    document.addEventListener('golden:trocou', function () {
        inicializarTodos(document);
    });

    document.addEventListener('shown.bs.tab', function (evento) {
        var seletor = evento.target && evento.target.getAttribute ? (evento.target.getAttribute('data-bs-target') || evento.target.getAttribute('href')) : null;
        var painel = seletor ? document.querySelector(seletor) : document;
        inicializarTodos(painel || document);
    });

    if (typeof window.jQuery !== 'undefined') {
        window.jQuery(document).on('golden:theme', aplicarTemaNosEditores);
        window.jQuery(function () {
            inicializarTodos(document);
        });
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            inicializarTodos(document);
        });
    }

    // Observa novos nós adicionados dinamicamente (Livewire, filtros ou parciais AJAX)
    var timerMutacao = null;
    if (typeof MutationObserver !== 'undefined') {
        var observador = new MutationObserver(function (mutacoes) {
            var precisaAtualizarTema = false;
            var temTextareaNovo = false;

            for (var i = 0; i < mutacoes.length; i++) {
                var m = mutacoes[i];

                if (m.type === 'attributes') {
                    if (m.target === document.documentElement || m.target === document.body) {
                        precisaAtualizarTema = true;
                    }
                    continue;
                }

                if (m.type !== 'childList' || !m.addedNodes || !m.addedNodes.length) {
                    continue;
                }

                for (var j = 0; j < m.addedNodes.length; j++) {
                    var no = m.addedNodes[j];
                    if (!(no instanceof Element)) {
                        continue;
                    }

                    if (no.matches('.note-editor, .note-frame, .note-modal, .note-popover, .note-dialog')
                        || no.closest('.note-editor, .note-frame, .note-modal, .note-popover, .note-dialog')) {
                        continue;
                    }

                    if (ehTextareaElegivel(no)) {
                        temTextareaNovo = true;
                        break;
                    }

                    var candidatos = no.querySelectorAll(SELETOR_ALVOS);
                    for (var k = 0; k < candidatos.length; k++) {
                        if (ehTextareaElegivel(candidatos[k])) {
                            temTextareaNovo = true;
                            break;
                        }
                    }

                    if (temTextareaNovo) {
                        break;
                    }
                }
            }

            if (precisaAtualizarTema) {
                aplicarTemaNosEditores();
            }

            if (!temTextareaNovo) {
                return;
            }

            clearTimeout(timerMutacao);
            timerMutacao = window.setTimeout(function () {
                inicializarTodos(document);
            }, 40);
        });

        observador.observe(document.documentElement, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['data-bs-theme', 'class']
        });
    }

    window.GoldenSummernote = {
        initAll: inicializarTodos,
        sync: function (textarea) { sincronizarParaTextarea(textarea, false, false); },
        syncForm: sincronizarFormulario,
        sincronizarDoTextarea: sincronizarDoTextareaParaEditor,
        inserirTexto: inserirTexto,
        aplicarTema: aplicarTemaNosEditores
    };
})();
