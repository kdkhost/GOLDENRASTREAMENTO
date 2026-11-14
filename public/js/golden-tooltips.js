(function () {
    'use strict';

    var tooltipEl = null;
    var activeTarget = null;

    function ensureEl() {
        if (!tooltipEl) {
            tooltipEl = document.createElement('div');
            tooltipEl.className = 'golden-tooltip';
            tooltipEl.setAttribute('role', 'tooltip');
            document.body.appendChild(tooltipEl);
        }
        return tooltipEl;
    }

    function converterTitulos(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var itens = scope.querySelectorAll('[title]:not(iframe):not(svg):not([data-native-title])');
        for (var i = 0; i < itens.length; i++) {
            var el = itens[i];
            if (el.closest('svg')) continue;
            var texto = (el.getAttribute('title') || '').trim();
            if (texto !== '') {
                el.setAttribute('data-tooltip', texto);
                if (!el.getAttribute('aria-label') && el.textContent.trim() === '') {
                    el.setAttribute('aria-label', texto);
                }
            }
            el.removeAttribute('title');
        }
        var bsItens = scope.querySelectorAll('[data-bs-title]:not([data-tooltip])');
        for (var j = 0; j < bsItens.length; j++) {
            var bsEl = bsItens[j];
            var txt = (bsEl.getAttribute('data-bs-title') || '').trim();
            if (txt !== '') {
                bsEl.setAttribute('data-tooltip', txt);
            }
        }
    }

    function posicionar(target, box) {
        var rect = target.getBoundingClientRect();
        var boxRect = box.getBoundingClientRect();
        var margin = 8;

        var top = rect.top - boxRect.height - margin;
        if (top < 8) {
            top = rect.bottom + margin;
        }

        var left = rect.left + (rect.width / 2) - (boxRect.width / 2);
        if (left < 8) left = 8;
        if (left + boxRect.width > window.innerWidth - 8) {
            left = window.innerWidth - boxRect.width - 8;
        }

        box.style.top = Math.round(top) + 'px';
        box.style.left = Math.round(left) + 'px';
    }

    function mostrar(target) {
        var texto = target.getAttribute('data-tooltip');
        if (!texto) return;

        activeTarget = target;
        var box = ensureEl();
        box.textContent = texto;
        box.classList.remove('is-visible');
        posicionar(target, box);
        requestAnimationFrame(function () {
            if (activeTarget === target) {
                posicionar(target, box);
                box.classList.add('is-visible');
            }
        });
    }

    function esconder() {
        activeTarget = null;
        if (tooltipEl) {
            tooltipEl.classList.remove('is-visible');
        }
    }

    function alvoComTooltip(node) {
        if (!node || !node.closest) return null;
        if (node.hasAttribute && node.hasAttribute('title') && !node.closest('svg') && node.tagName !== 'IFRAME') {
            converterTitulos(node.parentElement || document);
        }
        return node.closest('[data-tooltip]');
    }

    document.addEventListener('mouseover', function (e) {
        var target = alvoComTooltip(e.target);
        if (target && target !== activeTarget) {
            mostrar(target);
        }
    });

    document.addEventListener('mouseout', function (e) {
        if (!activeTarget) return;
        var related = e.relatedTarget;
        if (!related || !activeTarget.contains(related)) {
            esconder();
        }
    });

    document.addEventListener('focusin', function (e) {
        var target = alvoComTooltip(e.target);
        if (target) mostrar(target);
    });

    document.addEventListener('focusout', function () {
        esconder();
    });

    document.addEventListener('scroll', esconder, { passive: true });
    document.addEventListener('click', esconder);

    function init() {
        converterTitulos(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', init);

    if (typeof MutationObserver !== 'undefined') {
        var timer = null;
        var observer = new MutationObserver(function () {
            clearTimeout(timer);
            timer = setTimeout(init, 120);
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }
})();
