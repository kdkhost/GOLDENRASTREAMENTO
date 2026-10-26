@extends('admin.layouts.app')
@section('title', 'Cron '.$tarefa->nome)
@section('page_title', $tarefa->nome)
@section('page_description', $tarefa->descricao ?: 'Tarefa da agenda do painel, executada pelo cron da hospedagem.')

@section('page_actions')
@if($tarefa->podeRodarAgora())
    <form method="POST" action="{{ route('admin.crons.executar', $tarefa) }}" class="d-inline">
        @csrf
        <button class="btn btn-sm btn-golden"><i class="fas fa-play me-1"></i> Rodar agora</button>
    </form>
@endif
<form method="POST" action="{{ route('admin.crons.toggle', $tarefa) }}" class="d-inline">
    @csrf
    <button class="btn btn-sm btn-outline-secondary"><i class="fas {{ $tarefa->ativa ? 'fa-pause' : 'fa-play' }} me-1"></i> {{ $tarefa->ativa ? 'Pausar' : 'Reativar' }}</button>
</form>
<a href="{{ route('admin.crons.edit', $tarefa) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen me-1"></i> Editar</a>
<a href="{{ route('admin.crons.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-admin.stat label="Execuções no histórico" :value="$tarefa->execucoes()->count()" icon="fa-list" tone="gold" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Terminaram bem" :value="$sucessos" icon="fa-circle-check" tone="green" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Falharam" :value="$falhas" icon="fa-triangle-exclamation" tone="red" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Última duração" :value="$tarefa->duracao_ms === null ? '—' : $tarefa->duracaoEmPalavras()" icon="fa-stopwatch" tone="blue" pequeno
        :hint="$tarefa->batidaEmPalavras()" /></div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.table title="Histórico de execuções" icon="fa-list"
            descricao="O que o agendador fez nas últimas voltas — inclusive as que ele abriu e o botão Rodar agora fechou."
            :headers="['Quando', 'center:Situação', 'center:Duração', 'center:Origem', 'Saída']"
            empty="Ainda sem execuções registradas. A primeira linha nasce na próxima vez que a agenda bater."
            :paginator="$execucoes">
            @foreach($execucoes as $e)
                <tr>
                    <td class="small text-secondary text-nowrap">
                        {{ $e->inicio->format('d/m/Y H:i:s') }}
                        @if($e->fim)<div class="small">terminou {{ $e->fim->format('H:i:s') }}</div>@endif
                    </td>
                    <td class="text-center"><x-admin.status :rotulo="$e->rotuloStatus()" :tom="tom_por_status('cron', $e->status)" /></td>
                    <td class="text-center small text-secondary">{{ $e->duracaoEmPalavras() }}</td>
                    <td class="text-center small text-secondary">{{ $e->acionadaPorEmPalavras() }}</td>
                    <td>
                        @php $texto = trim((string) ($e->saida ?: $e->erro)); @endphp
                        @if($texto === '')
                            <span class="small text-secondary">sem saída</span>
                        @else
                            <pre class="exec-saida mb-0">{{ Str::limit($texto, 400) }}</pre>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-admin.table>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="A tarefa" icon="fa-circle-info">
            <dl class="defs mb-0">
                <dt>Comando</dt><dd><code>{{ $tarefa->comando }}</code></dd>
                <dt>Frequência</dt><dd>{{ $tarefa->agendaPorExtenso() }}</dd>
                <dt>Expressão</dt><dd><code>{{ $tarefa->expressao }}</code></dd>
                <dt>Próxima volta</dt><dd>{{ $tarefa->proximaEmPalavras() }}</dd>
                <dt>Situação</dt><dd><x-admin.status :rotulo="$tarefa->ativa ? 'Ativa' : 'Pausada'" :tom="tom_por_flag($tarefa->ativa)" /></dd>
                <dt>Último resultado</dt><dd><x-admin.status :rotulo="$tarefa->rotuloStatus()" :tom="tom_por_status('cron', $tarefa->status)" /> <span class="text-secondary small">{{ $tarefa->batidaEmPalavras() }}</span></dd>
                <dt>Uma por vez</dt><dd>{{ $tarefa->sem_sobreposicao ? 'sim, com trava' : 'não' }}</dd>
                <dt>Origem</dt><dd>{{ $tarefa->sistema ? 'tarefa do sistema' : 'criada pelo painel' }}</dd>
                <dt>Criada em</dt><dd>{{ $tarefa->created_at?->format('d/m/Y H:i') }}</dd>
            </dl>

            @if($tarefa->ultima_saida)
                <div class="zone-title mt-3">Última saída</div>
                <pre class="exec-saida mb-0">{{ Str::limit(trim($tarefa->ultima_saida), 600) }}</pre>
            @endif
        </x-admin.card>

        <x-admin.card title="Excluir" icon="fa-triangle-exclamation" class="mt-3">
            @if($tarefa->sistema)
                <p class="small text-secondary mb-0">
                    Esta tarefa veio com o sistema e não pode ser apagada. Pause-a se precisar parar o que ela faz —
                    sem ela, o efeito para em silêncio e ninguém fica sabendo.
                </p>
            @else
                <p class="small text-secondary">
                    Excluir remove a tarefa e todo o histórico de execuções dela. Para só parar por um tempo, prefira pausar.
                </p>
                <x-admin.delete :action="route('admin.crons.destroy', $tarefa)" label="Excluir tarefa"
                    confirm="Excluir a tarefa {{ $tarefa->nome }} e o histórico de execuções dela?" />
            @endif
        </x-admin.card>
    </div>
</div>
@endsection
