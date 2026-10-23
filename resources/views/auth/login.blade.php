<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acessar o painel — Golden Rastreamento</title>
    <link rel="icon" href="{{ asset('img/logo-golden.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:"Inter",system-ui,sans-serif;min-height:100vh;display:grid;place-items:center;background:radial-gradient(900px 500px at 80% -10%,rgba(31,158,134,.25),transparent 60%),linear-gradient(160deg,#12161a,#0d1114);color:#fff;padding:20px}
        .box{width:100%;max-width:410px;background:#fff;color:#12161a;border-radius:20px;padding:38px 34px;box-shadow:0 30px 70px -30px rgba(0,0,0,.7)}
        .brand{display:flex;align-items:center;gap:12px;justify-content:center;margin-bottom:8px}
        .brand img{width:48px;height:48px;border-radius:11px}
        .brand b{font-family:"Poppins";font-size:1.3rem;letter-spacing:.02em}
        .brand small{display:block;font-size:.6rem;letter-spacing:.22em;color:#c9862a;text-transform:uppercase}
        h1{font-family:"Poppins";font-size:1.15rem;text-align:center;margin:6px 0 4px}
        .sub{text-align:center;color:#5b6672;font-size:.9rem;margin-bottom:26px}
        label{display:block;font-weight:600;font-size:.85rem;margin-bottom:7px}
        .field{margin-bottom:16px}
        input[type=email],input[type=password]{width:100%;padding:13px 15px;border:1.5px solid #e6e9ee;border-radius:11px;font:inherit}
        input:focus{outline:none;border-color:#e0a33e;box-shadow:0 0 0 4px rgba(224,163,62,.14)}
        .row{display:flex;justify-content:space-between;align-items:center;margin:4px 0 20px;font-size:.86rem}
        .row a{color:#c9862a;text-decoration:none}
        button{width:100%;padding:14px;border:0;border-radius:11px;background:linear-gradient(135deg,#e0a33e,#c9862a);color:#241a06;font-weight:700;font-size:1rem;cursor:pointer;font-family:"Poppins"}
        button:hover{filter:brightness(1.05)}
        .alert{padding:12px 14px;border-radius:10px;font-size:.86rem;margin-bottom:18px}
        .alert.error{background:#fdecec;color:#9b2c2c;border:1px solid #f5c2c2}
        .alert.ok{background:#e7f6ef;color:#12694f;border:1px solid #b8e6d3}
        .back{text-align:center;margin-top:20px;font-size:.85rem;color:#5b6672}
        .back a{color:#5b6672}
        .demo{margin-top:16px;font-size:.78rem;color:#5b6672;background:#f6f8fa;border:1px dashed #d7dde4;border-radius:10px;padding:10px 12px;text-align:center}
    </style>
</head>
<body>
    <div class="box">
        <div class="brand">
            <img src="{{ asset('img/logo-golden.png') }}" alt="Golden">
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
                <label style="font-weight:500;display:flex;gap:7px;align-items:center;margin:0"><input type="checkbox" name="remember" value="1"> Manter conectado</label>
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
