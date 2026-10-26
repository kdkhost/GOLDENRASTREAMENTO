{{--
    A bolha de atendimento com o assistente: um botão no canto e um balão de conversa que fala
    com o robô. É uma só parcial para o site e para o funil — o que muda é o `$canal`, que o
    layout passa e que decide tanto o interruptor da ficha (`no_site` / `no_funil`) quanto a
    origem gravada na conversa. `$bolha` vem do mesmo layout, medido antes de a folha de estilo
    ser carregada: a pergunta é uma só e não se paga duas vezes por página.

    A bolha não cadastra ninguém. Ela atende e, quando o assunto é proposta, empurra o visitante
    para o formulário que a página já tem; o cadastro continua tendo uma casa só.

    O botão só existe se a ficha manda atender neste canal e se quem abriu a página não é um
    robô de busca: indexador não abre conversa, e o painel encheria de "atendimentos" que
    nenhum humano leu. O resto da régua — fonte, escalada, teto de caracteres — é do servidor,
    e o endpoint repete a pergunta antes de gastar uma chamada.

    Os textos e o ícone (quando não há rosto) vêm da ficha, editável no painel. A bolha ocupa o
    andar de cima da calha: o WhatsApp continua no pé da tela, e o robô fica logo acima dele.
--}}
@if($bolha ?? false)
    @php
        $ficha = \App\Models\Assistente::paraABolha();
        $canal = $canal ?? 'site';

        // Endereço do rosto na ficha; sem foto, a bolha desenha o ícone. O mesmo avatar aparece no
        // topo do balão, porque a conversa tem que continuar com a mesma cara de quem abriu o botão.
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

    <button type="button" class="ia-float" id="iaFloat" aria-controls="iaPainel" aria-expanded="false"
            aria-label="{{ $ficha->textoDica() }}" title="{{ $ficha->textoDica() }}">
        @if($avatar)
            <img class="ia-rosto" src="{{ $avatar }}" alt="" width="58" height="58" loading="lazy" decoding="async">
        @else
            <i class="fa-solid {{ $ficha->iconeBolha() }}" aria-hidden="true"></i>
        @endif
        <span class="ia-aviso" data-ia-aviso hidden aria-hidden="true"></span>
        <span class="ia-dica">{{ $ficha->textoDica() }}</span>
    </button>

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
