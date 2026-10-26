@extends('admin.layouts.app')
@section('title', $tarefa->exists ? 'Editar '.$tarefa->nome : 'Nova tarefa')
@section('page_title', $tarefa->exists ? 'Editar tarefa agendada' : 'Nova tarefa agendada')
@section('page_description', $tarefa->exists
    ? 'Mudar a agenda aqui vale a partir da próxima batida do cron da hospedagem.'
    : 'Escolha o comando e a frequência. A tarefa entra na agenda assim que a próxima batida do cron passar.')

@section('page_actions')
<a href="{{ $tarefa->exists ? route('admin.crons.show', $tarefa) : route('admin.crons.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
@if($tarefa->exists && $tarefa->sistema)
    <div class="callout mb-3">
        <span><i class="fas fa-lock me-1" aria-hidden="true"></i>
            Esta é uma tarefa do sistema: a agenda e a pausa são suas, mas apagar não é.
            Sem ela, o que ela faz para de acontecer em silêncio.</span>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="O que a tarefa faz" icon="fa-clock"
            descricao="O comando vem do próprio artisan; o que sobra é flag. Juntos, os dois formam a linha que o agendador executa.">
            <form method="POST" action="{{ $tarefa->exists ? route('admin.crons.update', $tarefa) : route('admin.crons.store') }}" class="row g-3">
                @csrf

                <x-admin.field name="nome" label="Nome da tarefa" :value="$tarefa->nome" required maxlength="120"
                    placeholder="Ex.: Drenar a fila de envios" col="col-md-7" />

                <x-admin.field name="agenda" type="select" label="Frequência" :value="$tarefa->agenda" :options="$agendas" required col="col-md-5" />

                <x-admin.field name="descricao" type="textarea" label="Para que serve" :value="$tarefa->descricao" :rows="2" :opcional="true"
                    maxlength="1000" placeholder="Uma linha que explique a tarefa para quem abrir a ficha daqui a seis meses." />

                <x-admin.field name="comando" type="select" label="Comando" :value="$tarefa->nomeComando()" :options="$comandos" required col="col-md-6"
                    hint="Só entram os comandos registrados no artisan." />

                <x-admin.field name="flags" label="Flags" :value="$tarefa->flags()" :opcional="true" col="col-md-6" maxlength="300"
                    placeholder="--stop-when-empty --max-time=50"
                    hint="Separadas por espaço. Ponto e vírgula, pipe e redirecionamento são recusados." />

                <x-admin.field name="hora" type="time" label="Hora" :value="$tarefa->hora" col="col-md-3"
                    hint="Só para frequências com hora marcada." />

                <x-admin.field name="dia_semana" type="select" label="Dia da semana" :value="$tarefa->dia_semana" :options="$dias" col="col-md-3"
                    empty="— escolha o dia —" hint="Só para a frequência semanal." />

                <x-admin.switch name="ativa" label="Ativa" :checked="$tarefa->ativa" col="col-md-3"
                    hint="Pausada, ela nem é registrada pelo agendador." />

                <x-admin.switch name="sem_sobreposicao" label="Uma execução por vez" :checked="$tarefa->sem_sobreposicao" col="col-md-3"
                    hint="A trava impede que duas voltas se empilhem." />

                <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                    <button class="btn btn-golden"><i class="fas fa-save me-1"></i> {{ $tarefa->exists ? 'Salvar tarefa' : 'Criar tarefa' }}</button>
                    <a href="{{ $tarefa->exists ? route('admin.crons.show', $tarefa) : route('admin.crons.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Como a agenda funciona" icon="fa-circle-info">
            <dl class="defs mb-0">
                <dt>Quem manda</dt>
                <dd>esta tela, não a crontab</dd>
                <dt>Quando vale</dt>
                <dd>na próxima batida, em até um minuto</dd>
                <dt>Pausada</dt>
                <dd>não é registrada pelo agendador</dd>
                <dt>Tarefa do sistema</dt>
                <dd>pode pausar e reagendar, não apagar</dd>
                <dt>Rodar agora</dt>
                <dd>na ficha da tarefa, exceto comandos da fila</dd>
            </dl>
            <p class="form-text mb-0 mt-3">
                O comando da fila (<code>queue:work</code>) aparece aqui como qualquer outro, mas o "rodar agora" o recusa:
                ele é o próprio mecanismo que executa os jobs e prenderia a tela até terminar. O cron continua chamando-o no minuto dele.
            </p>
        </x-admin.card>
    </div>
</div>
@endsection
