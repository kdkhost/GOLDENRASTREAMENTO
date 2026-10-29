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

        // O anexo só existe na tela quando o painel ligou o tipo: a lista de extensões que o
        // seletor de arquivos mostra é a mesma que a `Midia` confere no upload, e a dica do botão
        // repete o teto que o servidor aplica — a tela não promete o que o código recusa.
        $anexos = collect(['imagem', 'arquivo', 'audio'])
            ->filter(fn (string $tipo): bool => \App\Services\Assistente\Midia::permitido($tipo))
            ->values();
        $aceita = $anexos
            ->flatMap(fn (string $tipo): array => \App\Services\Assistente\Midia::extensoesDe($tipo))
            ->unique()
            ->map(fn (string $ext): string => '.'.$ext)
            ->implode(',');
        $limites = $anexos
            ->map(fn (string $tipo): string => \App\Services\Assistente\Midia::rotuloParaCliente($tipo)
                .' até '.\App\Services\Assistente\Midia::limiteEmMb($tipo).' MB')
            ->implode(' · ');

        // Endereço do rosto na ficha; sem foto, o painel mostra só o nome. O mesmo avatar
        // aparece no atendente da caixa e na cabeça do balão, para a conversa continuar com a
        // cara de quem abriu a porta.
        $avatar = $ficha->avatarUrl();
        $rosto = [
            'abrir' => route('site.assistente.abrir'),
            'fala' => route('site.assistente.fala'),
            'falas' => route('site.assistente.falas'),
            'avaliar' => route('site.assistente.avaliar'),
            'arquivo' => route('site.assistente.arquivo'),
            'encerrar' => route('site.assistente.encerrar'),
            'nova' => route('site.assistente.nova'),
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

        {{-- A pesquisa entra em estrelas para que a nota não dependa da pessoa saber escrever
             "quatro": o toque grava o mesmo número que a fala escrita gravaria. Só aparece depois
             de a máquina perguntar, e some no primeiro toque — pedir duas vezes é estragar a nota. --}}
        <div class="ia-avaliacao" data-ia-avaliacao hidden>
            <div class="ia-estrelas" role="group" aria-label="Nota de 1 a 5">
                @for($nota = 1; $nota <= 5; $nota++)
                    <button type="button" class="ia-estrela" data-ia-nota="{{ $nota }}"
                            aria-label="{{ $nota }} de 5">
                        <i class="fa-regular fa-star" aria-hidden="true"></i>
                    </button>
                @endfor
            </div>
        </div>

        <p class="ia-digitando" data-ia-digitando hidden>
            <span class="ia-pontos" aria-hidden="true"><i></i><i></i><i></i></span>
            {{ $ficha->nome }} está digitando…
        </p>

        <div class="ia-anexo" data-ia-anexo hidden>
            <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
            <span data-ia-anexo-nome></span>
            <button type="button" class="ia-anexo-limpar" data-ia-anexo-limpar aria-label="Remover o arquivo">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <form class="ia-passos" data-ia-form>
            <div class="ia-campo">
                @if($aceita !== '')
                    <button type="button" class="ia-anexar" data-ia-anexar aria-label="Anexar um arquivo" title="Anexar — {{ $limites }}">
                        <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                    </button>
                    <input type="file" class="ia-oculto" data-ia-arquivo accept="{{ $aceita }}" tabindex="-1">
                @endif
                <label class="ia-oculto" for="iaTexto">Escreva sua mensagem para {{ $ficha->nome }}</label>
                <textarea id="iaTexto" name="texto" rows="1" maxlength="1000" autocomplete="off"
                          placeholder="{{ $ficha->textoPlaceholder() }}" required></textarea>
            </div>
            <button type="submit" class="ia-enviar" data-ia-enviar aria-label="Enviar mensagem">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
            </button>
        </form>

        <div class="ia-fecho" data-ia-fecho>
            <button type="button" class="ia-encerrar" data-ia-encerrar>
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Encerrar conversa
            </button>
        </div>

        <div class="ia-fim" data-ia-fim hidden>
            <p class="ia-fim-texto"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Conversa encerrada.</p>
            <button type="button" class="ia-nova" data-ia-nova>Começar uma nova conversa</button>
        </div>

        <a class="ia-humano" href="{{ route('site.contato') }}" data-ia-humano>{{ $ficha->textoHumano() }}</a>
    </div>

    <script>window.GOLDEN_IA = @json($rosto);</script>
@endif
