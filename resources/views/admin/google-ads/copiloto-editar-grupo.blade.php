{{--
    Um grupo de anúncios do editor de plano. O mesmo arquivo desenha a linha que a pessoa vê e o
    modelo que `golden-grupos.js` clona ao adicionar grupo, senão os dois campos divergiriam no
    dia em que um dos dois fosse mexido.

    O índice no nome dos campos (`grupos[0][palavras]`) é o que o validador devolve como
    `grupos.0.palavras`; por isso o JS reescreve nome e id de cada bloco clonado — sem isso o
    erro cairia numa linha que não está na tela.
--}}
<div class="col-12" data-grupo>
    <div class="ads-editor-grupo">
        <div class="row g-3">
            <x-admin.field name="grupos[{{ $indice }}][nome]" label="Nome do grupo" :value="$grupo['nome'] ?? ''"
                :required="true" maxlength="60" col="col-12 col-md-7" data-campo="nome"
                placeholder="Ex.: Rastreamento para frotas" />

            <x-admin.field name="grupos[{{ $indice }}][tema]" label="Tema" :value="$grupo['tema'] ?? ''"
                maxlength="160" col="col-12 col-md-5" :opcional="true" data-campo="tema"
                placeholder="Quem procura isso e o que você entrega" />

            <x-admin.field name="grupos[{{ $indice }}][palavras]" type="textarea" label="Palavras-chave"
                :value="$grupo['palavras'] ?? ''" :rows="6" :required="true" maxlength="2500" col="col-12" data-campo="palavras"
                placeholder="rastreamento de frota | frase&#10;rastreador para transportadora | ampla"
                hint="Uma palavra por linha, com a correspondência depois da barra: ampla, frase ou exata. Sem a barra, fica em frase." />

            <x-admin.field name="grupos[{{ $indice }}][negativas]" type="textarea" label="Palavras negativas"
                :value="$grupo['negativas'] ?? ''" :rows="3" maxlength="6000" col="col-12" :opcional="true" data-campo="negativas"
                placeholder="grátis&#10;emprego&#10;como funciona"
                hint="Buscas que não devem mostrar o anúncio. Uma por linha." />

            <div class="col-12 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-remove-grupo>
                    <i class="fas fa-trash me-1" aria-hidden="true"></i> Tirar este grupo
                </button>
            </div>
        </div>
    </div>
</div>
