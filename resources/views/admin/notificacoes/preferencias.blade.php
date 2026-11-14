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
                    <div class="col-12"><div class="zone-title mt-3"><i class="fas fa-mobile-screen me-1 text-golden"></i> Alertas Direto no Celular (Telegram / WhatsApp / Push)</div></div>

                    <x-admin.switch name="celular_ativo" label="Ativar envio de alertas instantâneos direto no celular do administrador" :checked="$celular_ativo"
                        hint="Receba notificações em tempo real com som e vibração no smartphone sempre que houver novo lead, venda, depoimento, coleta ou entrega." />

                    <x-admin.field name="telegram_token" type="text" label="Token do Bot do Telegram" :value="$telegram_token" col="col-md-7"
                        placeholder="Ex: 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ" hint="Crie gratuitamente no @BotFather no Telegram em menos de 1 minuto." />

                    <x-admin.field name="telegram_chat_id" type="text" label="Chat ID do Telegram" :value="$telegram_chat_id" col="col-md-5"
                        placeholder="Ex: 123456789" hint="Seu ID de usuário ou do grupo no Telegram (consulte no @userinfobot)." />

                    <x-admin.field name="webhook_celular" type="url" label="Webhook Móvel / WhatsApp (opcional)" :value="$webhook_celular" col="col-12"
                        placeholder="https://api.seugateway.com/webhook..." hint="URL para receber POST JSON caso utilize WhatsApp API (Evolution, Z-API), N8N ou Discord." />

                    <div class="col-12"><div class="zone-title mt-3"><i class="fas fa-envelope me-1 text-golden"></i> Alertas por E-mail</div></div>

                    <x-admin.switch name="email_ativo" label="Enviar alertas para a caixa de e-mail da empresa" :checked="$email_ativo"
                        hint="Dispara e-mails transacionais instantâneos detalhando os acontecimentos da empresa." />

                    <x-admin.field name="email_destinatarios" type="text" label="Destinatários de e-mail (separados por vírgula)" :value="$email_destinatarios" col="col-12"
                        placeholder="admin@golden.com.br, comercial@goldenrastreamento.com.br" hint="Se vazio, utiliza o e-mail da equipe da loja ou o do administrador cadastrado." />

                    <div class="col-12"><div class="zone-title mt-3"><i class="fas fa-clock-rotate-left me-1 text-golden"></i> Prazos da central de notificações</div></div>

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

            @can('admin')
                <div class="border-top pt-3 mt-4">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <form method="POST" action="{{ route('admin.notificacoes.testar.celular') }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary" type="submit" title="Dispara uma notificação de teste para o Telegram / Webhook móvel">
                                <i class="fas fa-mobile-screen-button me-1"></i> ⚡ Testar Alerta no Celular Agora
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.notificacoes.testar.email') }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary" type="submit" title="Dispara um e-mail de teste para os destinatários configurados">
                                <i class="fas fa-paper-plane me-1"></i> 📧 Testar Alerta por E-mail
                            </button>
                        </form>
                    </div>
                </div>
            @endcan
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Notificações Push no Celular" icon="fa-mobile-screen" class="mb-3">
            <p class="small text-secondary mb-3">
                Ative as notificações nativas no navegador do seu smartphone para receber alertas na tela de bloqueio e avisos sonoros instantâneos.
            </p>
            <div id="pushStatusWrapper" class="mb-2">
                <span id="pushStatusBadge" class="badge bg-secondary mb-2">Verificando suporte...</span>
            </div>
            <button type="button" id="btnAtivarPush" class="btn btn-golden btn-sm w-100">
                <i class="fas fa-bell me-1"></i> Ativar Notificações no Celular
            </button>
        </x-admin.card>

        <x-admin.card title="Como Configurar o Telegram (1 Min)" icon="fa-paper-plane" class="mb-3">
            <ol class="small text-secondary ps-3 mb-0" style="line-height:1.7">
                <li>No Telegram do seu celular, abra <strong>@BotFather</strong>.</li>
                <li>Envie <code>/newbot</code> e escolha um nome (ex: <em>GoldenAlertasBot</em>).</li>
                <li>Copie o <strong>Token</strong> gerado e cole no campo ao lado.</li>
                <li>Abra <strong>@userinfobot</strong> para ver seu <strong>Chat ID</strong> numérico e cole no campo.</li>
                <li>Clique em <strong>Salvar</strong> e depois em <strong>⚡ Testar Alerta no Celular</strong>.</li>
            </ol>
        </x-admin.card>

        <x-admin.card title="Como a central funciona" icon="fa-circle-info">
            <dl class="defs mb-0">
                <dt>Ler</dt>
                <dd>tira a notificação do sino</dd>
                <dt>Histórico</dt>
                <dd>continua na página da central</dd>
                <dt>Sino</dt>
                <dd>mostra só as suas não lidas</dd>
                <dt>Celular / E-mail</dt>
                <dd>alertas imediatos em tempo real</dd>
            </dl>
            <p class="form-text mb-0 mt-3">
                Cada pessoa tem o próprio sino e a própria leitura: o que uma abre não some da central das outras.
                A poda roda pela agenda do painel e apaga só o que já foi lido ou venceu.
            </p>
        </x-admin.card>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('btnAtivarPush');
    var badge = document.getElementById('pushStatusBadge');

    if (!('Notification' in window)) {
        if (badge) {
            badge.className = 'badge bg-warning text-dark';
            badge.textContent = 'Navegador sem suporte a Push';
        }
        if (btn) btn.disabled = true;
        return;
    }

    function atualizarBadge() {
        if (!badge) return;
        if (Notification.permission === 'granted') {
            badge.className = 'badge bg-success';
            badge.innerHTML = '<i class="fas fa-check-circle me-1"></i> Notificações ativas neste dispositivo';
            if (btn) {
                btn.className = 'btn btn-outline-success btn-sm w-100';
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Notificações já ativadas (Clique para testar)';
            }
        } else if (Notification.permission === 'denied') {
            badge.className = 'badge bg-danger';
            badge.innerHTML = '<i class="fas fa-ban me-1"></i> Notificações bloqueadas no navegador';
        } else {
            badge.className = 'badge bg-secondary';
            badge.textContent = 'Permissão pendente';
        }
    }

    atualizarBadge();

    if (btn) {
        btn.addEventListener('click', function () {
            Notification.requestPermission().then(function (perm) {
                atualizarBadge();
                if (perm === 'granted') {
                    try {
                        new Notification('Golden Rastreamento', {
                            body: '🔔 Notificações ativadas no seu celular! Você receberá avisos em tempo real de vendas, leads e entregas.',
                            icon: '/favicon.ico'
                        });
                    } catch(e) {}
                }
            });
        });
    }
});
</script>
@endpush
@endsection
