{{--
    Botão flutuante do WhatsApp + caixa multicanal (padrão foco7): o botão abre o box e cada
    atendente leva ao canal dele. O assistente virtual é um dos atendentes — o primeiro da
    lista — e abre a conversa no próprio site, sem sair da página; os demais seguem para o
    wa.me com a mensagem pronta. Desligável em Admin → Configurações (whatsapp_box_ativo) e
    por canal (whatsapp_canal_<id>_ativo / _numero).

    Sem a caixa (interruptor desligado) o botão volta a ser o link direto do WhatsApp, e o
    assistente perde a porta: o layout nem carrega a folha e o script dele. Quando não sobra
    nenhum canal de WhatsApp, o mesmo botão passa a ser a entrada do assistente.
--}}
@php
    $box = $golden['whatsapp_box'];
    $ia = $bolha ?? false;
    $ficha = $ia ? \App\Models\Assistente::paraABolha() : null;
    $avatar = $ficha?->avatarUrl();
    $atendentes = count($golden['canais']) + ($ia ? 1 : 0);
@endphp

@if ($box['ativo'] && $atendentes >= 2)
    <button type="button" class="wa-float" id="waFloat" aria-controls="waPopup" aria-expanded="false"
            aria-label="Abrir canais de atendimento da Golden" title="Central de atendimento pelo WhatsApp">
        @if($ia)
            <span class="ia-aviso" data-ia-aviso hidden aria-hidden="true"></span>
        @else
            <span class="wa-badge" aria-hidden="true"></span>
        @endif
        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
        <span class="wa-tip">Falar no WhatsApp</span>
    </button>

    <div class="wa-popup" id="waPopup" role="dialog" aria-label="Canais de atendimento da Golden">
        <div class="wa-head">
            <button type="button" class="wa-close" data-wa-close aria-label="Fechar canais de atendimento">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <span class="wa-online"><i class="fa-solid fa-circle" aria-hidden="true"></i> Online agora</span>
            <h6>{{ $box['titulo'] }}</h6>
            <small>{{ $box['subtitulo'] }}</small>
        </div>
        <div class="wa-body">
            @if($ia)
                {{-- O atendente virtual: não é um link para fora, é a conversa aqui dentro. --}}
                <button type="button" class="wa-channel wa-channel-ia" data-ia-abrir
                        aria-controls="iaPainel" aria-expanded="false"
                        aria-label="{{ $ficha->textoDica() }}">
                    <span class="wa-channel-icon">
                        @if($avatar)
                            <img src="{{ $avatar }}" alt="" width="38" height="38" loading="lazy" decoding="async">
                        @else
                            <i class="fa-solid {{ $ficha->iconeBolha() }}" aria-hidden="true"></i>
                        @endif
                    </span>
                    <span class="wa-channel-info">
                        <b>{{ $ficha->nome }}</b>
                        <em><span class="wa-channel-ia-on" aria-hidden="true"></span>{{ $ficha->textoStatus() }}</em>
                    </span>
                    <i class="fa-solid fa-chevron-right wa-channel-arrow" aria-hidden="true"></i>
                </button>
            @endif

            @foreach($golden['canais'] as $canal)
                <a href="{{ $canal['url'] }}" target="_blank" rel="noopener" class="wa-channel"
                   data-track="whatsapp_click" data-cta="canal_{{ $canal['id'] }}">
                    <span class="wa-channel-icon"><i class="fa-solid {{ $canal['icone'] }}" aria-hidden="true"></i></span>
                    <span class="wa-channel-info">
                        <b>{{ $canal['nome'] }}</b>
                        <em>{{ $canal['desc'] }}</em>
                    </span>
                    <i class="fa-solid fa-chevron-right wa-channel-arrow" aria-hidden="true"></i>
                </a>
            @endforeach
        </div>
        <a class="wa-other" href="{{ wa_url($box['abertura']) }}" target="_blank" rel="noopener"
           data-track="whatsapp_click" data-cta="canal_geral">Outro assunto com a Golden →</a>
    </div>
@elseif ($ia && count($golden['canais']) === 0)
    <button type="button" class="wa-float wa-float-ia" data-ia-abrir aria-controls="iaPainel"
            aria-expanded="false" aria-label="{{ $ficha->textoDica() }}" title="{{ $ficha->textoDica() }}">
        @if($avatar)
            <img class="wa-rosto" src="{{ $avatar }}" alt="" width="58" height="58" loading="lazy" decoding="async">
        @else
            <i class="fa-solid {{ $ficha->iconeBolha() }}" aria-hidden="true"></i>
        @endif
        <span class="ia-aviso" data-ia-aviso hidden aria-hidden="true"></span>
        <span class="wa-tip">{{ $ficha->textoDica() }}</span>
    </button>
@else
    <a class="wa-float" href="{{ wa_url($box['abertura']) }}" target="_blank" rel="noopener"
       aria-label="Falar no WhatsApp" data-track="whatsapp_click" data-cta="float">
        <span class="wa-badge" aria-hidden="true"></span>
        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
        <span class="wa-tip">Falar no WhatsApp</span>
    </a>
@endif
