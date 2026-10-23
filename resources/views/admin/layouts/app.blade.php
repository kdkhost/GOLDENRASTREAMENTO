<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Painel') | Golden Rastreamento</title>
    @include('partials.favicon')
    <script>(function(){var t=localStorage.getItem('golden_admin_theme')||'light';document.documentElement.setAttribute('data-bs-theme',t);})();</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="{{ asset('css/golden-theme.css') }}?v={{ filemtime(public_path('css/golden-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-to-top.css') }}?v={{ filemtime(public_path('css/golden-to-top.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-upload.css') }}?v={{ filemtime(public_path('css/golden-upload.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin-golden.css') }}?v={{ filemtime(public_path('css/admin-golden.css')) }}">
    @yield('styles')
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary">
<div class="app-wrapper">

    {{-- NAVBAR --}}
    <nav class="app-header navbar navbar-expand bg-body sticky-top">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="fas fa-bars"></i></a></li>
                <li class="nav-item d-none d-md-block"><a href="{{ route('site.home') }}" target="_blank" rel="noopener" class="nav-link"><i class="fas fa-up-right-from-square text-gold me-1"></i> Ver site</a></li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <li class="nav-item">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle" id="adminThemeToggle"><i class="fas fa-moon" id="adminThemeIcon"></i></button>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                        <span class="avatar-letter">{{ substr(auth()->user()->name, 0, 1) }}</span>
                        <span class="d-none d-md-inline fw-semibold">{{ auth()->user()->name }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><span class="dropdown-item-text small text-secondary">{{ auth()->user()->email }} · <strong>{{ ucfirst(auth()->user()->role) }}</strong></span></li>
                        <li><hr class="dropdown-divider"></li>
                        @can('admin')
                        <li><a class="dropdown-item" href="{{ route('admin.usuarios.index') }}"><i class="fas fa-user-shield me-2 text-gold"></i> Usuários &amp; permissões</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.settings.index') }}"><i class="fas fa-sliders me-2 text-gold"></i> Configurações</a></li>
                        @endcan
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="event.preventDefault();document.getElementById('formLogout').submit();"><i class="fas fa-arrow-right-from-bracket me-2"></i> Encerrar sessão</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    <form id="formLogout" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>

    {{-- SIDEBAR --}}
    @php $u = auth()->user(); @endphp
    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand d-flex align-items-center justify-content-between px-3 py-3 border-bottom border-secondary">
            <a href="{{ route('admin.dashboard') }}" class="brand-link text-decoration-none d-flex align-items-center gap-2">
                <x-site.img imagem="marca_logo" alt="Golden" :largura="false" :altura="34" class="brand-image" :lazy="false" />
                <span class="fw-bold text-white">GOLDEN</span>
                <span class="badge badge-menu tone-gold ms-1 small">{{ strtoupper($u->role) }}</span>
            </a>
            <button type="button" class="btn btn-sm btn-link text-white-50 d-lg-none p-1" data-lte-toggle="sidebar"><i class="fas fa-times fs-5"></i></button>
        </div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" role="menu">
                    @include('admin.partials.sidebar-menu')
                </ul>
            </nav>
        </div>
    </aside>

    {{-- CONTEÚDO --}}
    <main class="app-main">
        <div class="app-content-header mb-3">
            <div class="container-fluid">
                <div class="row align-items-center g-2">
                    <div class="col-lg-7">
                        <h3 class="mb-0 fw-bold text-body fs-4">@yield('page_title', 'Painel')</h3>
                        <p class="text-secondary small mb-0">@yield('page_description')</p>
                    </div>
                    <div class="col-lg-5"><div class="page-actions">@yield('page_actions')</div></div>
                </div>
            </div>
        </div>
        <div class="app-content"><div class="container-fluid">
            @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong><i class="fas fa-triangle-exclamation me-1"></i> Não deu para salvar:</strong>
                <ul class="mb-0 small mt-1">@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
            @endif
            @yield('content')
        </div></div>
    </main>

    <footer class="app-footer py-3">
        <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center small text-secondary px-4">
            <span>&copy; {{ date('Y') }} {{ $golden['marca']['nome'] }} — Painel de leads, CRM, CMS e ERP.</span>
            <span>PHP {{ PHP_VERSION }} · banco {{ config('database.default') }}</span>
        </div>
    </footer>
</div>

@include('partials.to-top')

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
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
        var confirmar = function(){ toastr.success('Link copiado.'); };
        if (navigator.clipboard) { navigator.clipboard.writeText(campo.value).then(confirmar, function(){ document.execCommand('copy'); confirmar(); }); }
        else { document.execCommand('copy'); confirmar(); }
    });

    toastr.options={timeOut:4000,positionClass:'toast-top-right'};    @foreach(['success'=>'success','error'=>'error','warning'=>'warning','info'=>'info'] as $k=>$fn)
        @if(session($k)) toastr.{{ $fn }}(@json(session($k))); @endif
    @endforeach
});
</script>
<script src="{{ asset('js/golden-to-top.js') }}?v={{ filemtime(public_path('js/golden-to-top.js')) }}"></script>
<script src="{{ asset('js/golden-upload.js') }}?v={{ filemtime(public_path('js/golden-upload.js')) }}"></script>
@yield('scripts')
@stack('scripts')
</body>
</html>
