@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'options' => null,
    'empty' => null,
    'col' => 'col-12',
    'placeholder' => null,
    'hint' => null,
    'rows' => 4,
    'required' => false,
    'opcional' => false,
    'ajuda' => null,
])
@php
    $id = 'f-'.preg_replace('/[^a-z0-9]+/i', '-', $name);
    // Nome de array HTML (alt[home_hero]) vira chave de ponto (alt.home_hero): é assim que o
    // validador guarda o erro e é assim que old() procura — sem isso o campo nunca acusa erro.
    $chave = rtrim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $atual = old($chave, $value);
    $comErro = $errors->has($chave) ? ' is-invalid' : '';
    $classe = ($type === 'select' ? 'form-select' : 'form-control').$comErro;
    $extra = $attributes->except('class');
    // A ajuda do campo é procurada pelo próprio nome: nenhuma view precisa lembrar de chamá-la, e
    // `ajuda` só serve quando dois campos contam a mesma história (os dois servidores de acesso,
    // por exemplo). Sem entrada no config, o ícone não existe e o rótulo fica como sempre esteve.
    $gaveta = \App\Support\GuiaConfiguracao::gaveta($ajuda ?? $name, $label);
@endphp
<div class="{{ $col }}">
    {{-- O rótulo e o ícone na mesma linha; a dica embaixo. Dentro do `<label>` a dica empurrava o
         ícone para a última linha do bloco, e ele ficava solto em campo com explicação. --}}
    <div class="{{ $gaveta ? 'ajuda-rotulo' : '' }}">
        <label class="form-label" for="{{ $id }}">
            {{ $label }}
            @if($opcional)<span class="form-hint">(opcional)</span>@endif
        </label>
        @if($gaveta)<x-admin.ajuda :gaveta="$gaveta" />@endif
        @if($hint)<span class="form-hint d-block">{{ $hint }}</span>@endif
    </div>

    @if($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" class="{{ $classe }} {{ $attributes->get('class') }}"
            @if($placeholder) placeholder="{{ $placeholder }}" @endif @if($required) required @endif {!! $extra !!}>{{ $atual }}</textarea>
    @elseif($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" class="{{ $classe }} {{ $attributes->get('class') }}"
            @if($required) required @endif {!! $extra !!}>
            @if($empty !== null)<option value="">{{ $empty }}</option>@endif
            {{-- Os nomes abaixo não podem ser $chave: ela guarda a chave pontuada do validador,
                 e um laço que a reescreve cala o @error() do campo — a caixa fica vermelha e
                 muda de tamanho, mas ninguém lê o motivo. --}}
            @foreach($options as $valorOpcao => $rotuloOpcao)
                <option value="{{ $valorOpcao }}" @selected((string) $atual === (string) $valorOpcao)>{{ $rotuloOpcao }}</option>
            @endforeach
        </select>
    @else
        <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" value="{{ $atual }}"
            class="{{ $classe }} {{ $attributes->get('class') }}"
            @if($placeholder) placeholder="{{ $placeholder }}" @endif @if($required) required @endif {!! $extra !!}>
    @endif

    @error($chave)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
