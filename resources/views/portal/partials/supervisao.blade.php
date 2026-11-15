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
    <aside class="supervisao-flutuante" role="status" aria-label="Modo de supervisão ativa">
        <div class="supervisao-flutuante-barra">
            <span class="supervisao-flutuante-icone" aria-hidden="true"><i class="fas fa-user-shield"></i></span>

            <details class="supervisao-detalhes">
                <summary title="Ver detalhes da janela de supervisão">
                    <span>Supervisão · {{ \Illuminate\Support\Str::limit($cliente->nome, 22) }}</span>
                    <span class="text-secondary fw-normal">até {{ $fechaEm->format('H:i') }}</span>
                    <i class="fas fa-circle-info text-secondary" aria-hidden="true"></i>
                </summary>
                <div class="supervisao-popover">
                    <strong class="d-block mb-1 text-body">Supervisão aberta na conta de {{ $cliente->nome }} — modo somente leitura.</strong>
                    <p class="text-secondary mb-0">
                        Você está vendo o portal desta pessoa, tela por tela e com os mesmos filtros do financeiro que ela vê.
                        Aberto por {{ $janela['admin_nome'] }} às {{ $abertaEm->format('d/m/Y H:i') }}; a janela fecha sozinha às {{ $fechaEm->format('H:i') }}.
                        Nada aqui é gravado: chamado, pedido, cadastro, senha e depoimento ficam exatamente como estão.
                    </p>
                </div>
            </details>

            <div class="d-flex align-items-center gap-1 flex-wrap">
                <form method="POST" action="{{ route('portal.supervisao.sair') }}" class="d-inline m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-golden">
                        <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i> Sair da conta do cliente
                    </button>
                </form>
                @can('admin')
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.clientes.show', $janela['cliente_id']) }}">
                        <i class="fas fa-address-card" aria-hidden="true"></i> Ir à ficha no painel
                    </a>
                @endcan
            </div>
        </div>
    </aside>
@endif
