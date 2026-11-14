<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $dados['title'] ?? 'Erro' }} | Golden Rastreamento</title>
    <link rel="icon" type="image/png" href="{{ asset('img/favicon.png') }}">
    @include('partials.icones')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --bg-base: #07090e;
            --bg-card: rgba(16, 21, 33, 0.85);
            --border-card: rgba(245, 179, 1, 0.18);
            --gold: #f5b301;
            --gold-light: #ffd056;
            --gold-glow: rgba(245, 179, 1, 0.25);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
            --font-main: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font-main);
            background: var(--bg-base);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            padding: 24px 16px;
        }
        /* Background atmosphere */
        .bg-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(to right, rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
            z-index: 1;
        }
        .glow-sphere {
            position: absolute;
            top: 25%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 550px;
            height: 550px;
            background: radial-gradient(circle, var(--gold-glow) 0%, rgba(7, 9, 14, 0) 70%);
            filter: blur(60px);
            pointer-events: none;
            z-index: 1;
        }
        .error-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 640px;
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 24px;
            padding: 44px 36px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 30px -5px var(--gold-glow);
            backdrop-filter: blur(14px);
        }
        .brand-logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 28px;
            text-decoration: none;
        }
        .brand-logo img {
            height: 38px;
            width: auto;
            object-fit: contain;
        }
        .brand-logo span {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: .04em;
            color: #fff;
            text-transform: uppercase;
        }
        .brand-logo span b {
            color: var(--gold);
        }
        .code-badge-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }
        .code-badge {
            display: inline-block;
            font-size: 4.8rem;
            font-weight: 900;
            line-height: 1;
            letter-spacing: 2px;
            background: linear-gradient(135deg, #ffffff 10%, var(--gold) 55%, var(--gold-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 35px var(--gold-glow);
        }
        .icon-circle {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(245, 179, 1, 0.1);
            border: 1px solid rgba(245, 179, 1, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.8rem;
            color: var(--gold);
            animation: pulse-ring 3s infinite;
        }
        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(245, 179, 1, 0.4); }
            70% { box-shadow: 0 0 0 16px rgba(245, 179, 1, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 179, 1, 0); }
        }
        h1.title {
            font-size: 1.85rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }
        p.subtitle {
            font-size: 1.05rem;
            font-weight: 500;
            color: var(--gold);
            margin-bottom: 18px;
            line-height: 1.45;
        }
        p.message {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 32px;
            max-width: 520px;
            margin-left: auto;
            margin-right: auto;
        }
        .actions-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            align-items: center;
            margin-bottom: 28px;
        }
        .btn-custom {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 13px 26px;
            font-size: 0.95rem;
            font-weight: 700;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.25s ease;
            cursor: pointer;
            border: none;
        }
        .btn-primary-golden {
            background: linear-gradient(135deg, var(--gold) 0%, #d89600 100%);
            color: #0b0e14;
            box-shadow: 0 4px 18px rgba(245, 179, 1, 0.35);
        }
        .btn-primary-golden:hover {
            background: linear-gradient(135deg, #ffc424 0%, var(--gold) 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(245, 179, 1, 0.5);
            color: #0b0e14;
        }
        .btn-whatsapp {
            background: rgba(37, 211, 102, 0.15);
            border: 1px solid rgba(37, 211, 102, 0.4);
            color: #25d366;
        }
        .btn-whatsapp:hover {
            background: #25d366;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.35);
        }
        .footer-note {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 20px;
            font-size: 0.82rem;
            color: var(--text-dim);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .footer-note a {
            color: var(--text-muted);
            text-decoration: none;
        }
        .footer-note a:hover {
            color: var(--gold);
        }
        @media (max-width: 600px) {
            .error-container { padding: 32px 20px; border-radius: 18px; }
            .code-badge { font-size: 3.8rem; }
            h1.title { font-size: 1.5rem; }
            .btn-custom { width: 100%; justify-content: center; }
            .footer-note { justify-content: center; text-align: center; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="bg-grid"></div>
    <div class="glow-sphere"></div>

    <div class="error-container">
        <a href="{{ url('/') }}" class="brand-logo" title="Golden Rastreamento">
            <img src="{{ asset('img/logo-golden.png') }}" alt="Golden Rastreamento" onerror="this.style.display='none'">
            <span>Golden <b>Rastreamento</b></span>
        </a>

        @if(!empty($dados['icon']))
            @php
                $iconeRaw = trim($dados['icon']);
                if (!str_contains($iconeRaw, 'fa-solid') && !str_contains($iconeRaw, 'fa-regular') && !str_contains($iconeRaw, 'fa-brands') && !str_contains($iconeRaw, 'fas') && !str_contains($iconeRaw, 'far') && !str_contains($iconeRaw, 'fab')) {
                    $iconeRaw = 'fa-solid ' . (str_starts_with($iconeRaw, 'fa-') ? $iconeRaw : 'fa-' . $iconeRaw);
                }
            @endphp
            <div class="icon-circle">
                <i class="{{ $iconeRaw }}"></i>
            </div>
        @endif

        <div class="code-badge-wrap">
            <span class="code-badge">{{ $dados['code'] ?? '404' }}</span>
        </div>

        <h1 class="title">{{ $dados['title'] ?? 'Erro no Sistema' }}</h1>

        @if(!empty($dados['subtitle']))
            <p class="subtitle">{{ $dados['subtitle'] }}</p>
        @endif

        @if(!empty($dados['message']))
            <p class="message">{{ $dados['message'] }}</p>
        @endif

        @yield('conteudo_extra')

        <div class="actions-group">
            @if(!empty($dados['primary_button_text']))
                <a href="{{ $dados['primary_button_url'] ?? url('/') }}" class="btn-custom btn-primary-golden">
                    <i class="fas fa-arrow-left"></i> {{ $dados['primary_button_text'] }}
                </a>
            @endif

            @if(!empty($dados['show_whatsapp_button']))
                @php
                    $whatsappNumero = preg_replace('/[^\d]/', '', (string) \App\Models\Setting::get('contato_whatsapp', '5519997101483'));
                    $msgWhatsapp = rawurlencode($dados['whatsapp_custom_text'] ?? "Olá! Tive um imprevisto com o código {$dados['code']} no site da Golden Rastreamento e gostaria de suporte.");
                @endphp
                <a href="https://wa.me/{{ $whatsappNumero }}?text={{ $msgWhatsapp }}" target="_blank" rel="noopener noreferrer" class="btn-custom btn-whatsapp">
                    <i class="fab fa-whatsapp"></i> Falar com Suporte
                </a>
            @endif
        </div>

        <div class="footer-note">
            <span>&copy; {{ date('Y') }} Golden Rastreamento Veicular.</span>
            <span><i class="fas fa-shield-alt text-gold"></i> Sistema Protegido</span>
        </div>
    </div>

    @stack('scripts')
</body>
</html>

