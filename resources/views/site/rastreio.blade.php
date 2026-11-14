@extends('layouts.site')

@section('title', 'Rastreamento de Encomendas em Tempo Real — ' . config('golden.marca.nome'))
@section('meta_description', 'Acompanhe a entrega do seu rastreador veicular em tempo real com o código dos Correios ou número do pedido.')

@section('content')
<section class="sec-dark" style="padding: 4rem 1rem 3rem; background: linear-gradient(180deg, #12161a 0%, #1a1f24 100%);">
    <div class="wrap" style="max-width: 800px; margin: 0 auto; text-align: center;">
        <span class="eyebrow" style="color: #e0a33e; text-transform: uppercase; font-size: 0.85rem; font-weight: 700; letter-spacing: 1px;">
            Logística & Entregas
        </span>
        <h1 style="font-size: 2.2rem; color: #fff; margin: 0.5rem 0 1rem; font-family: 'Poppins', sans-serif;">
            Rastreamento de Encomendas
        </h1>
        <p style="color: #9ca3af; font-size: 1.05rem; max-width: 580px; margin: 0 auto 2.5rem;">
            Acompanhe o envio do seu rastreador veicular em tempo real. Digite o código de rastreio dos Correios ou o número do seu pedido.
        </p>

        {{-- Formulário de Consulta --}}
        <div style="background: #232930; border: 1px solid rgba(224, 163, 62, 0.25); border-radius: 16px; padding: 1.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
            <form method="GET" action="{{ route('site.rastreio.index') }}" style="display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center;">
                <div style="flex: 1 1 300px; position: relative;">
                    <input type="text" name="codigo" value="{{ $codigo }}"
                        placeholder="Ex.: QB123456789BR ou número do pedido"
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
    <div class="wrap" style="max-width: 800px; margin: 0 auto;">
        
        {{-- Card de Resumo --}}
        <div style="background: #fff; border-radius: 16px; border: 1px solid #e5e7eb; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.04);">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; border-bottom: 1px solid #f3f4f6; padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <span style="font-size: 0.8rem; text-transform: uppercase; color: #6b7280; font-weight: 700; letter-spacing: 0.5px;">
                        Código de Rastreamento
                    </span>
                    <h2 style="font-size: 1.6rem; color: #111827; margin: 0.2rem 0; font-family: monospace; letter-spacing: 1px;">
                        {{ $rastreio['codigo'] }}
                    </h2>
                    <span style="color: #4b5563; font-size: 0.9rem;">
                        Transportadora: <strong>{{ $rastreio['transportadora'] }}</strong>
                    </span>
                </div>
                <div style="text-align: right;">
                    <span style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 999px; font-weight: 700; font-size: 0.95rem; background: {{ $rastreio['entregue'] ? '#dcfce7; color: #15803d;' : '#e0f2fe; color: #0369a1;' }}">
                        <i class="fa-solid {{ $rastreio['icone'] }}"></i> {{ $rastreio['status_geral'] }}
                    </span>
                    <div style="font-size: 0.8rem; color: #9ca3af; margin-top: 0.4rem;">
                        Última checagem: {{ $rastreio['atualizado_em'] }}
                    </div>
                </div>
            </div>

            @if($rastreio['pedido'])
            <div style="background: #f9fafb; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 2rem; font-size: 0.9rem; color: #4b5563;">
                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.5rem;">
                    <span>Pedido vinculado: <strong>#{{ $rastreio['pedido']->numero }}</strong></span>
                    <span>Destinatário: <strong>{{ $rastreio['pedido']->nome }}</strong></span>
                    @if($rastreio['pedido']->cidade)
                        <span>Destino: <strong>{{ $rastreio['pedido']->cidade }}/{{ $rastreio['pedido']->uf }}</strong></span>
                    @endif
                </div>
            </div>
            @endif

            {{-- Linha do Tempo dos Correios --}}
            <h3 style="font-size: 1.15rem; color: #111827; margin: 0 0 1.5rem; font-weight: 700;">
                <i class="fa-solid fa-timeline text-gold me-1"></i> Histórico de Movimentações
            </h3>

            <div style="position: relative; padding-left: 1.5rem;">
                {{-- Linha vertical contínua --}}
                <div style="position: absolute; left: 7px; top: 8px; bottom: 8px; width: 2px; background: #e5e7eb;"></div>

                @forelse($rastreio['eventos'] as $evento)
                <div style="position: relative; margin-bottom: 2rem;">
                    {{-- Ponto indicador --}}
                    <div style="position: absolute; left: -1.5rem; top: 2px; width: 16px; height: 16px; border-radius: 50%; background: {{ $evento['destaque'] ? '#e0a33e' : '#9ca3af' }}; border: 3px solid #fff; box-shadow: 0 0 0 1px {{ $evento['destaque'] ? '#e0a33e' : '#d1d5db' }};"></div>
                    
                    <div style="background: {{ $evento['destaque'] ? '#fffbeb' : '#fff' }}; border: 1px solid {{ $evento['destaque'] ? '#fde68a' : '#f3f4f6' }}; border-radius: 10px; padding: 1rem 1.25rem;">
                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 0.5rem; margin-bottom: 0.25rem;">
                            <strong style="color: #111827; font-size: 1rem;">
                                {{ $evento['status'] }}
                            </strong>
                            <span style="font-size: 0.85rem; color: #6b7280; font-weight: 500;">
                                <i class="fa-regular fa-calendar me-1"></i> {{ $evento['data'] }} às {{ $evento['hora'] }}
                            </span>
                        </div>
                        <div style="color: #4b5563; font-size: 0.9rem; margin-top: 0.25rem;">
                            {{ $evento['detalhe'] }}
                        </div>
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
@endif
@endsection
