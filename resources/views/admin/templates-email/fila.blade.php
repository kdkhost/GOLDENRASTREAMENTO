@extends('admin.layouts.app')
@section('title', 'Fila Anti-Spam de E-mails')
@section('page_title', 'Fila cadenciada & proteção anti-spam')

@section('page_actions')
    <a href="{{ route('admin.templates-email.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left" aria-hidden="true"></i> Templates
    </a>
    <form method="POST" action="{{ route('admin.templates-email.fila.processar') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-golden">
            <i class="fas fa-play" aria-hidden="true"></i> Processar Lote Agora
        </button>
    </form>
@endsection

@section('content')
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
    <div class="col">
        <x-admin.stat label="Aguardando Disparo" :value="(string) $estatisticas['pendentes']" icon="fa-hourglass-half" tone="amber"
            :meta="$estatisticas['proximo_envio'] ? 'Próx: ' . $estatisticas['proximo_envio'] : 'Fila vazia'" />
    </div>
    <div class="col">
        <x-admin.stat label="Enviados na Última Hora" :value="$estatisticas['enviados_hora'] . ' / ' . $configFila['limite_por_hora']"
            icon="fa-gauge-high" tone="teal" meta="Teto por hora anti-bloqueio" />
    </div>
    <div class="col">
        <x-admin.stat label="Enviados Hoje" :value="(string) $estatisticas['enviados_hoje']" icon="fa-paper-plane" tone="green"
            :meta="$configFila['intervalo_entre_envios_seg'] . 's entre disparos'" />
    </div>
    <div class="col">
        <x-admin.stat label="Falhas Registradas" :value="(string) $estatisticas['falhas']" icon="fa-triangle-exclamation"
            :tone="$estatisticas['falhas'] > 0 ? 'red' : 'neutral'" meta="Backoff automático ativo" />
    </div>
</div>

{{-- Regras Anti-Spam --}}
<x-admin.card title="Controle Anti-Spam & Cadência SMTP" icon="fa-shield-halved"
    descricao="Espaça automaticamente cada e-mail e fatura PDF para proteger a reputação do domínio contra bloqueios." class="mb-3">
    <form method="POST" action="{{ route('admin.templates-email.fila.config') }}" class="row g-3 align-items-end">
        @csrf
        @method('PUT')

        <x-admin.switch name="ativo" label="Cadência Anti-Spam Ativa" :checked="$configFila['ativo']" col="col-12 col-md-3" />

        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold">Intervalo (seg)</label>
            <input type="number" name="intervalo_entre_envios_seg" min="0" max="600"
                value="{{ $configFila['intervalo_entre_envios_seg'] }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold">Limite / Minuto</label>
            <input type="number" name="limite_por_minuto" min="1" max="120"
                value="{{ $configFila['limite_por_minuto'] }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold">Limite / Hora</label>
            <input type="number" name="limite_por_hora" min="1" max="2000"
                value="{{ $configFila['limite_por_hora'] }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label small fw-semibold">Tent.</label>
            <input type="number" name="max_tentativas" min="1" max="10"
                value="{{ $configFila['max_tentativas'] }}" class="form-control form-control-sm">
        </div>
        <div class="col-12 col-md-2">
            <button type="submit" class="btn btn-sm btn-golden w-100">
                <i class="fas fa-save" aria-hidden="true"></i> Salvar Regras
            </button>
        </div>
    </form>
</x-admin.card>

{{-- Histórico & Itens Enfileirados --}}
<x-admin.card title="Itens na Fila" icon="fa-list-check">
    <x-slot:actions>
        <div class="d-flex flex-wrap gap-1">
            @foreach(['' => 'Todos', 'pendente' => 'Pendentes', 'enviado' => 'Enviados', 'falhou' => 'Falhos', 'cancelado' => 'Cancelados'] as $slug => $rotulo)
                <a href="{{ route('admin.templates-email.fila', $slug !== '' ? ['status' => $slug] : []) }}"
                    class="btn btn-sm {{ $statusAtual === $slug ? 'btn-golden' : 'btn-outline-secondary' }}">
                    {{ $rotulo }}
                </a>
            @endforeach
        </div>
    </x-slot:actions>

    <x-admin.table :links="$itens->links()">
        <x-slot:head>
            <tr>
                <th>#</th>
                <th>Destinatário</th>
                <th>Assunto / Template</th>
                <th>Anexo</th>
                <th>Agendado Para</th>
                <th class="text-center">Tent.</th>
                <th class="text-center">Status</th>
                <th class="text-end">Ações</th>
            </tr>
        </x-slot:head>
        @forelse($itens as $item)
            @php
                $tom = match($item->status) {
                    'enviado' => 'green',
                    'pendente' => 'amber',
                    'processando' => 'teal',
                    'falhou' => 'red',
                    default => 'neutral',
                };
            @endphp
            <tr>
                <td class="small fw-semibold">#{{ $item->id }}</td>
                <td>
                    <div class="fw-semibold small">{{ $item->destinatario_nome ?: $item->destinatario_email }}</div>
                    <div class="small text-secondary">{{ $item->destinatario_email }}</div>
                </td>
                <td>
                    <div class="small fw-semibold">{{ $item->assunto }}</div>
                    @if($item->template_chave)
                        <code class="small text-gold">{{ $item->template_chave }}</code>
                    @endif
                    @if($item->ultimo_erro)
                        <div class="small text-tone-red mt-1">{{ $item->ultimo_erro }}</div>
                    @endif
                </td>
                <td class="small">
                    @if(!empty($item->anexos))
                        <span class="badge-status tone-gold"><i class="fas fa-file-pdf me-1"></i> PDF</span>
                    @else
                        <span class="text-secondary">—</span>
                    @endif
                </td>
                <td class="small">
                    {{ $item->agendado_para?->format('d/m H:i:s') ?? '-' }}
                    @if($item->enviado_em)
                        <div class="text-secondary">Enviado: {{ $item->enviado_em->format('d/m H:i:s') }}</div>
                    @endif
                </td>
                <td class="text-center small">{{ $item->tentativas }}/{{ $item->max_tentativas }}</td>
                <td class="text-center">
                    <x-admin.status :rotulo="\App\Models\EmailFila::STATUS_ROTULOS[$item->status] ?? $item->status" :tom="$tom" />
                </td>
                <td class="text-end">
                    <div class="d-inline-flex gap-1">
                        @if($item->status !== \App\Models\EmailFila::ENVIADO)
                            <form method="POST" action="{{ route('admin.templates-email.fila.enviar', $item->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-golden" title="Disparar este e-mail agora">
                                    <i class="fas fa-paper-plane" aria-hidden="true"></i>
                                </button>
                            </form>
                            @if($item->status !== \App\Models\EmailFila::CANCELADO)
                                <form method="POST" action="{{ route('admin.templates-email.fila.cancelar', $item->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar disparo">
                                        <i class="fas fa-ban" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center text-secondary py-4">Nenhum registro na fila com esse filtro.</td>
            </tr>
        @endforelse
    </x-admin.table>
</x-admin.card>
@endsection
