{{-- A entrada da conta no topo: um ícone, não texto. Passar o mouse — ou tabular até ele —
     abre as duas portas: o portal da plataforma e o financeiro antigo. No celular o toque
     faz o mesmo, e a gaveta repete as opções em texto para quem não vê só um ícone. --}}
<div class="nav-acesso" data-acesso>
    <button type="button" class="nav-login" id="acessoConta" aria-expanded="false" aria-controls="acessoContaMenu" aria-label="Acessar minha conta" title="Acessar minha conta">
        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
    </button>
    <div class="nav-acesso-menu" id="acessoContaMenu">
        <a href="{{ $golden['portal_url'] }}">
            <span class="nav-acesso-icone"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
            <span class="nav-acesso-texto"><b>Painel do cliente</b><small>Pedidos, chamados e contratos</small></span>
        </a>
        @if($golden['acessos']['financeiro_url'])
            <a href="{{ $golden['acessos']['financeiro_url'] }}" target="_blank" rel="noopener">
                <span class="nav-acesso-icone"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i></span>
                <span class="nav-acesso-texto"><b>Financeiro antigo</b><small>Abrir o sistema financeiro</small></span>
                <i class="fa-solid fa-arrow-up-right-from-square nav-acesso-seta" aria-hidden="true"></i>
            </a>
        @endif
    </div>
</div>
