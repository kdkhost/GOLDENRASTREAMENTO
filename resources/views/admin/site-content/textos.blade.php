@extends('admin.layouts.app')
@section('title', 'Textos das páginas')
@section('page_title', 'Textos das páginas do site')
@section('page_description', 'A abertura de cada página fixa: o caminho, a linha de destaque, o título e o subtítulo.')

@section('page_actions')
    <a href="{{ route('admin.conteudo.imagens.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-photo-film me-1"></i> Imagens do site</a>
    <a href="{{ route('site.home') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-up-right-from-square me-1"></i> Ver o site</a>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.conteudo.textos.update') }}">
    @csrf

    <div class="callout">
        <span>
            <i class="fas fa-circle-info me-1" aria-hidden="true"></i>
            A home fica de fora: a abertura dela é montada no layout aprovado e o que se troca por
            painel são as fotos, em <a href="{{ route('admin.conteudo.imagens.index') }}">Imagens do site</a>.
            Em qualquer campo, <strong>deixar em branco faz voltar o texto original</strong>.
        </span>
    </div>

    <div class="row g-3 mt-1">
        @foreach($paginas as $pagina)
            <div class="col-12 col-xl-6">
                <x-admin.card :title="$pagina['nome']" icon="fa-heading" :descricao="'/'.ltrim((string) parse_url(route($pagina['rota']), PHP_URL_PATH), '/')">
                    <x-slot:actions>
                        <a href="{{ route($pagina['rota']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-up-right-from-square me-1"></i> Ver
                        </a>
                    </x-slot:actions>

                    <div class="row g-3">
                        @foreach(['rotulo' => 'Rótulo do caminho', 'eyebrow' => 'Linha de destaque', 'titulo' => 'Título', 'subtitulo' => 'Subtítulo'] as $campo => $rotulo)
                            @php
                                $padrao = $pagina['padrao'][$campo] ?? '';
                                $atual = old('paginas_site.'.$pagina['chave'].'.'.$campo, $pagina['valores'][$campo] ?? '');
                            @endphp
                            <x-admin.field :name="'paginas_site['.$pagina['chave'].']['.$campo.']'" :label="$rotulo"
                                :type="$campo === 'subtitulo' ? 'textarea' : 'text'" :value="$atual" :rows="3" :opcional="true"
                                :placeholder="$padrao ?: '—'"
                                :hint="$padrao === null ? 'Esta página não tem texto padrão: só aparece se você escrever aqui.' : 'Em branco, volta o texto original.'" />
                        @endforeach
                    </div>
                </x-admin.card>
            </div>
        @endforeach
    </div>

    <button class="btn btn-golden mt-3"><i class="fas fa-save me-1"></i> Salvar textos das páginas</button>
</form>
@endsection
