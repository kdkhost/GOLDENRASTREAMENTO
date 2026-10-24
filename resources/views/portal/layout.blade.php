{{--
    Layout da área logada do portal: a mesma carcaça do painel administrativo
    (admin.layouts.app) — navbar, sidebar com treeview do AdminLTE, cabeçalho de página,
    rodapé e os scripts compartilhados de admin.partials.scripts. O que muda é o conteúdo
    da sidebar (config('golden.menu_portal')) e o usuário: aqui quem entra é a ficha do
    CRM pelo guard 'cliente', nunca um usuário do painel.
--}}
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal do cliente') | {{ $golden['marca']['nome'] }}</title>
    @include('partials.favicon')
    @include('admin.partials.theme-init')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="{{ asset('css/golden-theme.css') }}?v={{ filemtime(public_path('css/golden-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-to-top.css') }}?v={{ filemtime(public_path('css/golden-to-top.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin-golden.css') }}?v={{ filemtime(public_path('css/admin-golden.css')) }}">
    @yield('styles')
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary">
@php $cliente = auth()->guard('cliente')->user(); @endphp
<div class="app-wrapper">

    {{-- NAVBAR --}}
    <nav class="app-header navbar navbar-expand bg-body sticky-top">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="fas fa-bars"></i></a></li>
                <li class="nav-item d-none d-md-block"><a href="{{ route('site.home') }}" class="nav-link"><i class="fas fa-house text-gold me-1"></i> Ir ao site</a></li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <li class="nav-item">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle" id="adminThemeToggle"><i class="fas fa-moon" id="adminThemeIcon"></i></button>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                        <span class="avatar-letter">{{ mb_strtoupper(mb_substr(trim($cliente->nome ?? 'G'), 0, 1)) }}</span>
                        <span class="d-none d-md-inline fw-semibold">{{ str($cliente->nome)->explode(' ')->first() }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><span class="dropdown-item-text small text-secondary">{{ $cliente->email_portal }} · <strong>Área do cliente</strong></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('portal.dados') }}"><i class="fas fa-user-gear me-2 text-gold"></i> Meus dados</a></li>
                        <li><a class="dropdown-item" href="{{ route('site.contato') }}"><i class="fas fa-headset me-2 text-gold"></i> Falar com a equipe</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="event.preventDefault();document.getElementById('formSairPortal').submit();"><i class="fas fa-arrow-right-from-bracket me-2"></i> Encerrar sessão</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    <form id="formSairPortal" action="{{ route('portal.sair') }}" method="POST" class="d-none">@csrf</form>

    {{-- SIDEBAR --}}
    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand d-flex align-items-center justify-content-between px-3 py-3 border-bottom border-secondary">
            <a href="{{ route('portal.inicio') }}" class="brand-link text-decoration-none d-flex align-items-center gap-2">
                <x-site.img imagem="marca_logo" alt="Golden" :largura="false" :altura="34" class="brand-image" :lazy="false" />
                <span class="fw-bold text-white">GOLDEN</span>
                <span class="badge badge-menu tone-gold ms-1 small">PORTAL</span>
            </a>
            <button type="button" class="btn btn-sm btn-link text-white-50 d-lg-none p-1" data-lte-toggle="sidebar"><i class="fas fa-times fs-5"></i></button>
        </div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" role="menu">
                    @include('admin.partials.sidebar-menu', ['menu' => $menu_portal])
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
                        <h3 class="mb-0 fw-bold text-body fs-4">@yield('page_title', 'Portal do cliente')</h3>
                        <p class="text-secondary small mb-0">@yield('page_description')</p>
                    </div>
                    <div class="col-lg-5"><div class="page-actions">@yield('page_actions')</div></div>
                </div>
            </div>
        </div>
        <div class="app-content"><div class="container-fluid">
            @yield('content')
        </div></div>
    </main>

    <footer class="app-footer py-3">
        <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-2 small text-secondary px-4">
            <span>&copy; {{ date('Y') }} {{ $golden['marca']['nome'] }} — Área do cliente.</span>
            <span><a href="{{ route('site.contato') }}">Falar com a equipe</a> · <a href="{{ route('site.privacidade') }}">Privacidade (LGPD)</a></span>
        </div>
    </footer>
</div>

@include('partials.to-top')

@include('admin.partials.scripts')
<script src="{{ asset('js/golden-to-top.js') }}?v={{ filemtime(public_path('js/golden-to-top.js')) }}"></script>
@yield('scripts')
@stack('scripts')
</body>
</html>
