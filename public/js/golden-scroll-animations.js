/**
 * Golden Rastreamento — Animações e Exibição de Imagens/Vídeos na Rolagem (Cima e Baixo)
 * Executado via IntersectionObserver + requestAnimationFrame sem travar o navegador.
 */
(function () {
    'use strict';

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    var ultimoScrollY = window.pageYOffset || document.documentElement.scrollTop;
    var direcaoRolagem = 'down'; // 'down' ou 'up'
    var ticking = false;

    // Detecta a direção da rolagem com RAF para não sobrecarregar
    window.addEventListener('scroll', function () {
        if (!ticking) {
            window.requestAnimationFrame(function () {
                var scrollAtual = window.pageYOffset || document.documentElement.scrollTop;
                if (scrollAtual > ultimoScrollY) {
                    direcaoRolagem = 'down';
                } else if (scrollAtual < ultimoScrollY) {
                    direcaoRolagem = 'up';
                }
                ultimoScrollY = Math.max(0, scrollAtual);
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    function iniciarObservador() {
        if (!('IntersectionObserver' in window)) {
            // Fallback para navegadores legados: exibe tudo diretamente
            var elementos = document.querySelectorAll('[data-scroll-reveal], img, video');
            for (var i = 0; i < elementos.length; i++) {
                elementos[i].classList.add('is-revealed');
            }
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                var el = entry.target;

                if (entry.isIntersecting) {
                    // Elemento entrou no campo de visão
                    if (direcaoRolagem === 'up') {
                        el.classList.add('from-top');
                    } else {
                        el.classList.remove('from-top');
                    }

                    // Força decodificação assíncrona em imagens
                    if (el.tagName === 'IMG' && !el.hasAttribute('decoding')) {
                        el.setAttribute('decoding', 'async');
                    }

                    // Se for vídeo HTML5, retoma a reprodução suave se aplicável
                    if (el.tagName === 'VIDEO' && el.hasAttribute('data-auto-resume') && el.paused) {
                        try {
                            el.play().catch(function () {});
                        } catch (e) {}
                    }

                    window.requestAnimationFrame(function () {
                        el.classList.add('is-revealed');
                    });
                } else {
                    // Elemento saiu do campo de visão pela rolagem
                    // Se estiver bem distante (fora da tela), permite re-animar suavemente ao retornar
                    var rect = entry.boundingClientRect;
                    var distY = rect.top;

                    // Pausa vídeo HTML5 fora da tela para poupar CPU/GPU
                    if (el.tagName === 'VIDEO' && !el.paused) {
                        el.setAttribute('data-auto-resume', '1');
                        try {
                            el.pause();
                        } catch (e) {}
                    }

                    // Se o elemento saiu por cima ou por baixo, prepara o estado para o retorno suave
                    if (distY > window.innerHeight + 150) {
                        el.classList.remove('is-revealed');
                        el.classList.remove('from-top');
                    } else if (distY < -150) {
                        el.classList.remove('is-revealed');
                        el.classList.add('from-top');
                    }
                }
            });
        }, {
            root: null,
            rootMargin: '60px 0px 60px 0px',
            threshold: [0, 0.12]
        });

        function anexarElementos() {
            // Seleciona todas as imagens de conteúdo, blocos e vídeos
            var alvos = document.querySelectorAll(
                'img:not(.brand-image):not([data-no-reveal]),' +
                'video,' +
                '.foto,' +
                '.video-frame-container,' +
                '.video-slot,' +
                '.f-bloco,' +
                '[data-scroll-reveal]'
            );

            alvos.forEach(function (el) {
                if (!el.hasAttribute('data-scroll-reveal-ativo')) {
                    el.setAttribute('data-scroll-reveal-ativo', 'true');
                    el.setAttribute('data-scroll-reveal', 'true');

                    if (el.tagName === 'IMG') {
                        el.setAttribute('decoding', 'async');
                        // Se for a primeira imagem da dobra superior (hero), revela imediatamente
                        var topo = el.getBoundingClientRect().top;
                        if (topo < window.innerHeight && topo >= 0) {
                            el.classList.add('is-revealed');
                        }
                    }

                    observer.observe(el);
                }
            });
        }

        anexarElementos();

        // Suporte para conteúdo injetado dinamicamente (Livewire, carrosséis, tabs)
        var mutacaoObserver = new MutationObserver(function () {
            anexarElementos();
        });

        mutacaoObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciarObservador);
    } else {
        iniciarObservador();
    }
})();
