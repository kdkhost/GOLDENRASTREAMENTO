@extends('layouts.site')

@section('title', 'Rastreamento de Encomendas em Tempo Real — ' . config('golden.marca.nome'))
@section('meta_description', 'Acompanhe o trajeto completo da sua encomenda em tempo real pela transportadora responsável.')

@section('content')
<section class="sec-dark" style="padding: 4rem 1rem 3rem; background: linear-gradient(180deg, #12161a 0%, #1a1f24 100%);">
    <div class="wrap" style="max-width: 860px; margin: 0 auto; text-align: center;">
        <span class="eyebrow" style="color: #e0a33e; text-transform: uppercase; font-size: 0.85rem; font-weight: 700; letter-spacing: 1px;">
            Logística & Entregas em Tempo Real
        </span>
        <h1 style="font-size: 2.2rem; color: #fff; margin: 0.5rem 0 1rem; font-family: 'Poppins', sans-serif;">
            Rastreamento de Encomendas
        </h1>
        <p style="color: #9ca3af; font-size: 1.05rem; max-width: 620px; margin: 0 auto 1.75rem;">
            Acompanhe todo o trajeto do seu pedido em tempo real de acordo com a transportadora responsável.
        </p>

        @if(!empty($logisticasAtivas))
        <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem; margin-bottom: 1.75rem;">
            @foreach($logisticasAtivas as $log)
                <span style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.8rem; border-radius: 999px; background: rgba(224, 163, 62, 0.12); border: 1px solid rgba(224, 163, 62, 0.3); color: #f3d28e; font-size: 0.8rem; font-weight: 600;">
                    <i class="fa-solid {{ $log['icone'] }}"></i> {{ $log['label'] }}
                </span>
            @endforeach
        </div>
        @endif

        {{-- Formulário de Consulta --}}
        <div style="background: #232930; border: 1px solid rgba(224, 163, 62, 0.25); border-radius: 16px; padding: 1.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
            <form method="GET" action="{{ route('site.rastreio.index') }}" style="display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center;">
                <div style="flex: 1 1 300px; position: relative;">
                    <input type="text" name="codigo" value="{{ $codigo }}"
                        placeholder="Ex.: QB123456789BR, código Melhor Envio ou nº do pedido"
                        required
                        style="width: 100%; padding: 0.9rem 1.2rem; border-radius: 10px; border: 1px solid #374151; background: #12161a; color: #fff; font-size: 1rem; outline: none; transition: border-color 0.2s;"
                        onfocus="this.style.borderColor='#e0a33e'" onblur="this.style.borderColor='#374151'">
                </div>
                <button type="submit"
                    style="background: #e0a33e; color: #12161a; font-weight: 700; font-size: 1rem; padding: 0.9rem 1.8rem; border-radius: 10px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; transition: transform 0.15s, background 0.15s;">
                    <i class="fa-solid fa-magnifying-glass"></i> Rastrear Agora
                </button>
            </form>
        </div>
    </div>
</section>

