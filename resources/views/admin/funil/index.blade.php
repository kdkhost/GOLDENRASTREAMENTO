@extends('admin.layouts.app')
@section('title', 'Marketing (funil)')
@section('page_title', 'Marketing (funil)')
@section('page_description', 'Campanhas de divulgação montadas por blocos: quem não tem o link, não vê.')

@section('page_actions')
<a href="{{ route('admin.funil.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1" aria-hidden="true"></i> Nova página de funil</a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <x-admin.stat label="Visitas no período" :value="number_format($totais['no_periodo'], 0, ',', '.')"
            icon="fa-person-walking" tone="blue" pequeno :hint="'Desde sempre: '.number_format($totais['visitas'], 0, ',', '.')" />
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.stat label="Cadastros no período" :value="number_format($totais['leads_periodo'], 0, ',', '.')"
            icon="fa-user-plus" tone="gold" pequeno :hint="'Desde sempre: '.number_format($totais['leads'], 0, ',', '.')" />
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.stat label="Páginas montadas" :value="$paginas->count()" icon="fa-bullseye" tone="teal" pequeno
            hint="Cada uma com seu endereço, sua pele e seus pixels." />
    </div>
    <div class="col-12 col-lg-3">
        <x-admin.card title="Como divulgar" icon="fa-link">
            <p class="small text-secondary mb-0">O endereço <code>/c/</code> não entra no menu, no rodapé nem na busca.
                Cole-o na URL final do anúncio do Google, do Meta ou do TikTok — a plataforma acrescenta o
                identificador de clique e a página registra de onde veio cada cadastro.</p>
        </x-admin.card>
    </div>
</div>

<x-admin.filters :reset="route('admin.funil.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-4" placeholder="Nome, endereço ou título da campanha..." />
    <x-admin.filter name="status" type="select" label="Situação" col="col-6 col-md-3" :options="\App\Models\FunnelPage::statusRotulos()" empty="Todas" />
    <x-admin.filter name="inicio" type="date" label="De" col="col-6 col-md-2" :value="$periodo[0]" />
    <x-admin.filter name="fim" type="date" label="Até" col="col-6 col-md-2" :value="$periodo[1]" />
</x-admin.filters>

<x-admin.table :headers="['Campanha', 'center:Situação', 'center:Visitas', 'center:Telas', 'center:Cliques', 'center:Cadastros', 'end:Conversão', 'end:Ações']"
    empty="Nenhuma página de funil com esse filtro.">
    @foreach($paginas as $pagina)
        @php
            $m = $medicoes[$pagina->id] ?? ['visitas' => 0, 'views' => 0, 'cliques' => 0, 'leads' => 0];
            $taxa = $m['visitas'] > 0 ? round($m['leads'] / $m['visitas'] * 100, 1) : 0.0;
        @endphp
        <tr>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <span class="table-thumb"><i class="fas fa-bullseye" aria-hidden="true"></i></span>
                    <span class="overflow-hidden">
                        <a href="{{ route('admin.funil.show', $pagina) }}" class="fw-semibold d-block text-truncate">{{ $pagina->nome }}</a>
                        <div class="small text-secondary text-truncate">{{ $pagina->caminho() }}</div>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <span class="badge tone-neutral">{{ $pagina::peleRotulo($pagina->pele) }}</span>
                            <span class="badge tone-neutral">{{ $pagina::visibilidadeRotulo($pagina->visibilidade) }}</span>
                        </div>
                    </span>
                    <input id="link-{{ $pagina->id }}" type="text" class="visually-hidden" value="{{ $pagina->url() }}" tabindex="-1" aria-hidden="true">
                    <button class="btn btn-sm btn-outline-secondary flex-shrink-0" type="button" data-copiar="#link-{{ $pagina->id }}" title="Copiar o link da campanha">
                        <i class="fas fa-copy" aria-hidden="true"></i>
                    </button>
                </div>
            </td>
            <td class="text-center"><x-admin.status :rotulo="$pagina->statusRotulo()" :tom="$pagina->tomStatus()" /></td>
            <td class="text-center">{{ number_format($m['visitas'], 0, ',', '.') }}</td>
            <td class="text-center text-secondary">{{ number_format($m['views'], 0, ',', '.') }}</td>
            <td class="text-center text-secondary">{{ number_format($m['cliques'], 0, ',', '.') }}</td>
            <td class="text-center">{{ $m['leads'] ? number_format($m['leads'], 0, ',', '.') : '—' }}</td>
            <td class="text-end"><x-admin.status :rotulo="$taxa.'%'" :tom="tom_por_flag($taxa > 0)" /></td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.funil.show', $pagina) }}" class="btn btn-sm btn-outline-secondary" title="Contador da campanha"><i class="fas fa-chart-column"></i></a>
                    <a href="{{ route('admin.funil.edit', $pagina) }}" class="btn btn-sm btn-outline-secondary" title="Editar página e blocos"><i class="fas fa-pen"></i></a>
                    <form method="POST" action="{{ route('admin.funil.toggle', $pagina) }}" class="d-inline" data-atualiza=".app-content"
                        data-confirmar="{{ $pagina->status === 'publicada' ? 'Tirar esta campanha do ar? Quem tem o link vê 404 até ela voltar.' : 'Colocar esta campanha no ar agora?' }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary"
                            title="{{ $pagina->status === 'publicada' ? 'Tirar do ar' : 'Colocar no ar' }}">
                            <i class="fas fa-{{ $pagina->status === 'publicada' ? 'pause' : 'play' }}" aria-hidden="true"></i>
                        </button>
                    </form>
                    @if($pagina->status === 'publicada')
                        <a href="{{ $pagina->url() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Abrir a campanha"><i class="fas fa-up-right-from-square"></i></a>
                    @endif
                    <x-admin.delete :action="route('admin.funil.destroy', $pagina)" :confirm="'Excluir '.$pagina->nome.' e todos os blocos dela? Os cadastros já feitos continuam no painel.'" />
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
