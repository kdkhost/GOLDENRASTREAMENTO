<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Painel') | Golden Rastreamento</title>
    <link rel="icon" href="{{ asset('img/logo-golden.png') }}">
    <script>(function(){var t=localStorage.getItem('golden_admin_theme')||'light';document.documentElement.setAttribute('data-bs-theme',t);})();</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin-golden.css') }}?v={{ filemtime(public_path('css/admin-golden.css')) }}">
    <style>
        :root{--bs-primary:#c9862a;--bs-primary-rgb:201,134,42;--gold:#e0a33e;}
    </style>
    @yield('styles')
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary">
<div class="app-wrapper">

    {{-- NAVBAR --}}
    <nav class="app-header navbar navbar-expand bg-body sticky-top">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="fas fa-bars"></i></a></li>
                <li class="nav-item d-none d-md-block"><a href="{{ route('site.home') }}" target="_blank" class="nav-link"><i class="fas fa-external-link-alt text-warning me-1"></i> Ver site</a></li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <li class="nav-item">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle" id="adminThemeToggle"><i class="fas fa-moon" id="adminThemeIcon"></i></button>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                        <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:32px;height:32px">{{ substr(auth()->user()->name,0,1) }}</div>
                        <span class="d-none d-md-inline fw-semibold">{{ auth()->user()->name }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><span class="dropdown-item-text small text-secondary">{{ auth()->user()->email }} · <strong>{{ ucfirst(auth()->user()->role) }}</strong></span></li>
                        <li><hr class="dropdown-divider"></li>
                        @if(auth()->user()->isAdmin())
                        <li><a class="dropdown-item" href="{{ route('admin.usuarios.index') }}"><i class="fas fa-users-cog me-2 text-warning"></i> Usuários &amp; Permissões</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.settings.index') }}"><i class="fas fa-sliders-h me-2 text-warning"></i> Configurações</a></li>
                        @endif
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="event.preventDefault();document.getElementById('formLogout').submit();"><i class="fas fa-sign-out-alt me-2"></i> Encerrar sessão</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    <form id="formLogout" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>

    {{-- SIDEBAR --}}
    @php $u = auth()->user(); $novos = \App\Models\Lead::where('status','novo')->count(); @endphp
    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand d-flex align-items-center justify-content-between px-3 py-3 border-bottom border-secondary">
            <a href="{{ route('admin.dashboard') }}" class="brand-link text-decoration-none d-flex align-items-center gap-2">
                <img src="{{ asset('img/logo-golden.png') }}" alt="Golden" height="34" class="brand-image">
                <span class="fw-bold text-white">GOLDEN</span>
                <span class="badge bg-warning text-dark ms-1 small">{{ strtoupper($u->role) }}</span>
            </a>
            <button type="button" class="btn btn-sm btn-link text-white-50 d-lg-none p-1" data-lte-toggle="sidebar"><i class="fas fa-times fs-5"></i></button>
        </div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" role="menu">
                    <li class="nav-item"><a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>

                    <li class="nav-header">Comercial</li>
                    <li class="nav-item"><a href="{{ route('admin.leads.index') }}" class="nav-link {{ request()->routeIs('admin.leads.*') ? 'active' : '' }}"><i class="nav-icon fas fa-users"></i><p>Leads @if($novos>0)<span class="badge bg-warning text-dark ms-auto">{{ $novos }}</span>@endif</p></a></li>
                    <li class="nav-item"><a href="{{ route('admin.campanhas.index') }}" class="nav-link {{ request()->routeIs('admin.campanhas.*') ? 'active' : '' }}"><i class="nav-icon fas fa-bullhorn"></i><p>Campanhas</p></a></li>

                    @if($u->isAdmin())
                    <li class="nav-header">Administração</li>
                    <li class="nav-item"><a href="{{ route('admin.activities.index') }}" class="nav-link {{ request()->routeIs('admin.activities.*') ? 'active' : '' }}"><i class="nav-icon fas fa-shield-halved"></i><p>Auditoria</p></a></li>
                    <li class="nav-item"><a href="{{ route('admin.usuarios.index') }}" class="nav-link {{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}"><i class="nav-icon fas fa-user-gear"></i><p>Usuários</p></a></li>
                    <li class="nav-item"><a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"><i class="nav-icon fas fa-sliders-h"></i><p>Configurações</p></a></li>
                    @endif
                </ul>
            </nav>
        </div>
    </aside>

    {{-- CONTEÚDO --}}
    <main class="app-main">
        <div class="app-content-header mb-3">
            <div class="container-fluid">
                <div class="row align-items-center g-2">
                    <div class="col-md-6"><h3 class="mb-0 fw-bold text-body fs-4">@yield('page_title','Painel')</h3><p class="text-secondary small mb-0">@yield('page_description','')</p></div>
                    <div class="col-md-6 text-md-end mt-2 mt-md-0">@yield('page_actions')</div>
                </div>
            </div>
        </div>
        <div class="app-content"><div class="container-fluid">@yield('content')</div></div>
    </main>

    <footer class="app-footer py-3">
        <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center small text-secondary px-4">
            <span>&copy; {{ date('Y') }} Golden Rastreamento — Painel de Leads.</span>
            <span>SQLite (local) · MySQL (produção)</span>
        </div>
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
$(function(){
    var theme = localStorage.getItem('golden_admin_theme')||'light';
    function apply(t){ if(t==='dark'){document.documentElement.setAttribute('data-bs-theme','dark');$('body').addClass('dark-mode').attr('data-bs-theme','dark');$('#adminThemeIcon').removeClass('fa-moon').addClass('fa-sun');}else{document.documentElement.setAttribute('data-bs-theme','light');$('body').removeClass('dark-mode').attr('data-bs-theme','light');$('#adminThemeIcon').removeClass('fa-sun').addClass('fa-moon');} }
    apply(theme);
    $('#adminThemeToggle').on('click',function(){theme=theme==='dark'?'light':'dark';localStorage.setItem('golden_admin_theme',theme);apply(theme);});

    toastr.options={timeOut:4000,positionClass:'toast-top-right'};
    @foreach(['success'=>'success','error'=>'error','warning'=>'warning','info'=>'info'] as $k=>$fn)
        @if(session($k)) toastr.{{ $fn }}(@json(session($k))); @endif
    @endforeach
});
</script>
@yield('scripts')
@stack('scripts')
</body>
</html>
