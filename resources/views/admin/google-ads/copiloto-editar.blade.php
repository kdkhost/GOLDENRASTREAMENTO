@extends('admin.layouts.app')
@section('title', 'Editar o plano: '.$plano->nome)
@section('page_title', 'Editar o plano')
@section('page_description', 'Corrija o que o copiloto escreveu — orçamento, regiões, grupos, palavras-chave e anúncio. Salvar devolve o plano para rascunho: ele precisa da aprovação de novo antes de ir ao Google.')

@section('page_actions')
<a href="{{ route('admin.google-ads.copiloto.show', $plano) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Voltar à ficha</a>
<a href="{{ route('admin.google-ads.copiloto.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-list-check me-1" aria-hidden="true"></i> Todos os planos</a>
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('css/golden-ads.css') }}?v={{ filemtime(public_path('css/golden-ads.css')) }}">
@endsection

@section('content')
@php
    // A lista digitada tem de voltar do jeito que saiu: `old()` manda nas linhas dos grupos e o
    // campo busca o valor dele dentro de cada linha. Sem isso, um erro de digitação apagaria o
    // grupo que a pessoa acabou de adicionar.
    $grupos = array_values((array) old('grupos', $valores['grupos']));
    $maxGrupos = (int) config('google_ads.copiloto.grupos_max', 8);
    $titulosMax = (int) config('google_ads.rsa.titulos_max', 15);
    $descricoesMax = (int) config('google_ads.rsa.descricoes_max', 4);
@endphp

