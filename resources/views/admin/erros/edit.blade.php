@extends('admin.layouts.app')
@section('title', "Editar Erro HTTP {$code}")
@section('page_title', "Editar página do erro HTTP {$code}")

@section('page_actions')
    <a href="{{ route('admin.erros.preview', $code) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-eye me-1"></i> Ver Prévia no Navegador
    </a>
    <a href="{{ route('admin.erros.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Voltar à lista
    </a>
@endsection

@section('content')
<div class="row g-4 justify-content-center">
    <div class="col-12 col-lg-8">
        <x-admin.card title="Conteúdo da Página de Erro {{ $code }}" icon="fa-pen-to-square">
            <p class="form-hint mb-4">
                <strong>{{ $nomeCodigo }}</strong>: customize todos os elementos que compõem esta tela para manter a voz da marca e orientar o visitante com clareza.
            </p>

            <form method="POST" action="{{ route('admin.erros.update', $code) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="campo-title" class="form-label fw-bold">Título Principal <span class="text-danger">*</span></label>
                    <input type="text" id="campo-title" name="title" class="form-control @error('title') is-invalid @enderror"
                        value="{{ old('title', $registro->title ?? $dados['title']) }}" required maxlength="160">
                    <div class="form-hint">Aparece em destaque no cabeçalho da página de erro.</div>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="campo-subtitle" class="form-label fw-bold">Subtítulo Dourado</label>
                    <input type="text" id="campo-subtitle" name="subtitle" class="form-control @error('subtitle') is-invalid @enderror"
                        value="{{ old('subtitle', $registro->subtitle ?? $dados['subtitle']) }}" maxlength="255">
                    <div class="form-hint">Frase de síntese destacada no tom dourado da Golden.</div>
                    @error('subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="campo-message" class="form-label fw-bold">Mensagem Explicativa Detalhada</label>
                    <textarea id="campo-message" name="message" rows="4" class="form-control @error('message') is-invalid @enderror"
                        maxlength="2000">{{ old('message', $registro->message ?? $dados['message']) }}</textarea>
                    <div class="form-hint">Explicação amigável do motivo do erro e o que a pessoa pode fazer.</div>
                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label for="campo-primary_button_text" class="form-label fw-bold">Texto do Botão Principal</label>
                        <input type="text" id="campo-primary_button_text" name="primary_button_text" class="form-control"
                            value="{{ old('primary_button_text', $registro->primary_button_text ?? $dados['primary_button_text']) }}" maxlength="80">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="campo-primary_button_url" class="form-label fw-bold">Link do Botão Principal</label>
                        <input type="text" id="campo-primary_button_url" name="primary_button_url" class="form-control"
                            value="{{ old('primary_button_url', $registro->primary_button_url ?? $dados['primary_button_url']) }}" maxlength="255">
                        <div class="form-hint">Ex: <code>/</code>, <code>/admin/login</code> ou <code>javascript:location.reload()</code></div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <x-admin.icon-picker name="icon" label="Ícone Central da Página de Erro"
                        :value="old('icon', $registro->icon ?? $dados['icon'])" col="col-12 col-md-6" required
                        hint="Selecione visualmente pelo ícone. Nunca é necessário digitar o código." />

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold d-block">Status da Personalização</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="switch-active" name="active" value="1"
                                {{ old('active', $registro->active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="switch-active">Aplicar estes textos personalizados no site</label>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="fw-bold mb-3"><i class="fab fa-whatsapp text-success me-2"></i> Botão de Suporte pelo WhatsApp</h5>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="switch-show-whatsapp" name="show_whatsapp_button" value="1"
                            {{ old('show_whatsapp_button', $registro->show_whatsapp_button ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="switch-show-whatsapp">Exibir botão verde do WhatsApp nesta página de erro</label>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="campo-whatsapp_custom_text" class="form-label fw-bold">Mensagem Pré-digitada do WhatsApp</label>
                    <input type="text" id="campo-whatsapp_custom_text" name="whatsapp_custom_text" class="form-control"
                        value="{{ old('whatsapp_custom_text', $registro->whatsapp_custom_text ?? $dados['whatsapp_custom_text']) }}" maxlength="120">
                    <div class="form-hint">Texto que o cliente já encontra digitado ao abrir a conversa no WhatsApp de suporte.</div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-2">
                    <button type="submit" class="btn btn-golden">
                        <i class="fas fa-check me-1"></i> Salvar Alterações
                    </button>
                    <a href="{{ route('admin.erros.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </x-admin.card>
    </div>

    <div class="col-12 col-lg-4">
        <x-admin.card title="Ações e Padrões" icon="fa-sliders">
            <p class="small text-secondary mb-3">
                Quer desfazer todas as alterações e retornar aos textos originais empacotados pela Golden Rastreamento?
            </p>
            <form method="POST" action="{{ route('admin.erros.restaurar', $code) }}"
                onsubmit="return confirm('Tem certeza que deseja restaurar os textos padrão de fábrica desta página de erro?');">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                    <i class="fas fa-rotate-left me-1"></i> Restaurar Padrão de Fábrica
                </button>
            </form>

            <hr class="my-3">

            <h6 class="fw-bold small text-uppercase text-secondary mb-2">Dica de Experiência do Usuário</h6>
            <p class="small text-muted mb-0">
                Páginas de erro claras reduzem a taxa de rejeição. Manter o botão do WhatsApp ativo permite transformar visitantes com links quebrados em leads reais atendidos pelo seu time comercial.
            </p>
        </x-admin.card>
    </div>
</div>
@endsection

