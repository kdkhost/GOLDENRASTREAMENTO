@extends('admin.layouts.app')
@section('title', 'Modo de Manutenção')
@section('page_title', 'Modo de manutenção do sistema')

@section('page_actions')
    <a href="{{ route('admin.erros.preview', 503) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-eye me-1"></i> Prévia da Tela 503
    </a>
    <a href="{{ route('site.home') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-globe me-1"></i> Ver o site
    </a>
@endsection

@section('content')
{{-- Status Banner Prominente --}}
<div class="card mb-4 shadow-sm border-0 {{ $ativa ? 'border-start border-danger border-4 bg-danger-subtle' : 'border-start border-success border-4 bg-success-subtle' }}">
    <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-grid place-items-center" style="width: 52px; height: 52px; background: {{ $ativa ? 'rgba(239, 68, 68, 0.2)' : 'rgba(34, 197, 94, 0.2)' }};">
                <i class="fas {{ $ativa ? 'fa-triangle-exclamation text-danger' : 'fa-circle-check text-success' }} fa-xl"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1 {{ $ativa ? 'text-danger' : 'text-success' }}">
                    {{ $ativa ? 'MODO DE MANUTENÇÃO ATIVADO' : 'SISTEMA OPERANDO NORMALMENTE' }}
                </h4>
                <p class="mb-0 text-secondary small">
                    {{ $ativa ? 'O site está temporariamente fechado ao público com a tela personalizada de manutenção (HTTP 503).' : 'O site público e todas as páginas estão acessíveis para visitantes e clientes.' }}
                </p>
            </div>
        </div>
        <div>
            <form method="POST" action="{{ route('admin.manutencao.toggle') }}"
                onsubmit="return confirm('{{ $ativa ? 'Deseja realmente desativar o modo de manutenção e liberar o site ao público?' : 'Deseja ativar o modo de manutenção agora? O site público passará a exibir a tela 503.' }}');">
                @csrf
                <button type="submit" class="btn {{ $ativa ? 'btn-success' : 'btn-danger' }} fw-bold px-4 py-2">
                    <i class="fas {{ $ativa ? 'fa-play' : 'fa-power-off' }} me-2"></i>
                    {{ $ativa ? 'Desativar Manutenção e Abrir Site' : 'Ativar Modo de Manutenção Agora' }}
                </button>
            </form>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <x-admin.card title="Personalização da Página de Manutenção" icon="fa-screwdriver-wrench">
            <form method="POST" action="{{ route('admin.manutencao.update') }}">
                @csrf
                @method('PUT')

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label for="campo-modo" class="form-label fw-bold">Escopo do Bloqueio</label>
                        <select id="campo-modo" name="modo" class="form-select">
                            <option value="total" {{ $modo === 'total' ? 'selected' : '' }}>Bloqueio Total (Site público e Portal do Cliente)</option>
                            <option value="publico_apenas" {{ $modo === 'publico_apenas' ? 'selected' : '' }}>Apenas Site Público (Portal do Cliente liberado)</option>
                        </select>
                        <div class="form-hint">O painel administrativo (/admin) permanece sempre acessível aos administradores.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="campo-previsao_fim" class="form-label fw-bold">Previsão de Término</label>
                        <input type="datetime-local" id="campo-previsao_fim" name="previsao_fim" class="form-control"
                            value="{{ old('previsao_fim', $previsaoFim) }}">
                        <div class="form-hint">Alimenta o cronômetro regressivo da tela de manutenção.</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="campo-titulo" class="form-label fw-bold">Título da Tela <span class="text-danger">*</span></label>
                    <input type="text" id="campo-titulo" name="titulo" class="form-control"
                        value="{{ old('titulo', $titulo) }}" required maxlength="160">
                </div>

                <div class="mb-3">
                    <label for="campo-subtitulo" class="form-label fw-bold">Subtítulo Dourado</label>
                    <input type="text" id="campo-subtitulo" name="subtitulo" class="form-control"
                        value="{{ old('subtitulo', $subtitulo) }}" maxlength="255">
                </div>

                <div class="mb-3">
                    <label for="campo-mensagem" class="form-label fw-bold">Mensagem aos Clientes e Visitantes</label>
                    <textarea id="campo-mensagem" name="mensagem" rows="3" class="form-control"
                        maxlength="2000">{{ old('mensagem', $mensagem) }}</textarea>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="switch-cronometro" name="exibir_cronometro" value="1"
                                {{ old('exibir_cronometro', $exibirCronometro) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="switch-cronometro">Exibir Cronômetro Regressivo</label>
                        </div>
                        <div class="form-hint">Mostra relógio ao vivo com contagem de horas, minutos e segundos.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="switch-plantao" name="exibir_plantao" value="1"
                                {{ old('exibir_plantao', $exibirPlantao) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="switch-plantao">Exibir Canais de Plantão</label>
                        </div>
                        <div class="form-hint">Mostra telefone e e-mail de emergência na tela.</div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label for="campo-telefone" class="form-label fw-bold">Telefone de Plantão</label>
                        <input type="text" id="campo-telefone" name="telefone" class="form-control"
                            value="{{ old('telefone', $telefone) }}" maxlength="60">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="campo-email" class="form-label fw-bold">E-mail de Suporte</label>
                        <input type="email" id="campo-email" name="email" class="form-control"
                            value="{{ old('email', $email) }}" maxlength="120">
                    </div>
                </div>

                <hr class="my-4">

                <div class="mb-4">
                    <label for="campo-ips_permitidos" class="form-label fw-bold">IPs com Acesso Liberado (Whitelist de Manutenção)</label>
                    <textarea id="campo-ips_permitidos" name="ips_permitidos" rows="3" class="form-control font-monospace"
                        placeholder="Exemplo:&#10;187.54.120.30&#10;201.89.44.12">{{ old('ips_permitidos', $ipsPermitidos) }}</textarea>
                    <div class="form-hint">
                        Digite um endereço IP por linha. Esses endereços nunca verão a tela de manutenção.
                        <span class="d-block mt-1">Seu IP atual detectado: <strong>{{ $meuIp }}</strong></span>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <button type="submit" class="btn btn-golden">
                        <i class="fas fa-check me-1"></i> Salvar Diretrizes de Manutenção
                    </button>
                    <a href="{{ route('admin.erros.preview', 503) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                        <i class="fas fa-eye me-1"></i> Pré-visualizar Tela 503
                    </a>
                </div>
            </form>
        </x-admin.card>
    </div>

    <div class="col-12 col-lg-4">
        {{-- Card de Chave de Bypass --}}
        <x-admin.card title="Acesso Secreto por Bypass" icon="fa-key">
            <p class="small text-secondary mb-3">
                Diretores e técnicos podem navegar pelo site normalmente durante a manutenção compartilhando o link com a chave secreta. O navegador grava um cookie temporário de 7 dias.
            </p>

            <div class="mb-3">
                <label class="form-label small fw-bold text-uppercase text-secondary">Link com Chave Secreta:</label>
                <div class="input-group">
                    <input type="text" id="input-bypass-url" class="form-control form-control-sm font-monospace"
                        value="{{ $urlBypass }}" readonly>
                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('input-bypass-url').value); alert('Link de bypass copiado para a área de transferência!');">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.manutencao.gerar-token') }}"
                onsubmit="return confirm('Deseja gerar uma nova chave secreta? O link anterior deixará de funcionar imediatamente.');">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="fas fa-arrows-rotate me-1"></i> Gerar Nova Chave Secreta
                </button>
            </form>

            <hr class="my-3">

            <h6 class="fw-bold small text-uppercase text-secondary mb-2">Segurança Garantida</h6>
            <p class="small text-muted mb-0">
                A tela 503 envia o cabeçalho HTTP <code>Retry-After</code> para os motores de busca (Google, Bing). Isso instrui os robôs a voltarem mais tarde sem penalizar o SEO ou desindexar suas páginas.
            </p>
        </x-admin.card>
    </div>
</div>
@endsection