@if($rastreio)
<section class="sec-light" style="padding: 3rem 1rem; background: #f8f6f0; min-height: 50vh;">
    <div class="wrap" style="max-width: 860px; margin: 0 auto;">

        {{-- Card de Resumo & Barra de Trajeto em Tempo Real --}}
        <div id="rastreio-painel" data-codigo="{{ $rastreio['codigo'] }}" data-entregue="{{ $rastreio['entregue'] ? '1' : '0' }}"
             style="background: #fff; border-radius: 16px; border: 1px solid #e5e7eb; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.04);">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 1rem; border-bottom: 1px solid #f3f4f6; padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <span style="font-size: 0.8rem; text-transform: uppercase; color: #6b7280; font-weight: 700; letter-spacing: 0.5px;">
                            Código de Rastreamento
                        </span>
                        @if(!empty($rastreio['tempo_real']))
                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.72rem; font-weight: 700; color: #15803d; background: #dcfce7; padding: 0.15rem 0.55rem; border-radius: 999px;">
                                <span style="width: 7px; height: 7px; border-radius: 50%; background: #16a34a; display: inline-block;"></span> Ao vivo
                            </span>
                        @endif
                    </div>
                    <h2 style="font-size: 1.6rem; color: #111827; margin: 0.25rem 0; font-family: monospace; letter-spacing: 1px;">
                        {{ $rastreio['codigo'] }}
                    </h2>
                    <span style="color: #4b5563; font-size: 0.92rem;">
                        Transportadora responsável: <strong id="rastreio-transportadora">{{ $rastreio['transportadora'] }}</strong>
                    </span>
                </div>

                <div style="text-align: right;">
                    <span id="rastreio-status-badge" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 999px; font-weight: 700; font-size: 0.95rem; background: {{ $rastreio['entregue'] ? '#dcfce7; color: #15803d;' : '#fef3c7; color: #92400e;' }}">
                        <i class="fa-solid {{ $rastreio['icone'] }}"></i>
                        <span>{{ $rastreio['status_geral'] }}</span>
                    </span>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.45rem;">
                        Última checagem: <strong id="rastreio-atualizado-em">{{ $rastreio['atualizado_em'] }}</strong>
                    </div>
                    <div style="margin-top: 0.6rem; display: inline-flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; justify-content: flex-end;">
                        <button type="button" id="btn-atualizar-rastreio"
                            style="background: #12161a; color: #e0a33e; border: 1px solid rgba(224, 163, 62, 0.45); border-radius: 8px; padding: 0.4rem 0.85rem; font-size: 0.82rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-rotate"></i> Atualizar agora
                        </button>
                        @if(!empty($rastreio['link_externo']))
                            <a href="{{ $rastreio['link_externo'] }}" target="_blank" rel="noopener"
                               style="background: #f3f4f6; color: #1f2937; border: 1px solid #d1d5db; border-radius: 8px; padding: 0.4rem 0.85rem; font-size: 0.82rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-up-right-from-square"></i> Site da transportadora
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Barra Visual de Progresso Origem -> Destino --}}
            @php
                $etapasBarra = ['Preparação', 'Postado', 'Em Trânsito', 'Saiu p/ Entrega', 'Entregue'];
                $indiceAtivo = (int) ($rastreio['etapa_indice'] ?? 0);
                $progresso = (int) ($rastreio['progresso'] ?? 20);
            @endphp
            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: #4b5563; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <span><i class="fa-solid fa-warehouse text-gold me-1"></i> Origem: <strong>{{ $rastreio['origem'] }}</strong></span>
                    <span><i class="fa-solid fa-location-dot text-gold me-1"></i> Destino: <strong>{{ $rastreio['destino'] }}</strong></span>
                </div>
                <div style="height: 8px; background: #e5e7eb; border-radius: 999px; overflow: hidden; margin-bottom: 0.9rem;">
                    <div id="rastreio-barra-progresso" style="height: 100%; width: {{ $progresso }}%; background: linear-gradient(90deg, #b88412, #e0a33e); border-radius: 999px; transition: width 0.4s ease;"></div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.25rem; text-align: center; font-size: 0.74rem;">
                    @foreach($etapasBarra as $idx => $rotuloEtapa)
                        <div style="color: {{ $idx <= $indiceAtivo ? '#111827' : '#9ca3af' }}; font-weight: {{ $idx === $indiceAtivo ? '700' : '500' }};">
                            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-bottom: 0.2rem; background: {{ $idx <= $indiceAtivo ? '#e0a33e' : '#d1d5db' }};"></span>
                            <div>{{ $rotuloEtapa }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if(!empty($rastreio['pedido']))
            <div style="background: #f9fafb; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; font-size: 0.9rem; color: #4b5563;">
                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.5rem;">
                    <span>Pedido vinculado: <strong>#{{ $rastreio['pedido']->numero }}</strong></span>
                    <span>Destinatário: <strong>{{ $rastreio['pedido']->nome }}</strong></span>
                    @if($rastreio['pedido']->cidade)
                        <span>Destino: <strong>{{ $rastreio['pedido']->cidade }}/{{ $rastreio['pedido']->uf }}</strong></span>
                    @endif
                </div>
            </div>
            @endif

            @if(!empty($rastreio['instrucoes']))
            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 0.9rem 1.15rem; margin-bottom: 1.5rem; font-size: 0.88rem; color: #78350f;">
                <i class="fa-solid fa-circle-info me-1"></i> {{ $rastreio['instrucoes'] }}
            </div>
            @endif

            {{-- Trajeto Corrente (Ordem Cronológica da Origem até o Ponto Atual) --}}
            @if(!empty($rastreio['trajeto']))
            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 1.05rem; color: #111827; margin: 0 0 1rem; font-weight: 700;">
                    <i class="fa-solid fa-route text-gold me-1"></i> Trajeto Corrente da Encomenda
                </h3>
                <div id="rastreio-trajeto" style="display: flex; flex-wrap: wrap; gap: 0.65rem;">
                    @foreach($rastreio['trajeto'] as $ponto)
                        <div style="flex: 1 1 200px; background: {{ $ponto['atual'] ? '#fffbeb' : '#f9fafb' }}; border: 1px solid {{ $ponto['atual'] ? '#e0a33e' : '#e5e7eb' }}; border-radius: 10px; padding: 0.75rem 0.9rem;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">
                                <span>Etapa {{ $ponto['ordem'] }}</span>
                                <span>{{ $ponto['data_hora'] }}</span>
                            </div>
                            <div style="font-weight: 700; color: #111827; font-size: 0.88rem;">{{ $ponto['local'] }}</div>
                            <div style="font-size: 0.8rem; color: #4b5563; margin-top: 0.15rem;">{{ $ponto['status'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Linha do Tempo Completa de Movimentações --}}
            <h3 style="font-size: 1.15rem; color: #111827; margin: 0 0 1.5rem; font-weight: 700;">
                <i class="fa-solid fa-timeline text-gold me-1"></i> Histórico Completo de Movimentações
            </h3>

            <div id="rastreio-eventos-lista" style="position: relative; padding-left: 1.5rem;">
                <div style="position: absolute; left: 7px; top: 8px; bottom: 8px; width: 2px; background: #e5e7eb;"></div>

                @forelse($rastreio['eventos'] as $evento)
                <div style="position: relative; margin-bottom: 1.5rem;">
                    <div style="position: absolute; left: -1.5rem; top: 2px; width: 16px; height: 16px; border-radius: 50%; background: {{ !empty($evento['destaque']) ? '#e0a33e' : '#9ca3af' }}; border: 3px solid #fff; box-shadow: 0 0 0 1px {{ !empty($evento['destaque']) ? '#e0a33e' : '#d1d5db' }};"></div>

                    <div style="background: {{ !empty($evento['destaque']) ? '#fffbeb' : '#fff' }}; border: 1px solid {{ !empty($evento['destaque']) ? '#fde68a' : '#f3f4f6' }}; border-radius: 10px; padding: 1rem 1.25rem;">
                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 0.5rem; margin-bottom: 0.25rem;">
                            <strong style="color: #111827; font-size: 1rem;">
                                {{ $evento['status'] }}
                            </strong>
                            <span style="font-size: 0.85rem; color: #6b7280; font-weight: 500;">
                                <i class="fa-regular fa-calendar me-1"></i> {{ $evento['data'] }} às {{ $evento['hora'] }}
                            </span>
                        </div>
                        @if(!empty($evento['detalhe']))
                        <div style="color: #4b5563; font-size: 0.9rem; margin-top: 0.25rem;">
                            {{ $evento['detalhe'] }}
                        </div>
                        @endif
                        <div style="color: #6b7280; font-size: 0.8rem; margin-top: 0.4rem;">
                            <i class="fa-solid fa-location-dot me-1 text-gold"></i> {{ $evento['local'] }}
                        </div>
                    </div>
                </div>
                @empty
                <p style="color: #6b7280; font-size: 0.9rem;">Nenhuma movimentação registrada até o momento.</p>
                @endforelse
            </div>
        </div>

        {{-- Card de Ajuda / Atendimento --}}
        <div style="background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; padding: 1.5rem; text-align: center;">
            <p style="color: #4b5563; font-size: 0.95rem; margin: 0 0 0.8rem;">
                Precisa de ajuda com a sua entrega ou tem urgência no recebimento?
            </p>
            <a href="https://wa.me/{{ preg_replace('/\D/', '', \App\Models\Setting::get('contato_whatsapp', config('golden.whatsapp'))) }}?text=Ol%C3%A1%2C+preciso+de+ajuda+com+o+rastreamento+do+meu+pedido+{{ urlencode($rastreio['codigo']) }}"
                target="_blank" rel="noopener"
                style="display: inline-flex; align-items: center; gap: 0.5rem; background: #25d366; color: #fff; text-decoration: none; font-weight: 700; padding: 0.75rem 1.5rem; border-radius: 8px; font-size: 0.95rem;">
                <i class="fa-brands fa-whatsapp fs-5"></i> Falar com Suporte no WhatsApp
            </a>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const painel = document.getElementById('rastreio-painel');
    const btn = document.getElementById('btn-atualizar-rastreio');
    if (!painel || !btn) return;

    const codigo = painel.dataset.codigo;
    let carregando = false;

    const esc = (str) => String(str ?? '').replace(/[&<>"']/g, (m) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));

    async function atualizarEmTempoReal() {
        if (carregando || !codigo) return;
        carregando = true;
        btn.disabled = true;
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-rotate fa-spin"></i> Atualizando...';

        try {
            const url = '{{ route('site.rastreio.index') }}/' + encodeURIComponent(codigo) + '?atualizar=1';
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!resp.ok) return;
            const d = await resp.json();

            const elTransp = document.getElementById('rastreio-transportadora');
            const elAtu = document.getElementById('rastreio-atualizado-em');
            const elStatus = document.getElementById('rastreio-status-badge');
            const elBarra = document.getElementById('rastreio-barra-progresso');
            const elTrajeto = document.getElementById('rastreio-trajeto');
            const elLista = document.getElementById('rastreio-eventos-lista');

            if (elTransp) elTransp.textContent = d.transportadora || '';
            if (elAtu) elAtu.textContent = d.atualizado_em || '';
            if (elBarra) elBarra.style.width = (d.progresso || 20) + '%';
            if (elStatus) {
                elStatus.style.background = d.entregue ? '#dcfce7' : '#fef3c7';
                elStatus.style.color = d.entregue ? '#15803d' : '#92400e';
                elStatus.innerHTML = `<i class="fa-solid ${esc(d.icone || 'fa-box')}"></i> <span>${esc(d.status_geral || '')}</span>`;
            }

            if (elTrajeto && Array.isArray(d.trajeto)) {
                elTrajeto.innerHTML = d.trajeto.map((p) => `
                    <div style="flex: 1 1 200px; background: ${p.atual ? '#fffbeb' : '#f9fafb'}; border: 1px solid ${p.atual ? '#e0a33e' : '#e5e7eb'}; border-radius: 10px; padding: 0.75rem 0.9rem;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">
                            <span>Etapa ${esc(p.ordem)}</span>
                            <span>${esc(p.data_hora)}</span>
                        </div>
                        <div style="font-weight: 700; color: #111827; font-size: 0.88rem;">${esc(p.local)}</div>
                        <div style="font-size: 0.8rem; color: #4b5563; margin-top: 0.15rem;">${esc(p.status)}</div>
                    </div>
                `).join('');
            }

            if (elLista && Array.isArray(d.eventos)) {
                elLista.innerHTML = '<div style="position: absolute; left: 7px; top: 8px; bottom: 8px; width: 2px; background: #e5e7eb;"></div>' +
                    d.eventos.map((ev) => `
                        <div style="position: relative; margin-bottom: 1.5rem;">
                            <div style="position: absolute; left: -1.5rem; top: 2px; width: 16px; height: 16px; border-radius: 50%; background: ${ev.destaque ? '#e0a33e' : '#9ca3af'}; border: 3px solid #fff; box-shadow: 0 0 0 1px ${ev.destaque ? '#e0a33e' : '#d1d5db'};"></div>
                            <div style="background: ${ev.destaque ? '#fffbeb' : '#fff'}; border: 1px solid ${ev.destaque ? '#fde68a' : '#f3f4f6'}; border-radius: 10px; padding: 1rem 1.25rem;">
                                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 0.5rem; margin-bottom: 0.25rem;">
                                    <strong style="color: #111827; font-size: 1rem;">${esc(ev.status)}</strong>
                                    <span style="font-size: 0.85rem; color: #6b7280; font-weight: 500;"><i class="fa-regular fa-calendar me-1"></i> ${esc(ev.data)} às ${esc(ev.hora)}</span>
                                </div>
                                ${ev.detalhe ? `<div style="color: #4b5563; font-size: 0.9rem; margin-top: 0.25rem;">${esc(ev.detalhe)}</div>` : ''}
                                <div style="color: #6b7280; font-size: 0.8rem; margin-top: 0.4rem;"><i class="fa-solid fa-location-dot me-1 text-gold"></i> ${esc(ev.local)}</div>
                            </div>
                        </div>
                    `).join('');
            }
        } finally {
            carregando = false;
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }

    btn.addEventListener('click', atualizarEmTempoReal);

    if (painel.dataset.entregue !== '1') {
        setInterval(atualizarEmTempoReal, 30000);
    }
});
</script>
@endif
@endsection
