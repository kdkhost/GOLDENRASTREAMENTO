@props([
    'name',
    'label',
    'value' => null,
    'col' => 'col-12',
    'required' => false,
    'opcional' => false,
    'hint' => null,
    'ajuda' => null,
])
@php
    $id = 'icon-picker-'.preg_replace('/[^a-z0-9]+/i', '-', $name);
    $modalId = 'modal-'.$id;
    $chave = rtrim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $atual = old($chave, $value ?: 'fa-triangle-exclamation');
    $catalogo = \App\Support\Icones::catalogo();
    $nomeAtual = \App\Support\Icones::nomeAmigavel($atual);
    $classeAtual = \App\Support\Icones::classeCompleta($atual);
    $gaveta = \App\Support\GuiaConfiguracao::gaveta($ajuda ?? $name, $label);
@endphp

<div class="{{ $col }} mb-3">
    <div class="{{ $gaveta ? 'ajuda-rotulo' : '' }}">
        <label class="form-label fw-bold d-flex align-items-center gap-1" for="{{ $id }}">
            {{ $label }}
            @if($opcional)<span class="form-hint fw-normal">(opcional)</span>@endif
            @if($required)<span class="text-danger">*</span>@endif
        </label>
        @if($gaveta)<x-admin.ajuda :gaveta="$gaveta" />@endif
        @if($hint)<span class="form-hint d-block mb-1">{{ $hint }}</span>@endif
    </div>

    {{-- Campo oculto que armazena e envia o código selecionado no formulário --}}
    <input type="hidden" name="{{ $name }}" id="{{ $id }}" value="{{ $atual }}" @if($required) required @endif>

    {{-- Cartão Visual Interativo de Exibição e Abertura do Seletor --}}
    <div class="icon-picker-box d-flex align-items-center justify-content-between p-3 rounded-3" id="box-{{ $id }}">
        <div class="d-flex align-items-center gap-3">
            <div class="icon-picker-preview shadow-sm" id="prev-box-{{ $id }}">
                <i class="{{ $classeAtual }}" id="prev-icon-{{ $id }}"></i>
            </div>
            <div>
                <strong class="d-block text-body fs-6" id="prev-name-{{ $id }}">{{ $nomeAtual }}</strong>
                <span class="badge text-bg-secondary bg-opacity-25 text-body small mt-1 font-monospace" id="prev-code-{{ $id }}">
                    {{ $atual }}
                </span>
            </div>
        </div>

        <button type="button" class="btn btn-outline-warning text-gold border-gold d-inline-flex align-items-center gap-2 fw-semibold px-3 py-2 shadow-sm"
            data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
            <i class="fa-solid fa-icons"></i>
            <span>Escolher Ícone</span>
        </button>
    </div>

    @error($chave)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

    {{-- Modal Seletor Visual com Grade e Categorias --}}
    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-secondary shadow-lg">
                <div class="modal-header border-bottom border-secondary border-opacity-25 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-2 rounded-2 bg-warning bg-opacity-10 text-gold fs-5">
                            <i class="fa-solid fa-icons"></i>
                        </span>
                        <div>
                            <h5 class="modal-title fw-bold mb-0 text-body" id="{{ $modalId }}-title">Selecione o Ícone Visual</h5>
                            <small class="text-secondary">Clique no ícone desejado para selecioná-lo imediatamente</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-3">
                    {{-- Barra de Pesquisa e Filtro Rápido --}}
                    <div class="input-group mb-3 shadow-sm">
                        <span class="input-group-text bg-body-tertiary border-secondary border-opacity-25 text-secondary">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" class="form-control border-secondary border-opacity-25" id="search-{{ $id }}"
                            placeholder="Buscar por palavra-chave... (ex: alerta, carro, servidor, chave, suporte, pix)" autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary" id="clear-search-{{ $id }}" style="display:none">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    {{-- Abas de Categoria --}}
                    <div class="d-flex gap-1 flex-wrap mb-3 border-bottom pb-2" id="tabs-{{ $id }}">
                        <button type="button" class="btn btn-sm btn-gold active" data-cat="todas">
                            <i class="fa-solid fa-border-all me-1"></i> Todos os Ícones
                        </button>
                        @foreach($catalogo as $catSlug => $cat)
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-cat="{{ $catSlug }}">
                            <i class="fa-solid {{ $cat['icone'] }} me-1"></i> {{ $cat['nome'] }}
                        </button>
                        @endforeach
                    </div>

                    {{-- Grade Visual de Ícones --}}
                    <div class="icon-grid-container p-1" id="grid-{{ $id }}" style="max-height: 420px; overflow-y: auto;">
                        <div class="row row-cols-3 row-cols-sm-4 row-cols-md-6 g-2" id="items-{{ $id }}">
                            @foreach($catalogo as $catSlug => $cat)
                                @foreach($cat['itens'] as $item)
                                    @php
                                        $fullCls = \App\Support\Icones::classeCompleta($item['codigo']);
                                        $isSelected = ($atual === $item['codigo']);
                                    @endphp
                                    <div class="col icon-item-col" data-cat="{{ $catSlug }}" data-codigo="{{ $item['codigo'] }}" data-tags="{{ strtolower($item['nome'] . ' ' . $item['tags'] . ' ' . $item['codigo']) }}">
                                        <button type="button" class="icon-card-btn w-100 p-2 text-center rounded-3 border {{ $isSelected ? 'is-selected' : '' }}"
                                            data-code="{{ $item['codigo'] }}"
                                            data-name="{{ $item['nome'] }}"
                                            data-class="{{ $fullCls }}"
                                            title="{{ $item['nome'] }} ({{ $item['codigo'] }})">
                                            <div class="icon-symbol mb-1">
                                                <i class="{{ $fullCls }}"></i>
                                            </div>
                                            <span class="icon-name d-block text-truncate small">{{ $item['nome'] }}</span>
                                        </button>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>

                        {{-- Mensagem quando nenhum ícone for encontrado na busca --}}
                        <div id="empty-{{ $id }}" class="text-center py-5 text-secondary" style="display:none">
                            <i class="fa-solid fa-face-frown fs-1 d-block mb-2 text-gold opacity-50"></i>
                            <p class="mb-0 fw-semibold">Nenhum ícone encontrado para o termo pesquisado.</p>
                            <small>Tente buscar por sinônimos ou selecione outra categoria acima.</small>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top border-secondary border-opacity-25 py-2 d-flex justify-content-between align-items-center">
                    <span class="small text-secondary" id="count-{{ $id }}">
                        Clique em qualquer ícone acima para aplicar.
                    </span>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.icon-picker-box {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.12);
    transition: all 0.2s ease;
}
.icon-picker-box:hover {
    border-color: rgba(245, 179, 1, 0.4);
    background: rgba(245, 179, 1, 0.02);
}
.icon-picker-preview {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: rgba(245, 179, 1, 0.12);
    border: 1.5px solid rgba(245, 179, 1, 0.4);
    color: #f5b301;
    display: grid;
    place-items: center;
    font-size: 1.7rem;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}
