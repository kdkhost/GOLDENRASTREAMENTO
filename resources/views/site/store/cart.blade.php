@extends('layouts.site')

@section('title', 'Seu carrinho — Loja Golden')
@section('meta_description', 'Revise os equipamentos e chips antes de finalizar o pedido na loja Golden.')

@section('content')
<section class="sec-light">
    <div class="wrap">
        <span class="eyebrow">Carrinho</span>
        <h1 id="cartHeadingCount" style="font-size:1.9rem;margin:.2rem 0">{{ count($linhas) }} {{ count($linhas) === 1 ? 'item' : 'itens' }} na sua sacola.</h1>
        <p class="lead-sub">Ajuste as quantidades quando quiser — nada é cobrado antes de você escolher a entrega e o pagamento.</p>

        <div id="cartEmptyState" class="cart-empty" @if($linhas !== []) hidden @endif>
            <i class="fa-solid fa-basket-shopping"></i>
            <p>Seu carrinho está vazio.</p>
            <a href="{{ route('site.loja.index') }}" class="btn btn-gold">Ver a loja</a>
        </div>

        @if($linhas !== [])
        <div id="cartContentWrap">
        <form method="POST" action="{{ route('site.loja.carrinho.atualizar') }}" class="cart-form" id="cartForm">
            @csrf
            <ul class="cart-list">
                @foreach($linhas as $l)
                <li class="cart-line" data-cart-line="{{ $l['id'] }}">
                    <a class="cart-thumb" href="{{ route('site.loja.produto', $l['slug']) }}">
                        @if($l['imagem'])<img src="{{ produto_foto_url($l['imagem']) }}" alt="{{ $l['nome'] }}">@else<i class="fa-solid fa-satellite-dish"></i>@endif
                    </a>
                    <div class="cart-info">
                        <a href="{{ route('site.loja.produto', $l['slug']) }}"><b>{{ $l['nome'] }}</b></a>
                        <small>{{ $l['categoria'] }}@if($l['sku']) · {{ $l['sku'] }}@endif</small>
                        <span class="cart-unit">{{ brl($l['preco']) }} cada</span>
                    </div>
                    <div class="qty">
                        <button type="button" data-cart-action="dec" data-product-id="{{ $l['id'] }}" aria-label="Diminuir quantidade de {{ $l['nome'] }}">−</button>
                        <input type="number" name="quantidades[{{ $l['id'] }}]" data-cart-qty="{{ $l['id'] }}" min="0" max="{{ $l['controle_estoque'] ? $l['estoque'] : 999 }}" value="{{ $l['quantidade'] }}" aria-label="Quantidade de {{ $l['nome'] }}">
                        <button type="button" data-cart-action="inc" data-product-id="{{ $l['id'] }}" aria-label="Aumentar quantidade de {{ $l['nome'] }}">+</button>
                    </div>
                    <b class="cart-total" data-cart-line-total="{{ $l['id'] }}">{{ brl($l['subtotal']) }}</b>
                    <button class="cart-del" type="button" data-cart-action="remove" data-product-id="{{ $l['id'] }}" aria-label="Remover {{ $l['nome'] }}"><i class="fa-solid fa-trash-can"></i></button>
                </li>
                @endforeach
            </ul>

            <div class="cart-foot">
                <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate"></i> Atualizar quantidades</button>
                <a href="{{ route('site.loja.index') }}" class="btn btn-ghost btn-sm">Continuar comprando</a>
            </div>
        </form>

        <aside class="cart-summary" data-total-sem-frete="{{ max(0, $subtotal - ($descontoCents ?? 0)) }}">
            <h2>Resumo</h2>
            <div class="sum-line"><span id="cartSummaryUnitsLabel">Subtotal ({{ array_sum(array_column($linhas, 'quantidade')) }} itens)</span><b id="cartSubtotalDisplay">{{ brl($subtotal) }}</b></div>
            <div class="sum-line"><span>Frete</span><b id="cartFreteDisplay">Calcule pelo CEP</b></div>
            <div class="sum-line" id="cartCupomSummaryRow" style="color:var(--teal)" @if(empty($cupom)) hidden @endif>
                <span id="cartCupomSummaryLabel">@if(!empty($cupom))Cupom <b>{{ $cupom->codigo }}</b> ({{ $cupom->rotuloDesconto() }})@endif</span>
                <b id="cartCupomSummaryVal">@if(!empty($cupom)){{ ($descontoCents ?? 0) > 0 ? '− '.brl($descontoCents) : 'Frete grátis' }}@endif</b>
            </div>
            <div class="sum-line" style="font-size:1.06rem;border-bottom:0;padding-top:10px">
                <strong>Total estimado</strong>
                <strong id="cartTotalDisplay" style="color:var(--gold-2)">{{ brl(max(0, $subtotal - ($descontoCents ?? 0))) }}</strong>
            </div>
            <p class="sum-hint" id="cartFreteGratisHint" @if($freteGratisEm <= 0) hidden @endif>
                <i class="fa-solid fa-gift" style="color:var(--gold)"></i>
                <span>Faltam <span id="cartFreteGratisVal">{{ brl($freteGratisEm) }}</span> para o frete grátis.</span>
            </p>

            <div class="cart-cupom-box" style="margin:14px 0 0;padding:12px;background:#f8fafc;border-radius:12px;border:1px solid rgba(0,0,0,.08)">
                <label for="cartCupomCodigo" style="display:block;font-size:.84rem;font-weight:600;margin-bottom:6px;color:var(--ink)">
                    <i class="fa-solid fa-ticket" style="color:var(--gold);margin-right:4px"></i> Cupom de desconto
                </label>
                <div id="cartCupomFeedback" style="font-size:.8rem;margin-bottom:6px;display:none"></div>
                <div id="cartCupomAppliedBox" @if(empty($cupom)) style="display:none" @else style="display:flex;align-items:center;justify-content:space-between;gap:8px;background:#fff;border:1px solid rgba(31,158,134,.35);border-radius:8px;padding:7px 10px" @endif>
                    <span style="font-size:.85rem;color:var(--teal);font-weight:600"><i class="fa-solid fa-circle-check"></i> <span id="cartCupomAppliedCode">{{ $cupom->codigo ?? '' }}</span> aplicado</span>
                    <form method="POST" action="{{ route('site.loja.cupom.remover') }}" id="formCartCupomRemover" style="margin:0">
                        @csrf
                        <button type="submit" style="border:0;background:none;color:#d06a5f;font-size:.8rem;font-weight:600;cursor:pointer">Remover</button>
                    </form>
                </div>
                <form method="POST" action="{{ route('site.loja.cupom.aplicar') }}" id="formCartCupomAplicar" @if(!empty($cupom)) style="display:none" @else style="display:flex;gap:6px" @endif>
                    @csrf
                    <input type="text" id="cartCupomCodigo" name="codigo" placeholder="Ex.: GOLDEN10" maxlength="40" style="flex:1;padding:6px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:.88rem;text-transform:uppercase" required>
                    <button type="submit" class="btn btn-ghost btn-sm" style="padding:6px 12px;white-space:nowrap">Aplicar</button>
                </form>
            </div>

            <div class="cart-frete-box" style="margin:16px 0;padding:12px;background:#f8fafc;border-radius:12px;border:1px solid rgba(0,0,0,.08)">
                <label for="cartCep" style="display:block;font-size:.84rem;font-weight:600;margin-bottom:6px;color:var(--ink)">
                    <i class="fa-solid fa-truck" style="color:var(--gold);margin-right:4px"></i> Simular frete Correios
                </label>
                <div style="display:flex;gap:6px">
                    <input type="text" id="cartCep" placeholder="00000-000" maxlength="9" style="flex:1;padding:6px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:.88rem">
                    <button type="button" id="btnCalcCartFrete" class="btn btn-ghost btn-sm" style="padding:6px 12px;white-space:nowrap">Calcular</button>
                </div>
                <div id="cartFreteResultados" style="margin-top:10px;font-size:.82rem;display:none"></div>
            </div>

            <a href="{{ route('site.loja.checkout') }}" class="btn btn-gold" data-farol="checkout" style="width:100%;margin-top:14px">Finalizar pedido →</a>
            <p class="fine">Pagamento na etapa seguinte: Pix, cartão ou boleto. Retirada na loja quando disponível.</p>
        </aside>
        </div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<style>
