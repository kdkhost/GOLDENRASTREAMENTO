/**
 * Pesquisa e Filtros Assíncronos no Painel (Live Search com Debounce)
 * Atualiza tabelas sem recarregar a página e sem travar o banco de dados.
 */
(() => {
    let debounceTimer = null;
    let abortController = null;

    function initLiveSearch() {
        const filterForms = document.querySelectorAll('.filters-card form, form[data-async-search]');
        if (!filterForms.length) return;

        filterForms.forEach(form => {
            if (form.dataset.asyncBound) return;
            form.dataset.asyncBound = 'true';

            const buscaInput = form.querySelector('input[name="busca"], input[data-async-input]');
            const selects = form.querySelectorAll('select');
            const tableCard = document.querySelector('.card:has(.table-responsive), .table-responsive')?.closest('.card');

            if (!tableCard) return;

            // Busca por digitação com debounce de 350ms
            if (buscaInput) {
                buscaInput.addEventListener('input', () => {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        executarBusca(form, tableCard);
                    }, 350);
                });

                // Limpar ao apertar Escape
                buscaInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        buscaInput.value = '';
                        executarBusca(form, tableCard);
                    }
                });
            }

            // Filtros de seleção automática ao mudar select
            selects.forEach(select => {
                select.addEventListener('change', () => {
                    clearTimeout(debounceTimer);
                    executarBusca(form, tableCard);
                });
            });

            // Submit do form também assíncrono
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                clearTimeout(debounceTimer);
                executarBusca(form, tableCard);
            });
        });

        // Intercepta cliques de paginação para navegar assincronamente
        document.addEventListener('click', (e) => {
            const link = e.target.closest('.pagination a, .table-responsive ~ nav a');
            if (!link) return;

            const tableCard = link.closest('.card');
            if (!tableCard) return;

            e.preventDefault();
            carregarUrl(link.href, tableCard);
        });
    }

    function executarBusca(form, tableCard) {
        const formData = new FormData(form);
        const params = new URLSearchParams();

        for (const [key, value] of formData.entries()) {
            if (value !== '') {
                params.append(key, value);
            }
        }

        const baseUrl = form.getAttribute('action') || window.location.pathname;
        const targetUrl = baseUrl + (params.toString() ? '?' + params.toString() : '');

        carregarUrl(targetUrl, tableCard);
    }

    async function carregarUrl(targetUrl, tableCard) {
        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();

        tableCard.style.transition = 'opacity 0.2s ease';
        tableCard.style.opacity = '0.5';
        tableCard.style.pointerEvents = 'none';

        try {
            const res = await fetch(targetUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: abortController.signal
            });

            if (!res.ok) throw new Error('Erro na resposta');

            const html = await res.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            const newTable = doc.querySelector('.card:has(.table-responsive), .table-responsive')?.closest('.card');
            if (newTable) {
                tableCard.innerHTML = newTable.innerHTML;
            }

            // Atualiza histórico do navegador sem recarregar
            window.history.replaceState({}, '', targetUrl);

            // Atualiza contadores ou badges de status se houver
            const novoExtra = doc.querySelector('.filters-card .ms-auto');
            const atualExtra = document.querySelector('.filters-card .ms-auto');
            if (novoExtra && atualExtra) {
                atualExtra.innerHTML = novoExtra.innerHTML;
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.error('Falha na busca assíncrona:', err);
            }
        } finally {
            tableCard.style.opacity = '1';
            tableCard.style.pointerEvents = 'auto';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLiveSearch);
    } else {
        initLiveSearch();
    }
})();

