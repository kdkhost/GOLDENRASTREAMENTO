{{--
    A barra de quem está de fora.

    Ela não é enfeite: é a única coisa na tela que diz que aquela conta não é sua. Fica acima do
    cabeçalho da página, dentro do fluxo normal do `app-main` — nada de posição fixa, nada de
    camada sobre o AdminLTE, porque um aviso que desloca o layout é um aviso que a pessoa fecha.

    O texto responde as três perguntas que ficam quando alguém abre a conta de outra pessoa: de
    quem é esta tela, quem a abriu e até quando, e o que acontece se eu mexer aqui. A resposta da
    última é "nada" — não por educação: `AcessoSupervisionado` recusa toda escrita da janela, e é
    o middleware, não esta barra, que garante a frase.
--}}
@php
    $janela = \App\Support\Supervisao::dados();
@endphp
@if($janela)
    @php
        $abertaEm = \Illuminate\Support\Carbon::createFromTimestamp($janela['desde']);
        $fechaEm = $abertaEm->copy()->addMinutes(\App\Services\Whmcs\Veiculacao::minutosDeSupervisao());
    @endphp
    <div class="container-fluid">
        <div class="alert alert-warning shadow-sm border-start border-warning border-4 d-flex flex-wrap align-items-center gap-3 mb-3" role="status">
            <i class="fas fa-user-shield fs-3" aria-hidden="true"></i>
            <div class="small flex-grow-1">
                <strong class="d-block">Supervisão aberta na conta de {{ $cliente->nome }} — modo somente leitura.</strong>
                Você está vendo o portal desta pessoa, tela por tela e com os mesmos filtros do financeiro que ela vê.
                Aberto por {{ $janela['admin_nome'] }} às {{ $abertaEm->format('d/m/Y H:i') }}; a janela fecha sozinha às {{ $fechaEm->format('H:i') }}.
                Nada aqui é gravado: chamado, pedido, cadastro, senha e depoimento ficam exatamente como estão.
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="{{ route('portal.supervisao.sair') }}" class="d-inline">
                    @csrf
                    <button class="btn btn-sm btn-warning text-nowrap"><i class="fas fa-arrow-right-from-bracket me-1" aria-hidden="true"></i> Sair da conta do cliente</button>
                </form>
                @can('admin')
                    <a class="btn btn-sm btn-outline-secondary text-nowrap" href="{{ route('admin.clientes.show', $janela['cliente_id']) }}">
                        <i class="fas fa-address-card me-1" aria-hidden="true"></i> Ir à ficha no painel
                    </a>
                @endcan
            </div>
        </div>
    </div>
@endif
