{{--
    A resposta visual do sistema, numa folha só. Todo retorno do que o usuário faz sai como
    toastr e toda confirmação de um passo sem volta sai como SweetAlert2 — no painel, no
    portal, na tela de entrada e no site público. Nenhuma view desenha caixa de aviso
    própria: quem precisa responder inclui esta folha e nada mais.

    'confirmacoes' => false desliga o SweetAlert2 (folha mais leve para quem só recebe).
--}}
@php $confirmacoes = $confirmacoes ?? true; @endphp
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<link rel="stylesheet" href="{{ asset('css/golden-avisos.css') }}?v={{ filemtime(public_path('css/golden-avisos.css')) }}">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
@if ($confirmacoes)
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endif
<script>
window.GoldenAvisos = (function () {
    toastr.options = {
        tapToDismiss: true, closeButton: true, newestOnTop: true, escapeHTML: true,
        positionClass: 'toast-top-right', showMethod: 'fadeIn', hideMethod: 'fadeOut',
        showDuration: 180, hideDuration: 220, timeOut: 4000, extendedTimeOut: 1500
    };

    var api = {
        sucesso: function (mensagem, link) {
            if (!link || !link.url) { toastr.success(String(mensagem || ''), ''); return; }
            //Montado pelo DOM: o texto continua escapado, só o link vira âncora de verdade.
            var caixa = document.createElement('div');
            caixa.appendChild(document.createTextNode(String(mensagem || '') + ' '));
            var ancora = document.createElement('a');
            ancora.href = String(link.url);
            ancora.target = '_blank';
            ancora.rel = 'noopener noreferrer';
            ancora.textContent = String(link.rotulo || 'Continuar');
            caixa.appendChild(ancora);
            toastr.success(caixa.outerHTML, '', { escapeHTML: false, timeOut: 8000, extendedTimeOut: 4000 });
        },
        info: function (mensagem) { toastr.info(String(mensagem || ''), ''); },
        aviso: function (mensagem) { toastr.warning(String(mensagem || ''), '', { timeOut: 6000, extendedTimeOut: 3000 }); },
        erro: function (mensagem) { toastr.error(String(mensagem || ''), '', { timeOut: 6000, extendedTimeOut: 3000 }); },
        erros: function (lista) {
            (lista || []).slice(0, 5).forEach(function (mensagem, i) {
                window.setTimeout(function () { api.erro(mensagem); }, i * 120);
            });
        },
        confirmar: function (pergunta, titulo) {
            if (!window.Swal) { return Promise.resolve(true); }
            return Swal.fire({
                icon: 'warning',
                title: titulo || 'Confirma esta ação?',
                text: String(pergunta || ''),
                showCancelButton: true,
                confirmButtonText: 'Sim, continuar',
                cancelButtonText: 'Voltar',
                reverseButtons: true,
                focusCancel: true
            }).then(function (resultado) { return !!resultado.isConfirmed; });
        }
    };

    return api;
})();

@if ($confirmacoes)
//Um formulário que pede confirmação só segue depois do "Sim, continuar" do SweetAlert2.
//O marcador impede que o segundo envio, disparado por requestSubmit, pergunte de novo.
document.addEventListener('submit', function (evento) {
    var form = evento.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirmar') || form.dataset.confirmado === '1') { return; }
    evento.preventDefault();
    GoldenAvisos.confirmar(form.getAttribute('data-confirmar')).then(function (aceitou) {
        if (!aceitou) { return; }
        form.dataset.confirmado = '1';
        form.requestSubmit();
    });
});
@endif

@foreach (['success' => 'sucesso', 'status' => 'sucesso', 'info' => 'info', 'warning' => 'aviso', 'error' => 'erro'] as $chave => $metodo)
    @if (session($chave))
        GoldenAvisos.{{ $metodo }}(@js(session($chave)));
    @endif
@endforeach
@if (isset($errors) && $errors->any())
    GoldenAvisos.erros(@js(collect($errors->all())->take(5)->values()->all()));
@endif
</script>
