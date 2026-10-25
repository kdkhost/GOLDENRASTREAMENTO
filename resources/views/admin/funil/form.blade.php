@extends('admin.layouts.app')
@php $editando = $pagina->exists; @endphp
@section('title', $editando ? 'Editar campanha' : 'Nova página de funil')
@section('page_title', $editando ? 'Editar: '.$pagina->nome : 'Nova página de funil')
@section('page_description', 'Funil de divulgação fora do menu e da busca: só chega quem tem o link.')

@section('page_actions')
    @if($editando)
        <a href="{{ route('admin.funil.show', $pagina) }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-chart-column me-1" aria-hidden="true"></i> Contador
        </a>
        @if($pagina->status === 'publicada')
            <a href="{{ $pagina->url() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-up-right-from-square me-1" aria-hidden="true"></i> Abrir a campanha
            </a>
        @endif
    @endif
    <a href="{{ route('admin.funil.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Voltar</a>
@endsection

@section('content')
<form method="POST" action="{{ $editando ? route('admin.funil.update', $pagina) : route('admin.funil.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="A página" icon="fa-bullseye">
                <div class="row g-3">
                    <x-admin.field name="nome" label="Nome interno" :value="$pagina->nome" col="col-12 col-md-7" :required="true" maxlength="120"
                        placeholder="Ex.: Google Ads kit 5 veículos — out/26" />
                    <x-admin.field name="slug" label="Endereço (/c/…)" :value="$pagina->slug" col="col-12 col-md-5" maxlength="120" :opcional="true"
                        hint="Em branco, geramos a partir do nome. Trocar depois não apaga as visitas antigas." />
                    <x-admin.field name="status" type="select" label="Situação" :value="$pagina->status" :options="\App\Models\FunnelPage::statusRotulos()" :required="true" col="col-12 col-md-4" />
                    <x-admin.field name="visibilidade" type="select" label="Na busca" :value="$pagina->visibilidade" :options="config('golden.funil_visibilidade')" :required="true" col="col-12 col-md-4" />
                    <x-admin.field name="pele" type="select" label="Pele da página" :value="$pagina->pele" :options="config('golden.funil_peles')" :required="true" col="col-12 col-md-4" />
                </div>
            </x-admin.card>

            <x-admin.card title="O que o buscador e o compartilhamento veem" icon="fa-magnifying-glass" class="mt-3"
                descricao="Título e resumo da página, a foto que vai no anúncio e o preço que o Google lê como oferta.">
                <div class="row g-3">
                    <x-admin.field name="titulo" label="Título da página" :value="$pagina->titulo" maxlength="190" :opcional="true"
                        hint="Sem ele, o nome interno vira o <title> e o H1 da abertura." />
                    <x-admin.field name="meta_description" type="textarea" label="Meta descrição" :value="$pagina->meta_description" rows="2" maxlength="255" :opcional="true"
                        hint="As duas linhas que aparecem no resultado da busca e no card compartilhado." />
                    <x-admin.field name="meta_keywords" label="Palavras-chave" :value="$pagina->meta_keywords" maxlength="190" :opcional="true"
                        hint="Separadas por vírgula. A busca moderna dá peso à frase da página, não à lista." />
                    <x-admin.field name="canonical" label="URL canônica" :value="$pagina->canonical" maxlength="255" :opcional="true"
                        hint="Só quando a mesma campanha existe em outro endereço. Em branco, o canônico é o próprio /c/." />
                    <div class="col-12">
                        <label class="form-label">Foto do card
                            <span class="form-hint">(opcional)</span>
                            <span class="form-hint d-block">É a imagem que aparece no anúncio, no WhatsApp e no Facebook. Sem ela, usamos a primeira foto dos blocos.</span>
                        </label>
                        @include('partials.upload', [
                            'campo' => 'site_imagem',
                            'name' => 'og_imagem',
                            'valor' => old('og_imagem', $pagina->og_imagem),
                            'titulo' => 'Foto do card da campanha',
                            'dica' => 'Paisagem, 1200 × 630 px — o formato do compartilhamento.',
                        ])
                    </div>
                    <x-admin.field name="og_imagem_alt" label="Descrição da foto do card" :value="$pagina->og_imagem_alt" maxlength="190" :opcional="true" />
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="f-preco">Preço anunciado (R$)
                            <span class="form-hint d-block">Alimenta o JSON-LD de oferta e o valor da conversão do Google Ads.</span>
                        </label>
                        <input id="f-preco" name="preco" type="text" inputmode="decimal" class="form-control"
                            placeholder="1.290,00" value="{{ old('preco', $pagina->preco_cents ? number_format($pagina->preco_cents / 100, 2, ',', '.') : '') }}">
                        @error('preco_cents')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <x-admin.field name="nota_media" type="number" label="Nota média (0 a 5)" :value="$pagina->nota_media" col="col-12 col-md-4" step="0.1" min="0" max="5" :opcional="true"
                        hint="Com o número de avaliações, vira as estrelas do resultado enriquecido." />
                    <x-admin.field name="num_avaliacoes" type="number" label="Nº de avaliações" :value="$pagina->num_avaliacoes" col="col-12 col-md-4" min="0" :opcional="true" />
                    <x-admin.field name="nota_legal" type="textarea" label="Aviso legal do rodapé" :value="$pagina->nota_legal" rows="2" :opcional="true"
                        hint="Ex.: condição limitada a uma quantidade de kits, prazo da condição." />
                </div>
            </x-admin.card>

            <x-admin.card title="Pixels e medidas da campanha" icon="fa-chart-line" class="mt-3"
                descricao="Cada anúncio measure o que aconteceu na própria página — nada é herdado do pixel global do site.">
                <div class="row g-3">
                    <x-admin.field name="pixel_ga4" label="GA4 (G-XXXXXXX)" :value="$pagina->pixel_ga4" col="col-12 col-md-6" maxlength="40" :opcional="true" />
                    <x-admin.field name="pixel_google_ads" label="Google Ads (AW-XXXXXXX)" :value="$pagina->pixel_google_ads" col="col-12 col-md-6" maxlength="40" :opcional="true" />
                    <x-admin.field name="conversao_google_ads" label="Rótulo de conversão (AW-…/xxxx)" :value="$pagina->conversao_google_ads" col="col-12 col-md-6" maxlength="60" :opcional="true"
                        hint="Dispara no cadastro concluído, com o preço anunciado como valor." />
                    <x-admin.field name="pixel_meta" label="Meta (Facebook/Instagram) Pixel ID" :value="$pagina->pixel_meta" col="col-12 col-md-6" maxlength="60" :opcional="true" />
                    <x-admin.field name="pixel_tiktok" label="TikTok Pixel ID" :value="$pagina->pixel_tiktok" col="12 col-md-6" maxlength="60" :opcional="true" />
                    <x-admin.field name="codigo_head" type="textarea" label="Código adicional do <head>" :value="$pagina->codigo_head" rows="3" :opcional="true" class="font-monospace"
                        hint="Outras plataformas (LinkedIn, Bing, X). Cole a tag <script> como ela é." />
                    <x-admin.field name="codigo_body" type="textarea" label="Código adicional após a página" :value="$pagina->codigo_body" rows="3" :opcional="true" class="font-monospace" />
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Publicar" icon="fa-rocket">
                <button class="btn btn-golden w-100"><i class="fas fa-save me-1" aria-hidden="true"></i> {{ $editando ? 'Salvar página' : 'Criar página' }}</button>
                @if($editando)
                    <div class="mt-3">
                        <x-admin.link-copiar :valor="$pagina->url()" label="Link da campanha" hint="É o endereço que vai no anúncio. Só quem o recebe vê a página." />
                    </div>
                    <p class="small text-secondary mt-3 mb-0">
                        <i class="fas fa-circle-info me-1" aria-hidden="true"></i>
                        {{ $pagina->status === 'publicada' ? 'A página está no ar.' : 'Enquanto não estiver publicada, o link responde 404.' }}
                    </p>
                @else
                    <p class="small text-secondary mt-3 mb-0">Salve para liberar a montagem dos blocos e receber o link.</p>
                @endif
            </x-admin.card>

            <x-admin.card title="Boas práticas da campanha" icon="fa-list-check" class="mt-3">
                <ul class="small text-secondary mb-0 ps-3">
                    <li>Deixe a visibilidade em <b>invisível na busca</b>: a campanha é medida pelos anúncios, e o
                        mesmo texto no Google orgânico confunde a leitura das conversões.</li>
                    <li>Um anúncio, uma página: o título repete a promessa do anúncio e o botão leva ao formulário.</li>
                    <li>Use <code>#formulario</code> como destino dos botões da abertura e da urgência.</li>
                    <li>O bloco de captura é o formulário do site — o mesmo registro de origem, sem campo duplicado.</li>
                </ul>
            </x-admin.card>
        </div>
    </div>
