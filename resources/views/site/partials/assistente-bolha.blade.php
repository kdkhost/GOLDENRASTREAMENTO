{{--
    A conversa com o assistente virtual: o balão que abre por cima do botão do WhatsApp. O
    gatilho não mora aqui — é o primeiro atendente da caixa multicanal (`whatsapp-box`), porque
    o robô não é um botão à parte: ele é um dos atendentes da casa. Esta parcial entrega o
    painel, a cabeça com o rosto da ficha e o contrato `window.GOLDEN_IA`.

    É uma só parcial para o site e para o funil — o que muda é o `$canal`, que o layout passa e
    que decide tanto o interruptor da ficha (`no_site` / `no_funil`) quanto a origem gravada na
    conversa. `$bolha` já chega resolvido do layout: caixa existente e ficha atendendo aqui.

    A conversa não cadastra ninguém. Ela atende e, quando o assunto é proposta, empurra o
    visitante para o formulário que a página já tem; o cadastro continua tendo uma casa só.

    Os textos e o ícone (quando não há rosto) vêm da ficha, editável no painel.
--}}
@if($bolha ?? false)
    @php
        $ficha = \App\Models\Assistente::paraABolha();
        $canal = $canal ?? 'site';

        // Endereço do rosto na ficha; sem foto, o painel mostra só o nome. O mesmo avatar
        // aparece no atendente da caixa e na cabeça do balão, para a conversa continuar com a
        // cara de quem abriu a porta.
        $avatar = $ficha->avatarUrl();
        $rosto = [
            'abrir' => route('site.assistente.abrir'),
            'fala' => route('site.assistente.fala'),
            'falas' => route('site.assistente.falas'),
            'contato' => route('site.contato'),
            'canal' => $canal,
            'nome' => (string) $ficha->nome,
        ];
    @endphp

    <div class="ia-painel" id="iaPainel" role="dialog" aria-label="Conversa com {{ $ficha->nome }}">
        <div class="ia-cabeca">
            <button type="button" class="ia-fechar" data-ia-fechar aria-label="Fechar a conversa">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <span class="ia-selo"><i class="fa-solid fa-circle" aria-hidden="true"></i> {{ $ficha->textoStatus() }}</span>
            @if($avatar)
                <img class="ia-rosto ia-rosto-cabeca" src="{{ $avatar }}" alt="" width="34" height="34" loading="lazy" decoding="async">
            @endif
            <h6>{{ $ficha->nome }}</h6>
            <small>{{ $ficha->cargo }}</small>
        </div>

        <div class="ia-corpo" data-ia-corpo aria-live="polite"></div>

        <p class="ia-digitando" data-ia-digitando hidden>
            <span class="ia-pontos" aria-hidden="true"><i></i><i></i><i></i></span>
            {{ $ficha->nome }} está digitando…
        </p>

        <form class="ia-passos" data-ia-form>
            <label class="ia-oculto" for="iaTexto">Escreva sua mensagem para {{ $ficha->nome }}</label>
            <textarea id="iaTexto" name="texto" rows="1" maxlength="1000" autocomplete="off"
                      placeholder="{{ $ficha->textoPlaceholder() }}" required></textarea>
            <button type="submit" class="ia-enviar" data-ia-enviar aria-label="Enviar mensagem">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
            </button>
        </form>

        <a class="ia-humano" href="{{ route('site.contato') }}" data-ia-humano>{{ $ficha->textoHumano() }}</a>
    </div>

    <script>window.GOLDEN_IA = @json($rosto);</script>
@endif
