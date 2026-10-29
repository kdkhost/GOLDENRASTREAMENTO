<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Segundo código — Golden Rastreamento</title>
    @include('partials.favicon')
    @include('admin.partials.theme-init')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    @include('partials.icones')
    <link rel="stylesheet" href="{{ asset('css/golden-theme.css') }}?v={{ filemtime(public_path('css/golden-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/golden-login.css') }}?v={{ filemtime(public_path('css/golden-login.css')) }}">
</head>
<body>
    <div class="box">
        <div class="brand">
            <x-site.img imagem="marca_logo" alt="Golden" :largura="false" :altura="false" :lazy="false" />
            <span><b>GOLDEN</b><small>Painel de Leads</small></span>
        </div>
        <h1>Falta o código do seu celular</h1>
        <p class="sub">A senha de <strong>{{ $nome }}</strong> abriu a primeira porta. Agora digite os seis dígitos que o aplicativo de autenticação mostra agora.</p>

        <form method="POST" action="{{ route('login.dois-fatores.post') }}">
            @csrf
            <div class="field">
                <label for="codigo">Código do aplicativo</label>
                <input id="codigo" type="text" name="codigo" autocomplete="one-time-code" maxlength="32" required autofocus placeholder="000000">
                @error('codigo')<span class="erro">{{ $message }}</span>@enderror
            </div>
            <button type="submit"><i class="fas fa-shield-halved me-1"></i> Entrar no painel</button>
        </form>

        <p class="dica">Sem o celular à mão? Um dos seus oito códigos de recuperação abre esta porta uma única vez — digite como está no papel: <code>ABCDE-12345</code>.</p>
        <p class="dica">Restam {{ $restam }} tentativa(s) antes deste acesso fechar e ter de recomeçar pela senha.</p>

        <form method="POST" action="{{ route('login.dois-fatores.cancelar') }}">
            @csrf
            <button type="submit" class="sutil">Cancelar e voltar ao login</button>
        </form>

        <div class="back"><a href="{{ route('site.home') }}">← Voltar ao site</a></div>
    </div>
@include('partials.avisos')
</body>
</html>
