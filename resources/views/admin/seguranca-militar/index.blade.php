@extends('admin.layouts.app')
@section('title', 'Segurança Militar (WAF)')
@section('page_title', 'Escudo de Segurança Militar')

@php
    $abaAtiva = request()->query('aba', 'war-room');
@endphp

@section('page_actions')
    <form method="POST" action="{{ route('admin.seguranca-militar.whitelist.meu-ip') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-success" title="Garante que seu IP atual nunca seja bloqueado por engano">
            <i class="fas fa-user-check me-1"></i> Liberar Meu IP na Whitelist ({{ $meuIp }})
        </button>
    </form>
    <a href="{{ route('admin.seguranca.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-user-lock me-1"></i> Segurança do Acesso (2FA)
    </a>
@endsection

@section('content')
{{-- KPIs da War Room --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <x-admin.stat label="Ataques Repelidos" :value="number_format($stats['total_bloqueios'], 0, ',', '.')" icon="fa-shield-halved" tone="red" pequeno hint="Total de ameaças interceptadas pelo escudo." />
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.stat label="IPs Banidos no Momento" :value="number_format($stats['ips_bloqueados_ativos'], 0, ',', '.')" icon="fa-ban" tone="neutral" pequeno hint="Endereços em quarentena ou lista negra." />
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.stat label="Críticos nas Últimas 24h" :value="number_format($stats['criticos_24h'], 0, ',', '.')" icon="fa-triangle-exclamation" :tone="$stats['criticos_24h'] > 0 ? 'red' : 'teal'" pequeno hint="Tentativas de SQLi, RCE ou varreduras." />
    </div>
    <div class="col-6 col-lg-3">
        @php
            $tomStatus = match($stats['rigor']) {
                'lockdown' => 'red',
                'militar', 'alto' => 'gold',
                default => 'blue'
            };
        @endphp
        <x-admin.stat label="Rigor de Proteção" :value="strtoupper($stats['rigor'])" icon="fa-crosshairs" :tone="$tomStatus" pequeno hint="Nível de inspeção e tolerância a tráfego hostil." />
    </div>
</div>

{{-- Abas do Painel Militar --}}
<div class="abas" data-abas="seguranca-militar">
    <div class="abas-barra mb-3">
        <ul class="nav abas-nav" role="tablist">
            <li class="nav-item">
                <button class="nav-link {{ $abaAtiva === 'war-room' ? 'active' : '' }}" type="button" data-bs-toggle="tab" data-bs-target="#tab-war-room">
                    <i class="fas fa-radar me-1"></i> War Room (Visão Geral)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link {{ $abaAtiva === 'logs' ? 'active' : '' }}" type="button" data-bs-toggle="tab" data-bs-target="#tab-logs">
                    <i class="fas fa-list-check me-1"></i> Central de Logs de Ataque
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link {{ $abaAtiva === 'ips' ? 'active' : '' }}" type="button" data-bs-toggle="tab" data-bs-target="#tab-ips">
                    <i class="fas fa-network-wired me-1"></i> Gestão de IPs (Whitelist / Blacklist)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link {{ $abaAtiva === 'config' ? 'active' : '' }}" type="button" data-bs-toggle="tab" data-bs-target="#tab-config">
                    <i class="fas fa-sliders me-1"></i> Configurações do Escudo
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content">
        {{-- ABA 1: WAR ROOM --}}
        <div class="tab-pane fade {{ $abaAtiva === 'war-room' ? 'show active' : '' }}" id="tab-war-room">
            <div class="row g-4">
                <div class="col-12 col-lg-7">
                    <x-admin.card title="Status do Perímetro de Defesa" icon="fa-shield-virus">
                        <div class="d-flex align-items-center justify-content-between p-3 rounded mb-4" style="background: rgba(245, 179, 1, 0.06); border: 1px solid rgba(245, 179, 1, 0.2);">
                            <div>
                                <h5 class="fw-bold mb-1 text-gold"><i class="fas fa-tower-broadcast me-2"></i> Escudo WAF Militar Ativo</h5>
                                <p class="small text-secondary mb-0">Inspeção profunda de cabeçalhos, payloads, injeções SQL, XSS, scripts e probes em tempo real.</p>
                            </div>
                            <span class="badge bg-success px-3 py-2" style="font-size: 0.9rem;">
                                <i class="fas fa-circle-check me-1"></i> 100% OPERACIONAL
                            </span>
                        </div>

                        <h6 class="fw-bold text-uppercase small text-secondary mb-3">Distribuição de Ameaças Interceptadas</h6>
                        @forelse($stats['distribuicao'] as $evento => $qtd)
                            @php
                                $percent = $stats['total_bloqueios'] > 0 ? round(($qtd / $stats['total_bloqueios']) * 100) : 0;
                            @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-bold"><i class="fas fa-caret-right text-gold me-1"></i> {{ ucfirst(str_replace('_', ' ', $evento)) }}</span>
                                    <span class="text-secondary">{{ number_format($qtd, 0, ',', '.') }} ocorrência(s) ({{ $percent }}%)</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-secondary small">Nenhum evento malicioso registrado até o momento.</p>
                        @endforelse
                    </x-admin.card>
                </div>

                <div class="col-12 col-lg-5">
                    <x-admin.card title="Parâmetros de Defesa Rápida" icon="fa-crosshairs">
                        <div class="mb-3">
                            <label class="small text-secondary fw-bold text-uppercase d-block mb-1">Nível de Rigor Vigente:</label>
                            <span class="badge bg-warning text-dark px-3 py-2 fw-bold text-uppercase" style="font-size: 0.88rem;">
                                {{ $stats['rigor'] }}
                            </span>
                        </div>

                        <ul class="list-group list-group-flush small mb-3">
                            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-2">
                                <span><i class="fas fa-database text-gold me-2"></i> Bloqueio de Injeção SQL (SQLi)</span>
                                <span class="badge text-bg-success">Ativo</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-2">
                                <span><i class="fas fa-code text-gold me-2"></i> Bloqueio de Cross-Site Scripting (XSS)</span>
                                <span class="badge text-bg-success">Ativo</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-2">
                                <span><i class="fas fa-terminal text-gold me-2"></i> Bloqueio de RCE e Path Traversal</span>
                                <span class="badge text-bg-success">Ativo</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-2">
                                <span><i class="fas fa-spider text-gold me-2"></i> Bloqueio de Scanners e Robôs Hostis</span>
                                <span class="badge text-bg-success">Ativo</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-2">
                                <span><i class="fas fa-file-shield text-gold me-2"></i> Probes de Arquivos Críticos (.env, git)</span>
                                <span class="badge text-bg-success">Ativo</span>
                            </li>
                        </ul>

                        <a href="#tab-config" onclick="document.querySelector('[data-bs-target=\'#tab-config\']').click()" class="btn btn-golden btn-sm w-100">
                            <i class="fas fa-sliders me-1"></i> Ajustar Rigor e Regras de Auto-Ban
                        </a>
                    </x-admin.card>
                </div>
            </div>
        </div>

        {{-- ABA 2: CENTRAL DE LOGS DE ATAQUE --}}
        <div class="tab-pane fade {{ $abaAtiva === 'logs' ? 'show active' : '' }}" id="tab-logs">
            <x-admin.card title="Central de Eventos e Auditoria Militar" icon="fa-list-check">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <form method="GET" action="{{ route('admin.seguranca-militar.index') }}" class="d-flex flex-wrap gap-2 align-items-center">
                        <input type="hidden" name="aba" value="logs">
                        <input type="text" name="busca" class="form-control form-control-sm" placeholder="Buscar por IP ou URL..." value="{{ $busca }}" style="width: 220px;">
                        <select name="nivel" class="form-select form-select-sm" style="width: 140px;">
                            <option value="">Todos os níveis</option>
                            <option value="critico" {{ $filtroNivel === 'critico' ? 'selected' : '' }}>Crítico</option>
                            <option value="alto" {{ $filtroNivel === 'alto' ? 'selected' : '' }}>Alto</option>
                            <option value="medio" {{ $filtroNivel === 'medio' ? 'selected' : '' }}>Médio</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-filter me-1"></i> Filtrar</button>
                        @if($busca || $filtroNivel || $filtroEvento)
                            <a href="{{ route('admin.seguranca-militar.index', ['aba' => 'logs']) }}" class="btn btn-sm btn-link text-secondary">Limpar</a>
                        @endif
                    </form>

                    <form method="POST" action="{{ route('admin.seguranca-militar.logs.limpar') }}"
                        onsubmit="return confirm('Deseja limpar logs de auditoria com mais de 30 dias?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-trash me-1"></i> Limpar logs antigos (+30 dias)
                        </button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 font-monospace small">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Endereço IP</th>
                                <th>Ameaça</th>
                                <th>Severidade</th>
                                <th>Método / URL</th>
                                <th>Ação</th>
                                <th class="text-end">Ações Rápidas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    <td class="text-nowrap text-secondary">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                                    <td>
                                        <span class="fw-bold text-light">{{ $log->ip }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            <i class="fas {{ $log->icone() }} me-1"></i> {{ $log->rotuloEvento() }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-{{ $log->tom() }} text-uppercase">{{ $log->nivel }}</span>
                                    </td>
                                    <td class="text-truncate" style="max-width: 280px;" title="{{ $log->url }}">
                                        <strong class="text-gold">{{ $log->metodo }}</strong> {{ $log->url }}
                                        @if($log->payload_suspeito)
                                            <div class="text-secondary small text-truncate" style="max-width: 260px;" title="{{ $log->payload_suspeito }}">
                                                <code>{{ $log->payload_suspeito }}</code>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary">{{ $log->acao_tomada }}</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <form method="POST" action="{{ route('admin.seguranca-militar.ips.adicionar') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="ip" value="{{ $log->ip }}">
                                                <input type="hidden" name="tipo" value="blacklist">
                                                <input type="hidden" name="motivo" value="Banido via Central de Logs ({{ $log->rotuloEvento() }})">
                                                <input type="hidden" name="horas" value="72">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Banir este IP por 72 horas">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.seguranca-militar.ips.adicionar') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="ip" value="{{ $log->ip }}">
                                                <input type="hidden" name="tipo" value="whitelist">
                                                <input type="hidden" name="motivo" value="Liberado manualmente na Whitelist">
                                                <button type="submit" class="btn btn-outline-success btn-sm" title="Liberar este IP na Whitelist">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-secondary">
                                        Nenhum evento registrado com os filtros informados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($logs->hasPages())
                    <div class="card-footer bg-body-tertiary px-0 pt-3">
                        {{ $logs->links() }}
                    </div>
                @endif
            </x-admin.card>
        </div>

        {{-- ABA 3: GESTÃO DE IPS (WHITELIST / BLACKLIST) --}}
        <div class="tab-pane fade {{ $abaAtiva === 'ips' ? 'show active' : '' }}" id="tab-ips">
            <div class="row g-4">
                {{-- Formulário de Adição de Regra --}}
                <div class="col-12 col-lg-4">
                    <x-admin.card title="Nova Regra de IP" icon="fa-plus">
                        <form method="POST" action="{{ route('admin.seguranca-militar.ips.adicionar') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="regra-ip" class="form-label fw-bold">Endereço IP</label>
                                <input type="text" id="regra-ip" name="ip" class="form-control font-monospace" placeholder="Ex: 177.18.99.45" required maxlength="45">
                            </div>

                            <div class="mb-3">
                                <label for="regra-tipo" class="form-label fw-bold">Lista de Destino</label>
                                <select id="regra-tipo" name="tipo" class="form-select">
                                    <option value="blacklist">Lista Negra (Blacklist - Bloquear Acesso)</option>
                                    <option value="whitelist">Lista Branca (Whitelist - Permitir Sempre)</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="regra-motivo" class="form-label fw-bold">Motivo do Bloqueio ou Liberação</label>
                                <input type="text" id="regra-motivo" name="motivo" class="form-control" placeholder="Ex: Tentativa suspeita de brute force" required maxlength="255">
                            </div>

                            <div class="mb-3">
                                <label for="regra-horas" class="form-label fw-bold">Duração (Horas)</label>
                                <input type="number" id="regra-horas" name="horas" class="form-control" placeholder="Deixe em branco para permanente" min="1" max="8760">
                                <div class="form-hint">Em branco = Regra permanente.</div>
                            </div>

                            <button type="submit" class="btn btn-golden w-100">
                                <i class="fas fa-plus me-1"></i> Aplicar Regra de IP
                            </button>
                        </form>
                    </x-admin.card>
                </div>

                {{-- Tabelas de Whitelist e Blacklist --}}
                <div class="col-12 col-lg-8">
                    {{-- Blacklist --}}
                    <x-admin.card title="Lista Negra Ativa (IPs Bloqueados)" icon="fa-ban" class="mb-4">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 font-monospace small">
                                <thead>
                                    <tr>
                                        <th>IP</th>
                                        <th>Motivo</th>
                                        <th>Bloqueios</th>
                                        <th>Expira em</th>
                                        <th class="text-end">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($blacklists as $b)
                                        <tr>
                                            <td class="fw-bold text-danger">{{ $b->ip }}</td>
                                            <td class="text-truncate" style="max-width: 220px;" title="{{ $b->motivo }}">{{ $b->motivo }}</td>
                                            <td><span class="badge bg-danger-subtle text-danger">{{ $b->tentativas_bloqueadas }}</span></td>
                                            <td class="text-secondary">{{ $b->expira_em ? $b->expira_em->format('d/m/Y H:i') : 'Permanente' }}</td>
                                            <td class="text-end">
                                                <form method="POST" action="{{ route('admin.seguranca-militar.ips.remover', $b->id) }}" class="d-inline"
                                                    onsubmit="return confirm('Remover o bloqueio deste IP?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Desbloquear IP">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-secondary py-3">Nenhum IP na lista negra no momento.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-admin.card>

                    {{-- Whitelist --}}
                    <x-admin.card title="Lista Branca Ativa (IPs Liberados)" icon="fa-circle-check">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 font-monospace small">
                                <thead>
                                    <tr>
                                        <th>IP</th>
                                        <th>Observação</th>
                                        <th>Registrado em</th>
                                        <th class="text-end">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($whitelists as $w)
                                        <tr>
                                            <td class="fw-bold text-success">{{ $w->ip }}</td>
                                            <td>{{ $w->motivo }}</td>
                                            <td class="text-secondary">{{ $w->created_at?->format('d/m/Y') }}</td>
                                            <td class="text-end">
                                                <form method="POST" action="{{ route('admin.seguranca-militar.ips.remover', $w->id) }}" class="d-inline"
                                                    onsubmit="return confirm('Remover este IP da lista branca?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Remover da Whitelist">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-secondary py-3">Nenhum IP customizado na lista branca. (127.0.0.1 é liberado nativamente).</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-admin.card>
                </div>
            </div>
        </div>

        {{-- ABA 4: CONFIGURAÇÕES DO ESCUDO --}}
        <div class="tab-pane fade {{ $abaAtiva === 'config' ? 'show active' : '' }}" id="tab-config">
            <div class="row g-4 justify-content-center">
                <div class="col-12 col-lg-8">
                    <x-admin.card title="Diretrizes do Escudo de Segurança Militar" icon="fa-sliders">
                        <form method="POST" action="{{ route('admin.seguranca-militar.configuracoes') }}">
                            @csrf
                            @method('PUT')

                            <div class="mb-4 p-3 rounded" style="background: rgba(245, 179, 1, 0.08); border: 1px solid rgba(245, 179, 1, 0.25);">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="switch-shield_ativo" name="shield_ativo" value="1"
                                        {{ $shield->estaAtivo() ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold" for="switch-shield_ativo">Escudo Militar WAF Habilitado</label>
                                </div>
                                <div class="small text-secondary">Desativar desliga toda a inspeção de tráfego perimetral (não recomendado).</div>
                            </div>

                            <div class="mb-4">
                                <label for="select-rigor" class="form-label fw-bold">Nível de Rigor de Defesa</label>
                                <select id="select-rigor" name="shield_rigor" class="form-select">
                                    <option value="monitoramento" {{ $shield->nivelRigor() === 'monitoramento' ? 'selected' : '' }}>
                                        Modo Monitoramento (Apenas audita e gera logs, sem bloquear conexões)
                                    </option>
                                    <option value="padrao" {{ $shield->nivelRigor() === 'padrao' ? 'selected' : '' }}>
                                        Padrão (Bloqueia ataques severos conhecidos e scanners hostis)
                                    </option>
                                    <option value="alto" {{ $shield->nivelRigor() === 'alto' ? 'selected' : '' }}>
                                        Alto (Inspeção profunda de cabeçalhos e parâmetros de formulário)
                                    </option>
                                    <option value="militar" {{ $shield->nivelRigor() === 'militar' ? 'selected' : '' }}>
                                        Militar / Tolerância Zero (Recomendado - bloqueia e aplica auto-ban em qualquer tentativa maliciosa)
                                    </option>
                                    <option value="lockdown" {{ $shield->nivelRigor() === 'lockdown' ? 'selected' : '' }}>
                                        Lockdown / Quarentena Total (Bloqueia absolutamente tudo, exceto IPs da Whitelist)
                                    </option>
                                </select>
                            </div>

                            <h6 class="fw-bold text-uppercase small text-secondary mb-3">Módulos de Detecção Especializada</h6>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="switch-bloquear_sqli" name="shield_bloquear_sqli" value="1"
                                    {{ $shield->bloquearSqli() ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="switch-bloquear_sqli">Proteção contra Injeção SQL (SQLi)</label>
                                <div class="form-hint">Intercepta UNION SELECT, tautologias OR 1=1, benchmarks, manipulação de schemas.</div>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="switch-bloquear_xss" name="shield_bloquear_xss" value="1"
                                    {{ $shield->bloquearXss() ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="switch-bloquear_xss">Proteção contra Cross-Site Scripting (XSS)</label>
                                <div class="form-hint">Bloqueia tags &lt;script&gt;, URIs javascript:, manipuladores onload/onerror inline.</div>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="switch-bloquear_rce" name="shield_bloquear_rce" value="1"
                                    {{ $shield->bloquearRce() ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="switch-bloquear_rce">Proteção contra RCE e Quebra de Diretório (Path Traversal)</label>
                                <div class="form-hint">Bloqueia sequências ../, acesso a /etc/passwd, chamadas de shell e terminais.</div>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="switch-bloquear_scanners" name="shield_bloquear_scanners" value="1"
                                    {{ $shield->bloquearScanners() ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="switch-bloquear_scanners">Bloqueio de Ferramentas e Scanners Hostis</label>
                                <div class="form-hint">Identifica e barra agentes automatizados como sqlmap, nikto, masscan, acunetix, gobuster.</div>
                            </div>

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="switch-bloquear_probes" name="shield_bloquear_probes" value="1"
                                    {{ $shield->bloquearProbes() ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="switch-bloquear_probes">Bloqueio de Varreduras a Arquivos Críticos (.env, .git, wp)</label>
                                <div class="form-hint">Banimento instantâneo para quem tentar ler arquivos confidenciais do servidor.</div>
                            </div>

                            <hr class="my-4">

                            <h6 class="fw-bold text-uppercase small text-secondary mb-3">Sistema de Prisão Automática (Auto-Ban)</h6>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="switch-autoban_ativo" name="shield_autoban_ativo" value="1"
                                    {{ $shield->autoBanAtivo() ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="switch-autoban_ativo">Auto-Ban Habilitado</label>
                                <div class="form-hint">Adiciona automaticamente à Lista Negra IPs com infrações reincidentes ou ataques críticos.</div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-12 col-md-6">
                                    <label for="campo-autoban_limite" class="form-label fw-bold">Limite de Infrações Toleradas</label>
                                    <input type="number" id="campo-autoban_limite" name="shield_autoban_limite" class="form-control"
                                        value="{{ old('shield_autoban_limite', $shield->limiteAutoBan()) }}" min="1" max="50">
                                    <div class="form-hint">Número de tentativas suspeitas antes de aplicar o ban (ataques críticos são banidos na 1ª tentativa).</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="campo-autoban_horas" class="form-label fw-bold">Duração do Banimento Automático (Horas)</label>
                                    <input type="number" id="campo-autoban_horas" name="shield_autoban_horas" class="form-control"
                                        value="{{ old('shield_autoban_horas', $shield->horasAutoBan()) }}" min="1" max="720">
                                    <div class="form-hint">Tempo em que o IP permanecerá bloqueado.</div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-golden">
                                <i class="fas fa-check me-1"></i> Salvar Diretrizes de Segurança
                            </button>
                        </form>
                    </x-admin.card>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

