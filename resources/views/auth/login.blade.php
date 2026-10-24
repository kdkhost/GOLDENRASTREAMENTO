<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acessar o painel — Golden Rastreamento</title>
    @include('partials.favicon')
    @include('admin.partials.theme-init')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/golden-theme.css') }}?v={{ filemtime(public_path('css/golden-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-login.css') }}?v={{ filemtime(public_path('css/golden-login.css')) }}">
</head>
<body>
    <div class="box">
        <div class="brand">
            <x-site.img imagem="marca_logo" alt="Golden" :largura="false" :altura="false" :lazy="false" />
            <span><b>GOLDEN</b><small>Painel de Leads</small></span>
        </div>
        <h1>Acessar o painel administrativo</h1>
        <p class="sub">Gestão de leads e conversões de campanhas</p>

        @if(session('status'))<div class="alert ok">{{ session('status') }}</div>@endif
        @if(session('success'))<div class="alert ok">{{ session('success') }}</div>@endif
        @if(session('warning'))<div class="alert error">{{ session('warning') }}</div>@endif
        @if(session('error'))<div class="alert error">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf
            <div class="field">
                <label for="email">E-mail</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            <div class="field">
                <label for="password">Senha</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <div class="row">
                <label class="remember"><input type="checkbox" name="remember" value="1"> Manter conectado</label>
            </div>
            <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> Entrar</button>
        </form>

        @if (app()->environment('local'))
        <div class="demo"><strong>Ambiente de demonstração</strong><br>admin@golden.com.br · senha: golden123</div>
        @endif
        <div class="back"><a href="{{ route('site.home') }}">← Voltar ao site</a></div>
    </div>
</body>
</html>
