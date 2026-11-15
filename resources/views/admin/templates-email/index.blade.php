@extends('admin.layouts.app')
@section('title', 'Templates de E-mail')
@section('page_title', 'Templates de e-mail & layout responsivo')

@section('page_actions')
    <a href="{{ route('admin.templates-email.layout') }}" class="btn btn-sm btn-golden">
        <i class="fas fa-palette" aria-hidden="true"></i> Configurar Layout Separado
    </a>
    <a href="{{ route('admin.templates-email.fila') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-paper-plane" aria-hidden="true"></i> Fila Anti-Spam ({{ $estatisticasFila['pendentes'] }})
    </a>
@endsection

@section('content')
{{-- Faixa KPI --}}
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
    <div class="col">
        <x-admin.stat label="Templates Ativos" :value="$templates->where('ativo', true)->count() . ' / ' . $templates->count()"
            icon="fa-envelope-open-text" tone="gold" meta="Rotas dedicadas por chave" />
    </div>
    <div class="col">
        <x-admin.stat label="Na Fila Anti-Spam" :value="(string) $estatisticasFila['pendentes']"
            icon="fa-clock" tone="amber" :meta="$estatisticasFila['proximo_envio'] ? 'Próx: ' . $estatisticasFila['proximo_envio'] : 'Sem pendências'" />
    </div>
    <div class="col">
        <x-admin.stat label="Enviados Hoje" :value="(string) $estatisticasFila['enviados_hoje']"
            icon="fa-circle-check" tone="green" :meta="$estatisticasFila['enviados_hora'] . ' na última hora'" />
    </div>
    <div class="col">
        <x-admin.stat label="Layout Global" :value="$layout['marca_nome'] ?? 'GOLDEN'"
            icon="fa-mobile-screen" tone="teal" :meta="'Largura ' . ($layout['largura_maxima_px'] ?? 580) . 'px · Responsivo'" />
    </div>
</div>

{{-- Destaque Layout Separado & Fila --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-7">
        <x-admin.card title="Arquitetura Separada: Layout vs. Corpo" icon="fa-layer-group" class="h-100">
            <p class="small text-secondary mb-3">
                O cabeçalho, cores responsivas (Desktop/Mobile), logomarca, botões e rodapé ficam no
                <strong>Layout Global</strong>, enquanto cada template edita apenas o assunto, pré-cabeçalho e corpo transacional com preview em tempo real durante a digitação.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.templates-email.layout') }}" class="btn btn-sm btn-golden">
                    <i class="fas fa-sliders" aria-hidden="true"></i> Abrir Studio do Layout Global
                </a>
                <a href="{{ route('admin.faturas-modelo.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Personalizar Faturas PDF A4
                </a>
            </div>
        </x-admin.card>
    </div>

    <div class="col-12 col-lg-5">
        <x-admin.card title="Filtro por Categoria" icon="fa-filter" class="h-100">
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.templates-email.index') }}"
                    class="btn btn-sm {{ $categoriaAtual === '' ? 'btn-golden' : 'btn-outline-secondary' }}">
                    Todos
                </a>
                @foreach($categorias as $slug => $rotulo)
                    <a href="{{ route('admin.templates-email.index', ['categoria' => $slug]) }}"
                        class="btn btn-sm {{ $categoriaAtual === $slug ? 'btn-golden' : 'btn-outline-secondary' }}">
                        {{ $rotulo }}
                    </a>
                @endforeach
            </div>
        </x-admin.card>
    </div>
</div>

{{-- Catálogo de Templates com Rotas Próprias --}}
<x-admin.card title="Templates Personalizáveis ({{ $templates->count() }})" icon="fa-envelopes-bulk"
    descricao="Cada template possui rota dedicada no painel para edição do HTML, variáveis dinâmicas e preview responsivo ao vivo.">
    <x-admin.table>
        <x-slot:head>
            <tr>
                <th>Template / Rota Dedicada</th>
                <th>Categoria</th>
                <th>Assunto Atual</th>
                <th class="text-center">Usa Layout</th>
                <th class="text-center">Status</th>
                <th class="text-end">Ações</th>
            </tr>
        </x-slot:head>
        @forelse($templates as $tpl)
            <tr>
                <td>
                    <div class="fw-semibold">{{ $tpl->nome }}</div>
                    <div class="small text-secondary">{{ $tpl->descricao }}</div>
                    <code class="small text-gold">{{ route('admin.templates-email.edit', $tpl->chave, false) }}</code>
                </td>
                <td>
                    <span class="badge-status tone-neutral text-capitalize">{{ $tpl->categoria }}</span>
                </td>
                <td class="small">{{ $tpl->assunto }}</td>
                <td class="text-center">
                    <x-admin.status :rotulo="$tpl->usar_layout ? 'Separado' : 'HTML Livre'" :tom="$tpl->usar_layout ? 'teal' : 'neutral'" />
                </td>
                <td class="text-center">
                    <x-admin.status :rotulo="$tpl->ativo ? 'Ativo' : 'Pausado'" :tom="$tpl->ativo ? 'green' : 'amber'" />
                </td>
                <td class="text-end">
                    <a href="{{ route('admin.templates-email.edit', $tpl->chave) }}" class="btn btn-sm btn-golden">
                        <i class="fas fa-pen" aria-hidden="true"></i> Editar &amp; Preview Ao Vivo
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-secondary py-4">Nenhum template encontrado nesta categoria.</td>
            </tr>
        @endforelse
    </x-admin.table>
</x-admin.card>
@endsection
