{{--
    Campo de envio arrastar-e-soltar, com preview dentro do próprio campo.
    Parâmetros:
      $campo    chave de config/envios.php (define tipo, formatos, limite e dimensão)
      $name     nome do input no formulário (ex.: imagem)
      $valor    caminho já armazenado, ou lista de caminhos quando $multiplo
      $multiplo true para acumular vários arquivos
      $titulo   rótulo do campo, quando o registro tem mais de um (ex.: uma
                foto por slot do site); sem ele vale o título da regra
      $dica     orientação extra exibida abaixo da linha de regras
    O arquivo sobe pelo endpoint JSON assim que é solto: o campo mostra o
    progresso, o tempo decorrido e, no fim, o tamanho e as dimensões reais.
--}}
@php
    $name ??= $campo;
    $multiplo ??= false;
    $dica ??= null;
    $regra = \App\Support\Envio::regra($campo);
    $titulo ??= $regra['titulo'];
    $id = 'envio-'.substr(md5($campo.'|'.$name), 0, 8);
    $icone = ['imagem' => 'fa-image', 'video' => 'fa-film', 'documento' => 'fa-file-arrow-up'][$regra['tipo']] ?? 'fa-arrow-up-right-from-square';
    $guardados = array_map(
        fn (string $caminho) => \App\Support\Envio::descritor($caminho, $campo),
        array_values(array_filter(is_array($valor ?? null) ? $valor : [$valor ?? null]))
    );
@endphp
<div class="upload" data-upload
     data-campo="{{ $campo }}"
     data-tipo="{{ $regra['tipo'] }}"
     data-nome="{{ $name }}"
     data-multiplo="{{ $multiplo ? 1 : 0 }}"
     data-max-bytes="{{ \App\Support\Envio::maxBytes($campo) }}"
     data-formatos="{{ implode(',', $regra['formatos']) }}"
     data-dimensoes="{{ $regra['dimensao'] }}"
     data-envio="{{ route('admin.uploads.store') }}"
     data-descarte="{{ route('admin.uploads.destroy') }}"
     data-token="{{ csrf_token() }}"
     data-arquivos='@json($guardados)'>
    <input type="file" id="{{ $id }}" class="upload-arquivo" hidden
           accept="{{ \App\Support\Envio::accept($campo) }}"
           @if($multiplo) multiple @endif>
    <button type="button" class="upload-alvo" data-upload-alvo>
        <span class="upload-miniatura" data-upload-miniatura hidden></span>
        <span class="upload-copy">
            <strong>{{ $titulo }}</strong>
            <span data-upload-chamada>{{ $regra['chamada'] }}</span>
            <small>{{ \App\Support\Envio::dica($campo) }}@if($dica)<br>{{ $dica }}@endif</small>
        </span>
        <span class="upload-selo" aria-hidden="true"><i class="fa-solid {{ $icone }}"></i></span>
        <span class="upload-progresso" aria-hidden="true"><i data-upload-barra></i></span>
    </button>
    <p class="upload-estado" data-upload-estado role="status" aria-live="polite"></p>
    <ul class="upload-fila" data-upload-fila></ul>
</div>
