@extends('admin.layouts.app')
@section('title', 'Páginas de Erro Personalizadas')
@section('page_title', 'Páginas de erro personalizadas')

@section('page_actions')
    <a href="{{ route('site.home') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-globe me-1"></i> Ver o site
    </a>
@endsection

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <x-admin.stat label="Páginas catalogadas" value="{{ count($codigos) }}" icon="fa-file-circle-exclamation" tone="gold" pequeno />
    </div>
    <div class="col-12 col-md-4">
        <x-admin.stat label="Páginas ativas" value="{{ $paginas->where('active', true)->count() }}" icon="fa-circle-check" tone="teal" pequeno />
    </div>
    <div class="col-12 col-md-4">
        <x-admin.stat label="Identidade da marca" value="Golden Rastreamento" icon="fa-shield-halved" tone="blue" pequeno hint="Tema escuro de alta legibilidade e botões de suporte integrados." />
    </div>
</div>

<x-admin.card title="Páginas de Erro HTTP" icon="fa-triangle-exclamation">
    <p class="form-hint mb-3">
        Personalize a mensagem, o título, o botão de retorno e a chamada para o WhatsApp de suporte para cada erro HTTP que um visitante ou cliente possa encontrar. Clique em <strong>Editar</strong> para customizar ou em <strong>Pré-visualizar</strong> para conferir o visual real.
    </p>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 110px;">Código</th>
                    <th>Título exibido</th>
                    <th>Subtítulo</th>
                    <th class="text-center" style="width: 130px;">WhatsApp</th>
                    <th class="text-center" style="width: 110px;">Status</th>
                    <th class="text-end" style="width: 220px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($paginas as $p)
                    <tr>
                        <td>
                            <span class="badge" style="font-size: 0.95rem; font-weight: 800; background: rgba(245, 179, 1, 0.15); color: #f5b301; border: 1px solid rgba(245, 179, 1, 0.35); padding: 6px 12px; border-radius: 8px;">
                                HTTP {{ $p->code }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas {{ $p->icon ?: 'fa-triangle-exclamation' }} text-gold"></i>
                                <span class="fw-bold">{{ $p->title }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="text-secondary small">{{ $p->subtitle ?: '—' }}</span>
                        </td>
                        <td class="text-center">
                            @if($p->show_whatsapp_button)
                                <span class="badge text-bg-success"><i class="fab fa-whatsapp me-1"></i> Ativo</span>
                            @else
                                <span class="badge text-bg-secondary">Oculto</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($p->active)
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Personalizada</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border">Padrão</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2">
                                <a href="{{ route('admin.erros.preview', $p->code) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Abrir pré-visualização em nova aba">
                                    <i class="fas fa-eye me-1"></i> Prévia
                                </a>
                                <a href="{{ route('admin.erros.edit', $p->code) }}" class="btn btn-sm btn-golden" title="Editar esta página de erro">
                                    <i class="fas fa-pen-to-square me-1"></i> Editar
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin.card>
@endsection

