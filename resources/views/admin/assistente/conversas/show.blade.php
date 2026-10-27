@extends('admin.layouts.app')
@section('title', 'Conversa '.$conversa->id)
@section('page_title', $conversa->quem())
@section('page_description', $conversa->canalLabel().' · '.($conversa->pagina ?: 'sem página registrada'))

@section('page_actions')
@if($conversa->chamado)
    <a href="{{ route('admin.chamados.show', $conversa->chamado) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-life-ring me-1"></i> Chamado</a>
@endif
@if($conversa->lead)
    <a href="{{ route('admin.leads.show', $conversa->lead) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-user-plus me-1"></i> Lead</a>
@endif
<a href="{{ route('admin.assistente.conversas.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="A fala" icon="fa-comments"
            :descricao="$conversa->mensagens->count().' mensagem(ns) — o que o cliente vê na bolha do site'">
            <div class="thread">
                @forelse($conversa->mensagens as $m)
                    <div class="msg {{ $m->autor }}">
                        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
                            <span class="fw-semibold small">
                                <i class="fas {{ ['cliente' => 'fa-user', 'assistente' => 'fa-robot', 'equipe' => 'fa-headset', 'sistema' => 'fa-gear'][$m->autor] ?? 'fa-comment' }} me-1 text-gold" aria-hidden="true"></i>
                                {{ $m->assinatura() }}
                                @if($m->doAssistente() && $m->provedor)
                                    <span class="form-hint">{{ \App\Services\Assistente\Provedores\Fabrica::rotuloDe($m->provedor) }} · {{ $m->modelo }}</span>
                                @endif
                            </span>
                            <small class="text-secondary">{{ $m->created_at->format('d/m/Y H:i') }}</small>
                        </div>

                        @if($m->textoVisivel() !== '')
                            <div class="small mt-1" style="white-space:pre-wrap">{{ $m->textoVisivel() }}</div>
                        @endif

                        @if($m->temMidia())
                            <div class="small mt-2">
                                @if($m->midiaUrl())
                                    <a href="{{ $m->midiaUrl() }}" target="_blank" rel="noopener">
                                        <i class="fas {{ $m->tipo === 'imagem' ? 'fa-image' : ($m->tipo === 'audio' ? 'fa-microphone' : 'fa-file-lines') }} me-1"></i>
                                        {{ $m->midia_nome ?: $m->tipoLabel() }}
                                    </a>
                                @else
                                    <span class="text-secondary"><i class="fas fa-triangle-exclamation me-1"></i> arquivo não está mais no disco</span>
                                @endif
                                @if($m->duracao_segundos)<span class="form-hint ms-2">{{ $m->duracao_segundos }}s</span>@endif
                            </div>
                        @endif

                        @if($m->audioUrl())
                            <div class="mt-2">
                                <audio controls preload="none" src="{{ $m->audioUrl() }}" class="w-100"></audio>
                            </div>
                        @endif

                        @if($m->fontes() !== [])
                            <div class="msg-fontes mt-2">
                                <span class="form-hint"><i class="fas fa-book-open me-1" aria-hidden="true"></i> Fontes da resposta</span>
                                <ul class="small mb-0 ps-3">
                                    @foreach($m->fontes() as $fonte)
                                        <li>{{ $fonte['titulo'] ?? 'item' }} — {{ $fonte['procedencia'] ?? '' }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-secondary mb-0">Ainda não há fala nesta conversa.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('admin.assistente.conversas.responder', $conversa) }}" class="row g-3 mt-2">
                @csrf
                <x-admin.field name="corpo" type="textarea" label="Responder como equipe" rows="4" :required="true"
                    placeholder="Quem escreve aqui assume a conversa: a máquina para de responder o resto do fio."
                    hint="A resposta é sua, assinada pelo seu login, do mesmo jeito do chamado." />
                <div class="col-12">
                    <button class="btn btn-golden btn-sm"><i class="fas fa-paper-plane me-1"></i> Enviar resposta</button>
                </div>
            </form>
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Atendimento" icon="fa-sliders">
            <form method="POST" action="{{ route('admin.assistente.conversas.status', $conversa) }}" class="row g-3">
                @csrf
                <x-admin.field name="status" type="select" label="Situação" :value="$conversa->status" :options="$statuses" :required="true" />
                <div class="col-12">
                    <button class="btn btn-golden btn-sm"><i class="fas fa-save me-1"></i> Atualizar conversa</button>
                </div>
            </form>

            <dl class="defs mt-3 mb-0">
                @php $acao_em_curso = \App\Services\Assistente\Acoes::descrever($conversa); @endphp
                <dt>De onde</dt><dd>{{ $conversa->canalLabel() }}</dd>
                <dt>Página</dt><dd>{{ $conversa->pagina ?: '—' }}</dd>
                <dt>Contato dito no chat</dt><dd>{{ $conversa->contato ?: '—' }}</dd>
                <dt>Por que chamou a equipe</dt><dd>{{ $conversa->escala_motivo ? $conversa->escalaLabel() : '—' }}</dd>
                <dt>Ação da máquina</dt><dd>{{ $acao_em_curso !== '' ? $acao_em_curso : 'nenhuma em curso' }}</dd>
                <dt>Equipe assumiu</dt><dd>{{ $conversa->respondido_por_equipe_em ? $conversa->respondido_por_equipe_em->format('d/m/Y H:i') : 'ainda é da máquina' }}</dd>
                <dt>Resolvida em</dt><dd>{{ $conversa->resolvido_em ? $conversa->resolvido_em->format('d/m/Y H:i') : '—' }}</dd>
            </dl>
        </x-admin.card>

        <x-admin.card title="Pontes" icon="fa-diagram-project" class="mt-3"
            descricao="A conversa nasce anônima e pode virar lead ou chamado sem perder o histórico.">
            <p class="small mb-0">
                @if($conversa->cliente)
                    <i class="fas fa-address-card me-1 text-gold"></i> Cliente: {{ $conversa->cliente->nome }}
                @elseif($conversa->lead)
                    <i class="fas fa-user-plus me-1 text-gold"></i> Lead: {{ $conversa->lead->name }}
                @else
                    <span class="text-secondary">Visitante anônimo — ainda sem cadastro. A bolha oferece o formulário de contato quando a conversa pede.</span>
                @endif
            </p>
        </x-admin.card>
    </div>
</div>
@endsection
