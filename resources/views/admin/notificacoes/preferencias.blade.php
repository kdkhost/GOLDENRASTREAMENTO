@extends('admin.layouts.app')
@section('title', 'Preferências das notificações')
@section('page_title', 'Preferências das notificações')
@section('page_description', 'Escolha o que aparece no seu sino. Esta tela não muda nada do que sai por e-mail para a equipe.')

@section('page_actions')
<a href="{{ route('admin.notificacoes.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar à central</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="O que eu quero receber" icon="fa-bell"
            descricao="Desligado aqui, o aviso não entra no seu sino — os de outras pessoas e o e-mail da equipe continuam iguais.">
            <form method="POST" action="{{ route('admin.notificacoes.preferencias.salvar') }}" class="row g-3">
                @csrf

                @foreach($tipos as $t)
                    <div class="col-md-6">
                        <div class="form-check form-switch pt-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="tipo-{{ $t['tipo'] }}"
                                name="tipos[]" value="{{ $t['tipo'] }}" @checked($t['ativo'])>
                            <label class="form-check-label" for="tipo-{{ $t['tipo'] }}">
                                <span class="d-inline-flex align-items-center gap-2">
                                    <span class="sino-icone tone-{{ $t['tom'] }}"><i class="fas {{ $t['icone'] }}" aria-hidden="true"></i></span>
                                    <span class="fw-semibold">{{ $t['rotulo'] }}</span>
                                </span>
                                <span class="form-hint d-block">{{ $t['audiencia'] }}</span>
                            </label>
                        </div>
                    </div>
                @endforeach

                @can('admin')
                    <div class="col-12"><div class="zone-title mt-2">Prazos da central</div></div>

                    <x-admin.field name="retencao" type="number" label="Guardar as lidas por (dias)" :value="$retencao" col="col-md-6"
                        min="1" max="365" hint="Depois disto, a poda apaga a notificação lida — de todo mundo." />

                    <x-admin.field name="expiracao" type="number" label="Apagar as não lidas depois de (dias)" :value="$expiracao" col="col-md-6"
                        min="1" max="365" hint="Vale para os tipos sem prazo próprio no catálogo." />
                @endcan

                <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                    <button class="btn btn-golden"><i class="fas fa-save me-1"></i> Salvar preferências</button>
                    <a href="{{ route('admin.notificacoes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Como a central funciona" icon="fa-circle-info">
            <dl class="defs mb-0">
                <dt>Ler</dt>
                <dd>tira a notificação do sino</dd>
                <dt>Histórico</dt>
                <dd>continua na página da central</dd>
                <dt>Sino</dt>
                <dd>mostra só as suas não lidas</dd>
                <dt>E-mail</dt>
                <dd>fora desta tela, sem mudança</dd>
            </dl>
            <p class="form-text mb-0 mt-3">
                Cada pessoa tem o próprio sino e a própria leitura: o que uma abre não some da central das outras.
                A poda roda pela agenda do painel e apaga só o que já foi lido ou venceu.
            </p>
        </x-admin.card>
    </div>
</div>
@endsection
