{{--
    Botão flutuante do WhatsApp + caixa multicanal (padrão foco7): o botão abre o box,
    cada canal abre o wa.me com a mensagem pronta. Desligável em Admin → Configurações
    (whatsapp_box_ativo) e por canal (whatsapp_canal_<id>_ativo / _numero).
--}}
@php $box = $golden['whatsapp_box']; @endphp

@if (!$box['ativo'] || count($golden['canais']) < 2)
    <a class="wa-float" href="{{ wa_url($box['abertura']) }}" target="_blank" rel="noopener"
       aria-label="Falar no WhatsApp" data-track="whatsapp_click" data-cta="float">
        <span class="wa-badge" aria-hidden="true"></span>
        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
        <span class="wa-tip">Falar no WhatsApp</span>
    </a>
@else
    <button type="button" class="wa-float" id="waFloat" aria-controls="waPopup" aria-expanded="false"
            aria-label="Abrir canais de atendimento da Golden" title="Central de atendimento pelo WhatsApp">
        <span class="wa-badge" aria-hidden="true"></span>
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
@endif
