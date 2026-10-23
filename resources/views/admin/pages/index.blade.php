@extends('admin.layouts.app')
@section('title', 'Páginas')
@section('page_title', 'Páginas do site (CMS)')
@section('page_description', 'Crie e edite páginas institucionais. As marcadas para o menu aparecem na navegação do site.')

@section('page_actions')
<a href="{{ route('admin.conteudo.paginas.create') }}" class="btn btn-golden btn-sm"><i class="fas fa-plus me-1"></i> Nova página</a>
@endsection

@section('content')
<div class="card shadow-sm" style="border-radius:16px"><div class="card-body p-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr class="text-secondary small"><th>Página</th><th>Slug</th><th class="text-center">Menu</th><th class="text-center">Status</th><th class="text-center">Atualizada</th><th class="text-end">Ações</th></tr></thead>
        <tbody>
        @forelse($paginas as $pg)
            <tr>
                <td><span class="fw-semibold">{{ $pg->titulo }}</span>@if($pg->intro)<div class="small text-secondary">{{ Str::limit($pg->intro, 60) }}</div>@endif</td>
                <td><code class="small">/p/{{ $pg->slug }}</code></td>
                <td class="text-center"><span class="badge {{ $pg->exibir_menu ? 'bg-info text-dark':'bg-secondary' }}">{{ $pg->exibir_menu ? 'no menu':'oculta' }}</span></td>
                <td class="text-center"><span class="badge-status {{ $pg->publicada ? 'st-vendido':'st-perdido' }}">{{ $pg->publicada ? 'publicada':'rascunho' }}</span></td>
                <td class="text-center small text-secondary">{{ $pg->updated_at->format('d/m/Y') }}</td>
                <td class="text-end">
                    <a href="{{ route('site.pagina',$pg->slug) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver"><i class="fas fa-external-link-alt"></i></a>
                    <a href="{{ route('admin.conteudo.paginas.edit',$pg) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    <form method="POST" action="{{ route('admin.conteudo.paginas.destroy',$pg) }}" class="d-inline" onsubmit="return confirm('Excluir página?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-secondary py-5">Nenhuma página criada. <a href="{{ route('admin.conteudo.paginas.create') }}">Criar a primeira</a>.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div></div>
@endsection