</form>

@if($editando)
    <x-admin.card title="Blocos da página" icon="fa-layer-group" class="mt-3"
        :descricao="$pagina->blocks->count().' '.($pagina->blocks->count() === 1 ? 'bloco' : 'blocos').' — a ordem decide o que o visitante lê primeiro.'">
        <x-slot:actions>
            <a href="{{ route('admin.funil.blocos.create', $pagina) }}" class="btn btn-sm btn-golden"><i class="fas fa-plus me-1" aria-hidden="true"></i> Novo bloco</a>
        </x-slot:actions>

        <x-admin.table :headers="['Bloco', 'O que diz', 'center:Ordem', 'center:No ar', 'end:Ações']"
            empty="Nenhum bloco ainda: comece pela abertura.">
            @foreach($pagina->blocks as $bloco)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas {{ \App\Models\FunnelBlock::iconeTipo($bloco->tipo) }} text-gold flex-shrink-0" aria-hidden="true"></i>
                            <span>
                                <span class="fw-semibold d-block">{{ \App\Models\FunnelBlock::rotuloTipo($bloco->tipo) }}</span>
                                @if($bloco->imagem)<img src="{{ $bloco->imagemUrl() }}" alt="" class="table-thumb mt-1" loading="lazy">@endif
                            </span>
                        </div>
                    </td>
                    <td>
                        <div class="small fw-semibold text-truncate">{{ $bloco->titulo ?: '— sem título —' }}</div>
                        <div class="small text-secondary text-truncate">{{ $bloco->texto ?: \App\Models\FunnelBlock::descricaoTipo($bloco->tipo) }}</div>
                    </td>
                    <td class="text-center small text-secondary">{{ $bloco->ordem }}</td>
                    <td class="text-center"><x-admin.status :rotulo="$bloco->ativa ? 'sim' : 'oculto'" :tom="tom_por_flag($bloco->ativa)" /></td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('admin.funil.blocos.edit', [$pagina, $bloco]) }}" class="btn btn-sm btn-outline-secondary" title="Editar bloco"><i class="fas fa-pen"></i></a>
                            <x-admin.delete :action="route('admin.funil.blocos.destroy', [$pagina, $bloco])" :confirm="'Remover o bloco '.\App\Models\FunnelBlock::rotuloTipo($bloco->tipo).' desta página?'" />
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.card>
@endif
@endsection