.icon-picker-box:hover .icon-picker-preview {
    transform: scale(1.06);
    box-shadow: 0 0 15px rgba(245, 179, 1, 0.25);
}
.icon-card-btn {
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.08) !important;
    color: inherit;
    transition: all 0.18s ease;
    cursor: pointer;
    min-height: 78px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.icon-card-btn:hover {
    background: rgba(245, 179, 1, 0.14);
    border-color: #f5b301 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 179, 1, 0.2);
}
.icon-card-btn:hover .icon-symbol i {
    color: #f5b301;
    transform: scale(1.15);
}
.icon-card-btn.is-selected {
    background: rgba(245, 179, 1, 0.18);
    border-color: #f5b301 !important;
    box-shadow: 0 0 0 2px rgba(245, 179, 1, 0.35);
}
.icon-card-btn.is-selected .icon-symbol i {
    color: #f5b301;
}
.icon-symbol {
    font-size: 1.65rem;
    line-height: 1;
    transition: transform 0.18s ease;
}
.icon-name {
    font-size: 0.72rem;
    line-height: 1.2;
    color: var(--bs-secondary-color, #94a3b8);
    width: 100%;
}
.icon-card-btn:hover .icon-name,
.icon-card-btn.is-selected .icon-name {
    color: var(--bs-body-color, #ffffff);
    font-weight: 600;
}
</style>

<script>
(function() {
    var pickerId = '{{ $id }}';
    var modalId = '{{ $modalId }}';

    function initPicker() {
        var inputHidden = document.getElementById(pickerId);
        var searchInput = document.getElementById('search-' + pickerId);
        var clearBtn = document.getElementById('clear-search-' + pickerId);
        var modalEl = document.getElementById(modalId);
        var tabsContainer = document.getElementById('tabs-' + pickerId);
        var itemsContainer = document.getElementById('items-' + pickerId);
        var emptyMsg = document.getElementById('empty-' + pickerId);
        var prevIcon = document.getElementById('prev-icon-' + pickerId);
        var prevName = document.getElementById('prev-name-' + pickerId);
        var prevCode = document.getElementById('prev-code-' + pickerId);

        if (!inputHidden || !modalEl) return;

        var currentCat = 'todas';

        function filtrar() {
            var termo = (searchInput.value || '').trim().toLowerCase();
            if (clearBtn) clearBtn.style.display = termo ? 'block' : 'none';

            var cols = itemsContainer.querySelectorAll('.icon-item-col');
            var visiveis = 0;

            cols.forEach(function(col) {
                var catMatch = (currentCat === 'todas') || (col.dataset.cat === currentCat);
                var tags = col.dataset.tags || '';
                var textMatch = (!termo) || (tags.indexOf(termo) !== -1);

                if (catMatch && textMatch) {
                    col.style.display = '';
                    visiveis++;
                } else {
                    col.style.display = 'none';
                }
            });

            if (emptyMsg) {
                emptyMsg.style.display = (visiveis === 0) ? 'block' : 'none';
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', filtrar);
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                filtrar();
                searchInput.focus();
            });
        }

        if (tabsContainer) {
            tabsContainer.querySelectorAll('button[data-cat]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    tabsContainer.querySelectorAll('button[data-cat]').forEach(function(b) {
                        b.classList.remove('active', 'btn-gold');
                        b.classList.add('btn-outline-secondary');
                    });
                    this.classList.remove('btn-outline-secondary');
                    this.classList.add('active', 'btn-gold');
                    currentCat = this.dataset.cat;
                    filtrar();
                });
            });
        }

        // Clique em um ícone
        itemsContainer.querySelectorAll('.icon-card-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var code = this.dataset.code;
                var name = this.dataset.name;
                var cls = this.dataset.class;

                // Atualiza input
                inputHidden.value = code;

                // Atualiza preview visual
                if (prevIcon) prevIcon.className = cls;
                if (prevName) prevName.textContent = name;
                if (prevCode) prevCode.textContent = code;

                // Marca ativo no modal
                itemsContainer.querySelectorAll('.icon-card-btn').forEach(function(b) {
                    b.classList.remove('is-selected');
                });
                this.classList.add('is-selected');

                // Fecha modal
                var modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) {
                    modalInstance.hide();
                }

                // Dispara evento change nativo
                inputHidden.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPicker);
    } else {
        initPicker();
    }
})();
</script>