<form method="POST" action="{{ route('admin.google-ads.copiloto.atualizar', $plano) }}">
    @csrf

    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="A campanha" icon="fa-bullhorn"
                descricao="O que o Google recebe como ajuste da campanha inteira.">
                <div class="row g-3">
                    <x-admin.field name="nome" label="Nome da campanha" :value="$valores['nome']" :required="true" maxlength="120" col="col-12" />

                    <x-admin.field name="objetivo" type="select" label="Objetivo" :options="$objetivos" :value="$valores['objetivo']"
                        :required="true" col="col-12 col-md-6" hint="O que a campanha precisa entregar." />

                    <x-admin.field name="orcamento" type="number" label="Orçamento diário (R$)" :value="$valores['orcamento']"
                        :required="true" step="0.01" min="1" max="5000" col="col-12 col-md-6"
                        hint="Quanto pode ser gasto por dia, no máximo." />

                    <x-admin.field name="cpc_maximo" type="number" label="Teto de lance por clique (R$)" :value="$valores['cpc_maximo']"
                        step="0.01" min="0.10" max="50" col="col-12 col-md-6" :opcional="true"
                        hint="O maior valor que uma palavra pode receber por clique. A campanha é em CPC manual." />

                    <x-admin.field name="url" type="url" label="Página de destino" :value="$valores['url']" :required="true" maxlength="255" col="col-12"
                        hint="É a página que o anúncio abre. Só o que existe no site." />

                    <x-admin.field name="regioes" type="textarea" label="Regiões" :value="$valores['regioes']" :required="true" :rows="3" maxlength="255" col="col-12"
                        hint="Uma região por linha. Vale cidade, estado ou país — o Google resolve o resto." />
                </div>
            </x-admin.card>

            <x-admin.card title="O anúncio responsivo" icon="fa-rectangle-ad" class="mt-3"
                descricao="Título e descrição que a busca do Google combina entre si. O que passar do limite do Google é deixado fora, e a tela avisa.">
                <div class="row g-3">
                    <x-admin.field name="titulos" type="textarea" label="Títulos" :value="$valores['titulos']" :rows="8" :required="true" maxlength="600" col="col-12 col-lg-6"
                        hint="Um por linha, até {{ $titulosMax }} de {{ config('google_ads.rsa.caracteres_titulo') }} caracteres. São preciso pelo menos três." />

                    <x-admin.field name="descricoes" type="textarea" label="Descrições" :value="$valores['descricoes']" :rows="8" :required="true" maxlength="500" col="col-12 col-lg-6"
                        hint="Uma por linha, até {{ $descricoesMax }} de {{ config('google_ads.rsa.caracteres_descricao') }} caracteres. São preciso pelo menos duas." />

                    <x-admin.field name="caminhos" type="textarea" label="Rotas de exibição" :value="$valores['caminhos']" :rows="2" maxlength="60" col="col-12" :opcional="true"
                        placeholder="frota&#10;24h"
                        hint="O texto que aparece depois do endereço no anúncio. No máximo duas, de {{ config('google_ads.rsa.caracteres_rota') }} caracteres." />
                </div>
            </x-admin.card>

            <x-admin.card title="Grupos de anúncios" icon="fa-layer-group" class="mt-3"
                descricao="Cada grupo reúne as palavras que significam a mesma coisa e as negativas que barram o que não serve.">
                <div class="row g-3" data-grupos data-max="{{ $maxGrupos }}">
                    @foreach ($grupos as $indice => $grupo)
                        @include('admin.google-ads.copiloto-editar-grupo', ['grupo' => $grupo, 'indice' => $indice])
                    @endforeach
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-adiciona-grupo>
                        <i class="fas fa-plus me-1" aria-hidden="true"></i> Adicionar grupo
                    </button>
                    <span class="form-hint">Até {{ $maxGrupos }} grupos por plano, com {{ config('google_ads.copiloto.palavras_por_grupo_max') }} palavras em cada um.</span>
                </div>

                {{-- O modelo do grupo novo fica oculto no próprio documento: o JS copia e troca o
                     índice dos campos, e a tela continua desenhada em Blade, não em string de JS. --}}
                <template data-grupo-modelo>
                    @include('admin.google-ads.copiloto-editar-grupo', ['grupo' => [], 'indice' => 'MODELO'])
                </template>
            </x-admin.card>

            <x-admin.card title="Por que este plano" icon="fa-comment-dots" class="mt-3"
                descricao="A explicação que quem aprova lê antes de assinar embaixo.">
                <x-admin.field name="justificativa" type="textarea" label="Justificativa do plano" :value="$valores['justificativa']" :rows="4"
                    maxlength="2000" col="col-12" :opcional="true" />
            </x-admin.card>

            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <button class="btn btn-golden">
                    <i class="fas fa-save me-1" aria-hidden="true"></i> Salvar o plano
                </button>
                <a href="{{ route('admin.google-ads.copiloto.show', $plano) }}" class="btn btn-outline-secondary">Cancelar</a>
                <span class="form-hint">Salvar não toca no Google Ads: o plano volta a rascunho, aqui no painel.</span>
            </div>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="O que muda ao salvar" icon="fa-shield-halved"
                descricao="A trava da casa continua de pé: nada é publicado sem aprovação.">
                <ul class="small text-secondary ps-3 mb-0">
                    <li>O plano volta a <strong>rascunho</strong>, mesmo se já estava aprovado.</li>
                    <li>Um administrador precisa aprovar de novo antes de aplicar.</li>
                    <li>O briefing original continua guardado, como foi escrito — o que muda é só o plano.</li>
                    <li>Nada é escrito no Google Ads por esta tela.</li>
                </ul>
            </x-admin.card>

            @if ($problemas !== [])
                <x-admin.card title="O que a conferência achou" icon="fa-clipboard-check" class="mt-3"
                    descricao="A conferência roda em cada salvamento. O vermelho impede aprovar e aplicar.">
                    <div class="ads-aviso ps-3">
                        <ul class="mb-0 ps-3 small">
                            @foreach ($problemas as $problema)
                                <li>{{ $problema }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <p class="form-hint mt-2 mb-0">Corrija aqui e salve de novo — a lista é refita a cada gravação.</p>
                </x-admin.card>
            @endif
        </div>
    </div>
</form>
@endsection

@section('scripts')
<script src="{{ asset('js/golden-grupos.js') }}?v={{ filemtime(public_path('js/golden-grupos.js')) }}"></script>
@endsection
