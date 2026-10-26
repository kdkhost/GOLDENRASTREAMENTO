@props([
    'campo',
])
@php
    // Uma linha da tela de chaves: o campo, o selo de onde o valor está valendo e, para as
    // cifradas, o pedido explícito de apagar. Nada aqui decide rótulo, cor ou regra — os três
    // vêm de ChavesDeIntegracao, a fonte única que a tela, o controle e os testes consultam.
    $chave = $campo['chave'];
    $segredo = (bool) ($campo['segredo'] ?? false);
    $origem = \App\Support\ChavesDeIntegracao::origem($chave);
    $opcoes = $campo['opcoes'] ?? null;
    // Segredo nunca chega com valor no HTML. Os outros campos mostram só o que o banco guarda:
    // o que vem do .env é plano de fundo, e devolvê-lo pronto no input gravaria por cima dele
    // na primeira pessoa que apertasse Salvar sem querer.
    $valor = $segredo ? null : \App\Support\ChavesDeIntegracao::doBanco($chave);
    $rotulo = \App\Support\ChavesDeIntegracao::ROTULOS_ORIGEM[$origem];
@endphp
<div class="col-12">
    <x-admin.field
        :name="$chave"
        :label="$campo['label']"
        :type="$opcoes !== null ? 'select' : ($campo['type'] ?? ($segredo ? 'password' : 'text'))"
        :options="$opcoes"
        :empty="$opcoes !== null ? '— como está no .env —' : null"
        :value="$valor"
        :placeholder="$campo['placeholder'] ?? null"
        :hint="$campo['dica'] ?? null"
        :opcional="(bool) ($campo['opcional'] ?? false)"
        :col="''"
        maxlength="255"
        autocomplete="off"
    />

    <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
        <x-admin.status :rotulo="$rotulo" :tom="tom_por_status('origem_chave', $origem)" />
        <span class="form-hint"><code>{{ $campo['ambiente'] }}</code></span>

        @if($segredo && $origem === 'banco')
            <x-admin.switch name="{{ $chave }}_apagar" col="">
                <span class="small text-secondary">apagar e voltar ao <code>.env</code></span>
            </x-admin.switch>
        @endif
    </div>

    @if($segredo && $origem !== 'banco')
        <div class="form-hint">Cifrada no banco ao salvar; depois disto não aparece mais nesta tela.</div>
    @endif
</div>
