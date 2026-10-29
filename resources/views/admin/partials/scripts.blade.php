{{-- Scripts que todo painel precisa: o retorno e a confirmação compartilhados (partials.avisos),
     os bibliotecários do AdminLTE, o botão de tema, o accordion da sidebar e o "Copiar" das
     listas. Painel administrativo e portal do cliente carregam esta mesma folha — é por ela que
     os dois se comportam igual, e é aqui (e só aqui) que se corrige um comportamento dos dois. --}}
@include('partials.avisos')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>
<script>
$(function(){
    var theme = localStorage.getItem('golden_admin_theme')||'light';
    function apply(t){ if(t==='dark'){document.documentElement.setAttribute('data-bs-theme','dark');$('body').addClass('dark-mode').attr('data-bs-theme','dark');$('#adminThemeIcon').removeClass('fa-moon').addClass('fa-sun');}else{document.documentElement.setAttribute('data-bs-theme','light');$('body').removeClass('dark-mode').attr('data-bs-theme','light');$('#adminThemeIcon').removeClass('fa-sun').addClass('fa-moon');} $(document).trigger('golden:theme',[t]); }
    apply(theme);
    $('#adminThemeToggle').on('click',function(){theme=theme==='dark'?'light':'dark';localStorage.setItem('golden_admin_theme',theme);apply(theme);});

    // O plugin de treeview faz o accordion (um grupo aberto por vez) mas não mexe no
    // aria-expanded, que precisa acompanhar o estado para o leitor de tela. Os eventos
    // dele têm o ponto no próprio nome ('expanded.lte.treeview') e não sobem, então a
    // escuta é em cada item, não delegada na lista.
    document.querySelectorAll('.sidebar-menu .nav-item').forEach(function(li){
        var link = li.querySelector(':scope > .nav-link');
        if (!link || !li.querySelector(':scope > .nav-treeview')) return;
        li.addEventListener('expanded.lte.treeview', function(){ link.setAttribute('aria-expanded','true'); });
        li.addEventListener('collapsed.lte.treeview', function(){ link.setAttribute('aria-expanded','false'); });
    });

    // Todo botão "Copiar" do painel (link do pedido, URL rastreada da campanha) age sobre
    // o campo declarado em data-copiar. Escuta delegada: a página não precisa se lembrar de chamá-la.
    document.addEventListener('click', function(e){
        var btn = e.target instanceof Element ? e.target.closest('[data-copiar]') : null;
        if (!btn) return;
        var campo = document.querySelector(btn.getAttribute('data-copiar'));
        if (!campo) return;
        campo.select();
        var confirmar = function(){ GoldenAvisos.sucesso('Link copiado.'); };
        if (navigator.clipboard) { navigator.clipboard.writeText(campo.value).then(confirmar, function(){ document.execCommand('copy'); confirmar(); }); }
        else { document.execCommand('copy'); confirmar(); }
    });
});
</script>
{{-- A gravação em segundo plano do painel e do portal. Uma folha só, chamada aqui, porque é aqui
     que os dois layouts se encontram: todo formulário de escrita das duas áreas passa por ela sem
     que a view precise chamar nada — e nenhuma tela volta a piscar depois de salvar. --}}
<script src="{{ asset('js/golden-salvar.js') }}?v={{ filemtime(public_path('js/golden-salvar.js')) }}"></script>
