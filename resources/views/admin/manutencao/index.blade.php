@extends('admin.layouts.app')
@section('title', 'Modo de Manutenção')
@section('page_title', 'Modo de manutenção do sistema')

@section('page_actions')
    <a href="{{ route('admin.erros.preview', 503) }}" target="_blank" rel="noopener" class="btn btn-sm btn-golden">
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
                onsubmit="return confirm('{{ $ativa ? 'Deseja realmente desativar o modo de manutenção e liberar o site ao público?' : 'Deseja ativar o modo de manutenção agora? O site público passará a exibir a tela personalizada 503.' }}');">
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
        <form method="POST" action="{{ route('admin.manutencao.update') }}">
            @csrf
            @method('PUT')

            {{-- 1. Textos e Escopo da Manutenção --}}
            <x-admin.card title="Conteúdo & Mensagens da Manutenção" icon="fa-pen-to-square" class="mb-4">
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
                    <label for="campo-titulo" class="form-label fw-bold">Título Principal <span class="text-danger">*</span></label>
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

                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="switch-cronometro" name="exibir_cronometro" value="1"
                        {{ old('exibir_cronometro', $exibirCronometro) ? 'checked' : '' }}>
                    <label class="form-check-label fw-bold" for="switch-cronometro">Exibir Contador Regressivo ao Vivo</label>
                    <div class="form-hint">Apresenta mostrador digital com dias, horas, minutos e segundos até a reabertura dos serviços.</div>
                </div>
            </x-admin.card>

            {{-- 2. Animação de Fundo Interativa (Carros em Circulação & Satélite de Telemetria) --}}
            <x-admin.card title="Plano de Fundo Animado (Tráfego de Carros & Satélites)" icon="fa-satellite" class="mb-4">
                <div class="alert alert-dark border-0 p-3 mb-3 d-flex align-items-center gap-3" style="background: rgba(245, 179, 1, 0.06); border-left: 3px solid #f5b301 !important;">
                    <i class="fas fa-radar text-warning fa-2x"></i>
                    <div class="small">
                        <strong>Simulação de Telemetria Satelital em Tempo Real:</strong> Renderizada em Canvas de alta performance na paleta oficial Golden (Dark Void, Ouro e Verde Satelital), com veículos trafegando nas avenidas e pulsos de satélite transmitindo dados.
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="switch-animacao" name="exibir_animacao" value="1"
                                {{ old('exibir_animacao', $exibirAnimacao) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="switch-animacao">Ativar Fundo Animado com Carros</label>
                            <div class="form-hint">Carros com faróis e lanternas circulando pela malha viária estilizada.</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="switch-satelite" name="exibir_satelite" value="1"
                                {{ old('exibir_satelite', $exibirSatelite) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="switch-satelite">Exibir Satélite em Órbita & Feixes</label>
                            <div class="form-hint">Emite ondas de rádio e feixes de laser conectando aos veículos.</div>
                        </div>
                    </div>
                </div>

                <div class="mb-2">
                    <label for="campo-velocidade_animacao" class="form-label fw-bold">Velocidade da Dinâmica de Tráfego</label>
                    <select id="campo-velocidade_animacao" name="velocidade_animacao" class="form-select">
                        <option value="suave" {{ $velocidadeAnimacao === 'suave' ? 'selected' : '' }}>Suave (Movimento tranquilo e relaxante)</option>
                        <option value="normal" {{ $velocidadeAnimacao === 'normal' ? 'selected' : '' }}>Padrão (Velocidade balanceada a 60 FPS)</option>
                        <option value="rapida" {{ $velocidadeAnimacao === 'rapida' ? 'selected' : '' }}>Dinâmica (Tráfego ágil com alta taxa de telemetria)</option>
                    </select>
                </div>
            </x-admin.card>

            {{-- 3. WhatsApp e Atendimento de Plantão --}}
            <x-admin.card title="Atendimento & WhatsApp de Emergência" icon="fa-headset" class="mb-4">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="switch-whatsapp" name="exibir_whatsapp" value="1"
                                {{ old('exibir_whatsapp', $exibirWhatsapp) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="switch-whatsapp">Exibir Botão de WhatsApp em Destaque</label>
                            <div class="form-hint">Permite aos visitantes abrirem conversa direta de suporte durante a manutenção.</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="switch-plantao" name="exibir_plantao" value="1"
                                {{ old('exibir_plantao', $exibirPlantao) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="switch-plantao">Exibir Canais de Plantão (Tel & E-mail)</label>
                            <div class="form-hint">Mostra telefonia 24h e endereço de e-mail técnico.</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label for="campo-whatsapp_numero" class="form-label fw-bold">Número de WhatsApp</label>
                        <input type="text" id="campo-whatsapp_numero" name="whatsapp_numero" class="form-control"
                            placeholder="Ex: 5519997101483" value="{{ old('whatsapp_numero', $whatsappNumero) }}" maxlength="32">
                        <div class="form-hint">Somente números com DDI + DDD (ex: 5519997101483). Deixe em branco para usar o número padrão do site.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="campo-whatsapp_mensagem" class="form-label fw-bold">Mensagem Pré-formatada do WhatsApp</label>
                        <input type="text" id="campo-whatsapp_mensagem" name="whatsapp_mensagem" class="form-control"
                            value="{{ old('whatsapp_mensagem', $whatsappMensagem) }}" maxlength="255">
                        <div class="form-hint">Texto que o cliente verá pré-preenchido ao clicar no WhatsApp.</div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="campo-telefone" class="form-label fw-bold">Telefone 24h / Plantão</label>
                        <input type="text" id="campo-telefone" name="telefone" class="form-control"
                            value="{{ old('telefone', $telefone) }}" maxlength="60">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="campo-email" class="form-label fw-bold">E-mail de Suporte Técnico</label>
                        <input type="email" id="campo-email" name="email" class="form-control"
                            value="{{ old('email', $email) }}" maxlength="120">
                    </div>
                </div>
            </x-admin.card>

            {{-- 4. Redes Sociais Oficiais --}}
            <x-admin.card title="Redes Sociais Oficiais" icon="fa-share-nodes" class="mb-4">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="switch-redes" name="exibir_redes" value="1"
                        {{ old('exibir_redes', $exibirRedes) ? 'checked' : '' }}>
                    <label class="form-check-label fw-bold" for="switch-redes">Exibir Links das Redes Sociais no Rodapé</label>
                    <div class="form-hint">Apresenta os perfis oficiais da Golden Rastreamento para manter o público conectado.</div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="campo-instagram" class="form-label fw-bold"><i class="fab fa-instagram text-danger me-1"></i> Instagram</label>
                        <input type="url" id="campo-instagram" name="instagram" class="form-control"
                            placeholder="https://instagram.com/goldenrastreamento" value="{{ old('instagram', $instagram) }}" maxlength="255">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="campo-facebook" class="form-label fw-bold"><i class="fab fa-facebook text-primary me-1"></i> Facebook</label>
                        <input type="url" id="campo-facebook" name="facebook" class="form-control"
                            placeholder="https://facebook.com/goldenrastreamento" value="{{ old('facebook', $facebook) }}" maxlength="255">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="campo-youtube" class="form-label fw-bold"><i class="fab fa-youtube text-danger me-1"></i> YouTube</label>
                        <input type="url" id="campo-youtube" name="youtube" class="form-control"
                            placeholder="https://youtube.com/@goldenrastreamento" value="{{ old('youtube', $youtube) }}" maxlength="255">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="campo-linkedin" class="form-label fw-bold"><i class="fab fa-linkedin text-info me-1"></i> LinkedIn</label>
                        <input type="url" id="campo-linkedin" name="linkedin" class="form-control"
                            placeholder="https://linkedin.com/company/goldenrastreamento" value="{{ old('linkedin', $linkedin) }}" maxlength="255">
                    </div>
                </div>
            </x-admin.card>

            {{-- 5. Whitelist de IPs --}}
            <x-admin.card title="Acesso Liberado por IP (Whitelist)" icon="fa-shield-halved" class="mb-4">
                <div class="mb-3">
                    <label for="campo-ips_permitidos" class="form-label fw-bold">Endereços IP Liberados</label>
                    <textarea id="campo-ips_permitidos" name="ips_permitidos" rows="3" class="form-control font-monospace"
                        placeholder="Exemplo:&#10;187.54.120.30&#10;201.89.44.12">{{ old('ips_permitidos', $ipsPermitidos) }}</textarea>
                    <div class="form-hint">
                        Digite um endereço IP por linha. Esses endereços nunca verão a tela de manutenção.
                        <span class="d-block mt-1">Seu IP atual detectado: <strong>{{ $meuIp }}</strong></span>
                    </div>
                </div>
            </x-admin.card>

            <div class="d-flex justify-content-between align-items-center mb-5">
                <button type="submit" class="btn btn-golden btn-lg px-4 fw-bold">
                    <i class="fas fa-check me-2"></i> Salvar Todas as Configurações de Manutenção
                </button>
                <a href="{{ route('admin.erros.preview', 503) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-eye me-1"></i> Ver Prévia ao Vivo
                </a>
            </div>
        </form>
    </div>

    <div class="col-12 col-lg-4">
        {{-- Card de Chave de Bypass --}}
        <x-admin.card title="Acesso Secreto por Bypass" icon="fa-key" class="mb-4">
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

            <h6 class="fw-bold small text-uppercase text-secondary mb-2">Segurança & SEO</h6>
            <p class="small text-muted mb-0">
                A tela 503 envia o cabeçalho HTTP <code>Retry-After: 300</code> para os motores de busca (Google, Bing). Isso instrui os indexadores a voltarem mais tarde sem penalizar o SEO ou desindexar suas páginas.
            </p>
        </x-admin.card>

        {{-- Card de Resumo de Recursos Ativos --}}
        <x-admin.card title="Resumo da Tela 503" icon="fa-sliders" class="mb-4">
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span>Fundo Animado (Carros):</span>
                    <span class="badge {{ $exibirAnimacao ? 'bg-success' : 'bg-secondary' }}">{{ $exibirAnimacao ? 'Ativo' : 'Desativado' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span>Satélite Orbital & Feixes:</span>
                    <span class="badge {{ $exibirSatelite ? 'bg-success' : 'bg-secondary' }}">{{ $exibirSatelite ? 'Ativo' : 'Desativado' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span>Contador Regressivo:</span>
                    <span class="badge {{ $exibirCronometro ? 'bg-success' : 'bg-secondary' }}">{{ $exibirCronometro ? 'Ativo' : 'Desativado' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span>Botão do WhatsApp:</span>
                    <span class="badge {{ $exibirWhatsapp ? 'bg-success' : 'bg-secondary' }}">{{ $exibirWhatsapp ? 'Ativo' : 'Desativado' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span>Redes Sociais:</span>
                    <span class="badge {{ $exibirRedes ? 'bg-success' : 'bg-secondary' }}">{{ $exibirRedes ? 'Ativo' : 'Desativado' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span>Plantão de Emergência:</span>
                    <span class="badge {{ $exibirPlantao ? 'bg-success' : 'bg-secondary' }}">{{ $exibirPlantao ? 'Ativo' : 'Desativado' }}</span>
                </li>
            </ul>

            <div class="mt-3">
                <a href="{{ route('admin.erros.preview', 503) }}" target="_blank" rel="noopener" class="btn btn-golden w-100 fw-bold">
                    <i class="fas fa-play me-1"></i> Abrir Demonstração da Tela
                </a>
            </div>
        </x-admin.card>
    </div>
</div>
@endsection
