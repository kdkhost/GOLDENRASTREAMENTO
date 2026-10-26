@extends('admin.layouts.app')
@section('title', 'Crons')
@section('page_title', 'Crons e agenda')
@section('page_description', 'A hospedagem tem uma linha só de cron. O que roda depois dela é decidido aqui: a agenda, o histórico e o "rodar agora".')

@section('page_actions')
<a href="{{ route('admin.crons.create') }}" class="btn btn-sm btn-golden"><i class="fas fa-plus me-1"></i> Nova tarefa</a>
@endsection

@section('content')
<div class="callout {{ $batida_viva ? 'ok' : 'warn' }} mb-3">
    <span>
        <i class="fas {{ $batida_viva ? 'fa-heart-pulse' : 'fa-triangle-exclamation' }} me-1" aria-hidden="true"></i>
        @if($batida_viva)
            <strong>Agendador vivo.</strong> A crontab da hospedagem acordou o agendador {{ $batida_em_palavras }}.
        @elseif($segundos_sem_batida === null)
            <strong>Ainda sem batida.</strong> O agendador não deu sinal desde que este ambiente subiu — no servidor,
            confira se a linha abaixo está registrada no cron da hospedagem.
        @else
            <strong>Sem batida {{ $batida_em_palavras }}.</strong> Nenhuma tarefa roda até a linha da crontab voltar —
            este é um diagnóstico diferente de "a tarefa falhou".
        @endif
    </span>
    <a href="{{ route('admin.guia.index') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-book me-1"></i> Guia do painel</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-admin.stat label="Tarefas na agenda" :value="$kpis['total']" icon="fa-clock" tone="gold" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Ativas" :value="$kpis['ativas']" icon="fa-play" tone="green" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Com falha" :value="$kpis['falhas']" icon="fa-triangle-exclamation" tone="red" pequeno /></div>
    <div class="col-6 col-lg-3"><x-admin.stat label="Ativas sem sinal" :value="$kpis['sem_batida']" icon="fa-heart-crack" tone="amber" pequeno
        hint="passaram da janela da própria frequência" /></div>
</div>

<x-admin.card title="A linha da hospedagem" icon="fa-server" class="mb-3"
    descricao="Ela não muda: é o único agendamento registrado no cron da hospedagem, e é o que acorda a agenda deste painel a cada minuto.">
    <div class="row g-3 align-items-end">
        <div class="col-lg-7">
            <x-admin.link-copiar :valor="$linha_do_servidor" label="Linha registrada no cron da hospedagem" hint="(PHP 8.3 e saída descartada)" />
        </div>
        <div class="col-lg-5">
            <p class="form-text mb-0">
                Uma vez por minuto, essa linha roda o <code>schedule:run</code>, que lê esta tela e chama o que estiver ativo.
                Criar, editar ou pausar uma tarefa aqui não mexe em crontab nenhuma — o efeito aparece na próxima batida.
            </p>
        </div>
    </div>
</x-admin.card>

<x-admin.filters :reset="route('admin.crons.index')">
    <x-admin.filter name="busca" label="Buscar" col="col-12 col-md-4" placeholder="Nome, comando ou descrição..." />
    <x-admin.filter name="situacao" type="select" label="Situação" col="col-6 col-md-2" :options="['ativa' => 'Ativas', 'pausada' => 'Pausadas']" empty="Todas" />
    <x-admin.filter name="status" type="select" label="Último resultado" col="col-6 col-md-3" :options="$statuses" empty="Todos" />
    <x-admin.filter name="agenda" type="select" label="Frequência" col="col-6 col-md-3" :options="$agendas" empty="Todas" />
</x-admin.filters>

<x-admin.table :headers="['Tarefa', 'center:Frequência', 'center:Situação', 'center:Último resultado', 'center:Próxima', 'end:Ações']"
    empty="Nenhuma tarefa com esse filtro. A agenda nasce com as tarefas do sistema e cresce pelo botão Nova tarefa."
    :paginator="$tarefas">
    @foreach($tarefas as $t)
        <tr>
            <td>
                <a href="{{ route('admin.crons.show', $t) }}" class="fw-semibold text-decoration-none">{{ $t->nome }}</a>
                @if($t->sistema)<span class="badge tone-neutral ms-1">sistema</span>@endif
                <div class="small text-secondary"><code>{{ $t->comando }}</code></div>
            </td>
            <td class="text-center small">
                {{ $t->agendaPorExtenso() }}
                <div class="text-secondary"><code>{{ $t->expressao }}</code></div>
            </td>
            <td class="text-center">
                <x-admin.status :rotulo="$t->ativa ? 'Ativa' : 'Pausada'" :tom="tom_por_flag($t->ativa)" />
            </td>
            <td class="text-center">
                <x-admin.status :rotulo="$t->rotuloStatus()" :tom="tom_por_status('cron', $t->status)" />
                <div class="small text-secondary mt-1">{{ $t->batidaEmPalavras() }}
                    @if($t->duracao_ms !== null)· {{ $t->duracaoEmPalavras() }}@endif
                </div>
            </td>
            <td class="text-center small text-secondary">{{ $t->proximaEmPalavras() }}</td>
            <td>
                <div class="table-actions">
                    <a href="{{ route('admin.crons.show', $t) }}" class="btn btn-sm btn-outline-secondary" title="Abrir a ficha"><i class="fas fa-eye" aria-hidden="true"></i></a>

                    @if($t->podeRodarAgora())
                        <form method="POST" action="{{ route('admin.crons.executar', $t) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary" title="Rodar agora"><i class="fas fa-play" aria-hidden="true"></i></button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('admin.crons.toggle', $t) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary" title="{{ $t->ativa ? 'Pausar' : 'Reativar' }}">
                            <i class="fas {{ $t->ativa ? 'fa-pause' : 'fa-play' }}" aria-hidden="true"></i>
                        </button>
                    </form>

                    <a href="{{ route('admin.crons.edit', $t) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen" aria-hidden="true"></i></a>
                </div>
            </td>
        </tr>
    @endforeach
</x-admin.table>
@endsection