.cart-list{list-style:none;margin:22px 0 0;padding:0}
.cart-line{display:grid;grid-template-columns:72px 1fr auto auto auto;gap:16px;align-items:center;padding:14px 0;border-bottom:1px solid rgba(0,0,0,.08)}
.cart-thumb{width:72px;height:72px;border-radius:12px;background:linear-gradient(160deg,#1b2127,#0d1114);display:grid;place-items:center;overflow:hidden}
.cart-thumb img{width:100%;height:100%;object-fit:contain;padding:4px}
.cart-thumb i{color:var(--gold);font-size:1.5rem}
.cart-info{display:flex;flex-direction:column;gap:2px;min-width:0}
.cart-info a{color:var(--ink);text-decoration:none;font-size:1rem}
.cart-info small{color:var(--muted);font-size:.78rem}
.cart-unit{font-size:.82rem;color:var(--muted)}
.qty{display:flex;align-items:center;border:1px solid rgba(0,0,0,.15);border-radius:10px;overflow:hidden}
.qty button{width:34px;height:36px;border:0;background:transparent;font-size:1.1rem;cursor:pointer;color:var(--ink)}
.qty input{width:52px;text-align:center;border:0;font-size:.95rem}
.cart-total{color:var(--gold);font-size:1.05rem;white-space:nowrap}
.cart-del{border:0;background:none;color:var(--muted);cursor:pointer;font-size:1rem;padding:6px}
.cart-del:hover{color:#d06a5f}
.cart-foot{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.cart-summary{margin-top:26px;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:16px;padding:18px 20px;max-width:420px;margin-left:auto}
.cart-summary h2{font-size:1.1rem;margin:0 0 10px}
.sum-line{display:flex;justify-content:space-between;padding:7px 0;font-size:.95rem;border-bottom:1px dashed rgba(0,0,0,.08)}
.sum-line b{color:var(--ink)}
.sum-hint{font-size:.82rem;color:var(--muted);margin:10px 0 0}
.cart-empty{text-align:center;padding:44px 0;color:var(--muted)}
.cart-empty i{font-size:2.6rem;color:var(--gold);opacity:.6;display:block;margin-bottom:10px}
@media(max-width:720px){
  .cart-line{grid-template-columns:56px 1fr auto;gap:10px}
  .cart-thumb{width:56px;height:56px}
  .cart-total{grid-column:2;text-align:right}
  .cart-del{grid-column:3;grid-row:2}
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputCep = document.getElementById('cartCep');
    const btnCalc = document.getElementById('btnCalcCartFrete');
    const boxResultados = document.getElementById('cartFreteResultados');

    if (!inputCep || !btnCalc || !boxResultados) return;

    let ultimoCepCart = '';

    const summaryAside = document.querySelector('.cart-summary');
    let totalSemFreteCents = summaryAside ? (parseInt(summaryAside.getAttribute('data-total-sem-frete') || '0', 10) || 0) : 0;
    let freteCalculadoCents = null;
    let freteCalculadoTexto = 'Calcule pelo CEP';
    let freteCodigoSelecionado = null;

    function formatBrlCents(cents) {
        return 'R$ ' + ((cents || 0) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function atualizarResumoComFrete() {
        const freteEl = document.getElementById('cartFreteDisplay');
        const totalEl = document.getElementById('cartTotalDisplay');
        if (freteEl) {
            freteEl.textContent = freteCalculadoCents === null ? 'Calcule pelo CEP' : freteCalculadoTexto;
        }
        if (totalEl) {
            const soma = totalSemFreteCents + (freteCalculadoCents || 0);
            totalEl.textContent = formatBrlCents(soma);
        }
    }

    window.goldenRecalcularFreteCart = function () {
        if (ultimoCepCart && ultimoCepCart.length === 8) {
            consultarFreteCart();
        }
    };

    function consultarFreteCart() {
        const rawCep = inputCep.value.replace(/\D/g, '');
        if (rawCep.length !== 8) {
            boxResultados.style.display = 'block';
            boxResultados.innerHTML = '<span style="color:#dc2626">Informe um CEP válido com 8 dígitos.</span>';
            freteCalculadoCents = null;
            freteCalculadoTexto = 'Calcule pelo CEP';
            atualizarResumoComFrete();
            return;
        }

        btnCalc.disabled = true;
        btnCalc.textContent = 'Calculando...';
        boxResultados.style.display = 'block';
        boxResultados.innerHTML = '<span style="color:#64748b"><i class="fa-solid fa-spinner fa-spin"></i> Consultando CEP e Correios...</span>';

        fetch("{{ route('site.loja.frete.calcular') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ cep: rawCep })
        })
        .then(res => res.json())
        .then(data => {
            btnCalc.disabled = false;
            btnCalc.textContent = 'Calcular';

            if (!data.sucesso || !data.opcoes || data.opcoes.length === 0) {
                boxResultados.innerHTML = '<span style="color:#dc2626">Não foi possível calcular o frete para este CEP.</span>';
                freteCalculadoCents = null;
                freteCalculadoTexto = 'Calcule pelo CEP';
                atualizarResumoComFrete();
                return;
            }

            let html = '';
            if (data.endereco_cep && data.endereco_cep.resumo) {
                html += '<div style="margin-bottom:8px;padding:6px 8px;border-radius:6px;background:rgba(15,118,110,.08);color:var(--teal);font-weight:600">';
                html += '<i class="fa-solid fa-location-dot" style="margin-right:4px"></i> ' + data.endereco_cep.resumo;
                html += '</div>';
            }

            let opSelecionada = data.opcoes.find(op => String(op.codigo || op.id) === String(freteCodigoSelecionado)) || data.opcoes[0];
            freteCodigoSelecionado = String(opSelecionada.codigo || opSelecionada.id);
            freteCalculadoCents = parseInt(opSelecionada.valor_cents || 0, 10) || 0;
            freteCalculadoTexto = freteCalculadoCents === 0 ? 'Grátis' : opSelecionada.valor_formatado;
            atualizarResumoComFrete();

            html += '<div style="display:flex;flex-direction:column;gap:6px;margin-top:4px">';
            data.opcoes.forEach(op => {
                const code = String(op.codigo || op.id);
                const checked = code === freteCodigoSelecionado ? 'checked' : '';
                const prazo = op.prazo_dias ? (op.prazo_dias + ' ' + (op.prazo_dias > 1 ? 'dias úteis' : 'dia útil')) : (op.prazo_texto || '');
                html += '<label style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:7px 9px;background:#fff;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer">';
                html += '<span style="display:flex;align-items:center;gap:8px"><input type="radio" name="cart_frete_opcao" value="' + code + '" data-cents="' + (parseInt(op.valor_cents || 0, 10) || 0) + '" data-texto="' + (op.valor_formatado || 'Grátis') + '" ' + checked + '><span><strong>' + op.nome + '</strong>' + (prazo ? '<br><small style="color:#64748b">' + prazo + '</small>' : '') + '</span></span>';
                html += '<strong style="color:var(--gold);white-space:nowrap">' + op.valor_formatado + '</strong>';
                html += '</label>';
            });
            html += '</div>';
            boxResultados.innerHTML = html;
        })
        .catch(() => {
            btnCalc.disabled = false;
            btnCalc.textContent = 'Calcular';
            boxResultados.innerHTML = '<span style="color:#dc2626">Erro ao conectar ao serviço de frete.</span>';
        });
    }

    boxResultados.addEventListener('change', function (e) {
        const radio = e.target.closest('input[name="cart_frete_opcao"]');
        if (!radio) return;
        freteCodigoSelecionado = radio.value;
        freteCalculadoCents = parseInt(radio.getAttribute('data-cents') || '0', 10) || 0;
        freteCalculadoTexto = freteCalculadoCents === 0 ? 'Grátis' : (radio.getAttribute('data-texto') || formatBrlCents(freteCalculadoCents));
        atualizarResumoComFrete();
    });

    inputCep.addEventListener('input', function () {
        let v = inputCep.value.replace(/\D/g, '').slice(0, 8);
        inputCep.value = v.replace(/^(\d{5})(\d)/, '$1-$2');
        if (v.length === 8 && v !== ultimoCepCart) {
            ultimoCepCart = v;
            consultarFreteCart();
        } else if (v.length < 8) {
            ultimoCepCart = '';
            freteCalculadoCents = null;
            freteCalculadoTexto = 'Calcule pelo CEP';
            boxResultados.style.display = 'none';
            boxResultados.innerHTML = '';
            atualizarResumoComFrete();
        }
    });

    btnCalc.addEventListener('click', function () {
        ultimoCepCart = inputCep.value.replace(/\D/g, '');
        consultarFreteCart();
    });

    window.goldenSetTotalSemFreteCents = function (cents) {
        totalSemFreteCents = parseInt(cents || 0, 10) || 0;
        atualizarResumoComFrete();
    };
});

document.addEventListener('DOMContentLoaded', function () {
    const cartForm = document.getElementById('cartForm');
    if (!cartForm) return;

    const csrfToken = '{{ csrf_token() }}';
    const wrap = document.getElementById('cartContentWrap');
    const emptyState = document.getElementById('cartEmptyState');
    const headingCount = document.getElementById('cartHeadingCount');
    const unitsLabel = document.getElementById('cartSummaryUnitsLabel');
    const subtotalEl = document.getElementById('cartSubtotalDisplay');
    const freteEl = document.getElementById('cartFreteDisplay');
    const totalEl = document.getElementById('cartTotalDisplay');
    const freteGratisHint = document.getElementById('cartFreteGratisHint');
    const freteGratisVal = document.getElementById('cartFreteGratisVal');

    const cupomSummaryRow = document.getElementById('cartCupomSummaryRow');
    const cupomSummaryLabel = document.getElementById('cartCupomSummaryLabel');
    const cupomSummaryVal = document.getElementById('cartCupomSummaryVal');
    const cupomAppliedBox = document.getElementById('cartCupomAppliedBox');
    const cupomAppliedCode = document.getElementById('cartCupomAppliedCode');
    const formCupomAplicar = document.getElementById('formCartCupomAplicar');
    const formCupomRemover = document.getElementById('formCartCupomRemover');
    const cupomFeedback = document.getElementById('cartCupomFeedback');

    function atualizarBadgesCabecalho(totalUnidades) {
        document.querySelectorAll('[data-cart-count]').forEach(function (badge) {
            badge.textContent = String(totalUnidades);
            if (totalUnidades > 0) {
                badge.removeAttribute('hidden');
            } else {
                badge.setAttribute('hidden', '');
            }
        });
    }

    function aplicarEstadoCarrinho(data) {
        if (!data || !data.sucesso) return;

        atualizarBadgesCabecalho(data.total_unidades || 0);

        if (headingCount) {
            const n = data.itens_count || 0;
            headingCount.textContent = n + ' ' + (n === 1 ? 'item' : 'itens') + ' na sua sacola.';
        }

        if (data.vazio) {
            if (wrap) wrap.style.display = 'none';
            if (emptyState) emptyState.removeAttribute('hidden');
            return;
        }

        const linhasMap = new Map((data.linhas || []).map(l => [String(l.id), l]));

        cartForm.querySelectorAll('[data-cart-line]').forEach(function (li) {
            const id = li.getAttribute('data-cart-line');
            const linha = linhasMap.get(id);
            if (!linha) {
                li.remove();
                return;
            }
            const qtyInput = li.querySelector('[data-cart-qty]');
            const lineTotal = li.querySelector('[data-cart-line-total]');
            if (qtyInput) qtyInput.value = linha.quantidade;
            if (lineTotal) lineTotal.textContent = linha.subtotal_formatado;
        });

        if (unitsLabel) {
            const u = data.total_unidades || 0;
            unitsLabel.textContent = 'Subtotal (' + u + ' ' + (u === 1 ? 'item' : 'itens') + ')';
        }
        if (subtotalEl) subtotalEl.textContent = data.subtotal_formatado;
        if (typeof window.goldenSetTotalSemFreteCents === 'function' && typeof data.total_sem_frete_cents !== 'undefined') {
            window.goldenSetTotalSemFreteCents(data.total_sem_frete_cents);
        } else {
            if (freteEl) freteEl.textContent = data.frete_formatado;
            if (totalEl) totalEl.textContent = data.total_formatado;
        }
        if (typeof window.goldenRecalcularFreteCart === 'function') {
            window.goldenRecalcularFreteCart();
        }

        if (freteGratisHint && freteGratisVal) {
            if (data.frete_gratis_faltam_formatado) {
                freteGratisVal.textContent = data.frete_gratis_faltam_formatado;
                freteGratisHint.removeAttribute('hidden');
            } else {
                freteGratisHint.setAttribute('hidden', '');
            }
        }

        if (data.cupom) {
            if (cupomSummaryLabel) cupomSummaryLabel.innerHTML = 'Cupom <b>' + data.cupom.codigo + '</b> (' + data.cupom.rotulo + ')';
            if (cupomSummaryVal) cupomSummaryVal.textContent = data.cupom.desconto_formatado;
            if (cupomSummaryRow) cupomSummaryRow.removeAttribute('hidden');
            if (cupomAppliedCode) cupomAppliedCode.textContent = data.cupom.codigo;
            if (cupomAppliedBox) cupomAppliedBox.style.display = 'flex';
            if (formCupomAplicar) formCupomAplicar.style.display = 'none';
        } else {
            if (cupomSummaryRow) cupomSummaryRow.setAttribute('hidden', '');
            if (cupomAppliedBox) cupomAppliedBox.style.display = 'none';
            if (formCupomAplicar) formCupomAplicar.style.display = 'flex';
        }
    }

    let pendingTimer = null;
    let requestSeq = 0;

    function enviarQuantidades() {
        const mySeq = ++requestSeq;
        const quantidades = {};
        cartForm.querySelectorAll('[data-cart-qty]').forEach(function (input) {
            const id = input.getAttribute('data-cart-qty');
            quantidades[id] = Math.max(0, parseInt(input.value || '0', 10) || 0);
        });

        fetch(cartForm.action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ quantidades: quantidades })
        })
        .then(res => res.json())
        .then(function (data) {
            if (mySeq !== requestSeq) return;
            aplicarEstadoCarrinho(data);
            if (data && data.sucesso && typeof window.goldenSiteToast === 'function') {
                window.goldenSiteToast(data.mensagem || 'Carrinho atualizado.', { type: 'success', duration: 2400 });
            }
        })
        .catch(() => {});
    }

    cartForm.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-cart-action]');
        if (!btn) return;

        e.preventDefault();
        const action = btn.getAttribute('data-cart-action');
        const id = btn.getAttribute('data-product-id');
        const input = cartForm.querySelector('[data-cart-qty="' + id + '"]');
        if (!input) return;

        const current = parseInt(input.value || '0', 10) || 0;
        const max = parseInt(input.getAttribute('max') || '999', 10) || 999;

        if (action === 'dec') {
            input.value = Math.max(0, current - 1);
        } else if (action === 'inc') {
            input.value = Math.min(max, current + 1);
        } else if (action === 'remove') {
            input.value = 0;
        }

        clearTimeout(pendingTimer);
        enviarQuantidades();
    });

    cartForm.addEventListener('change', function (e) {
        if (e.target.matches('[data-cart-qty]')) {
            clearTimeout(pendingTimer);
            pendingTimer = setTimeout(enviarQuantidades, 150);
        }
    });

    cartForm.addEventListener('submit', function (e) {
        e.preventDefault();
        enviarQuantidades();
    });

    if (formCupomAplicar) {
        formCupomAplicar.addEventListener('submit', function (e) {
            e.preventDefault();
            const codigoInput = document.getElementById('cartCupomCodigo');
            const codigo = codigoInput ? codigoInput.value.trim() : '';
            if (!codigo) return;

            if (cupomFeedback) cupomFeedback.style.display = 'none';

            fetch(formCupomAplicar.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ codigo: codigo })
            })
            .then(async res => ({ ok: res.ok, body: await res.json() }))
            .then(({ ok, body }) => {
                if (!ok || !body.sucesso) {
                    if (cupomFeedback) {
                        cupomFeedback.style.display = 'block';
                        cupomFeedback.style.color = '#dc2626';
                        cupomFeedback.textContent = (body && body.mensagem) ? body.mensagem : 'Cupom inválido.';
                    }
                    return;
                }
                if (codigoInput) codigoInput.value = '';
                if (cupomFeedback) {
                    cupomFeedback.style.display = 'block';
                    cupomFeedback.style.color = 'var(--teal)';
                    cupomFeedback.textContent = body.mensagem || 'Cupom aplicado!';
                }
                aplicarEstadoCarrinho(body);
            })
            .catch(() => {});
        });
    }

    if (formCupomRemover) {
        formCupomRemover.addEventListener('submit', function (e) {
            e.preventDefault();
            if (cupomFeedback) cupomFeedback.style.display = 'none';

            fetch(formCupomRemover.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({})
            })
            .then(res => res.json())
            .then(aplicarEstadoCarrinho)
            .catch(() => {});
        });
    }
});
</script>
@endpush
