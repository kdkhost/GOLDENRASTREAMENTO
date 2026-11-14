<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="theme-color" content="#07090e">
    <title>{{ $dados['title'] ?? 'Erro' }} | Golden Rastreamento</title>
    @include('partials.favicon')

    {{-- Tipografia moderna do ecossistema Golden --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">

    {{-- Ícones FontAwesome (local com fallback seguro para cdnjs) --}}
    @include('partials.icones')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        :root {
            --bg-void: #06080d;
            --bg-panel: rgba(14, 18, 28, 0.82);
            --bg-panel-hover: rgba(18, 24, 38, 0.9);
            --border-panel: rgba(245, 179, 1, 0.22);
            --border-subtle: rgba(255, 255, 255, 0.08);
            --gold: #f5b301;
            --gold-light: #ffd056;
            --gold-deep: #d89600;
            --gold-glow: rgba(245, 179, 1, 0.28);
            --teal: #1f9e86;
            --text-white: #ffffff;
            --text-heading: #f8fafc;
            --text-slate: #94a3b8;
            --text-dim: #64748b;
            --font-display: "Poppins", "Segoe UI", system-ui, sans-serif;
            --font-body: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --radius-card: clamp(20px, 3.5vw, 28px);
            --radius-btn: 9999px;
            --transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            min-height: 100%;
            width: 100%;
            background-color: var(--bg-void);
            color: var(--text-heading);
            font-family: var(--font-body);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            padding: clamp(20px, 4vw, 40px) 16px;
            min-height: 100vh;
            min-height: 100dvh;
        }

        /* Atmosfera de fundo: Grid com gradiente espacial de alta precisão */
        .err-bg-atmosphere {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .err-bg-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(to right, rgba(255, 255, 255, 0.025) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
            background-size: clamp(32px, 5vw, 48px) clamp(32px, 5vw, 48px);
            mask-image: radial-gradient(circle at 50% 50%, #000 30%, transparent 85%);
            -webkit-mask-image: radial-gradient(circle at 50% 50%, #000 30%, transparent 85%);
        }

        .err-glow-orb-primary {
            position: absolute;
            top: 20%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: clamp(340px, 60vw, 680px);
            height: clamp(340px, 60vw, 680px);
            background: radial-gradient(circle, rgba(245, 179, 1, 0.18) 0%, rgba(245, 179, 1, 0.03) 45%, transparent 70%);
            filter: blur(80px);
            border-radius: 50%;
            animation: pulse-ambient 8s ease-in-out infinite alternate;
        }

        .err-glow-orb-secondary {
            position: absolute;
            bottom: 5%;
            left: 50%;
            transform: translateX(-50%);
            width: clamp(280px, 50vw, 550px);
            height: clamp(220px, 40vw, 400px);
            background: radial-gradient(ellipse at center, rgba(31, 158, 134, 0.14) 0%, transparent 65%);
            filter: blur(90px);
            border-radius: 50%;
        }

        @keyframes pulse-ambient {
            0% { transform: translate(-50%, -50%) scale(0.95); opacity: 0.8; }
            100% { transform: translate(-50%, -50%) scale(1.08); opacity: 1; }
        }

        /* Cartão Glassmorphism Central */
        .err-card {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 660px;
            background: var(--bg-panel);
            border: 1px solid var(--border-panel);
            border-top: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: var(--radius-card);
            padding: clamp(32px, 5.5vw, 52px) clamp(22px, 5vw, 44px);
            text-align: center;
            box-shadow:
                0 30px 80px -15px rgba(0, 0, 0, 0.9),
                0 0 50px -10px var(--gold-glow);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px);
            transition: var(--transition);
        }

        /* Cabeçalho da marca e selo de status */
        .err-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: clamp(24px, 4vw, 36px);
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-subtle);
        }

        .err-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: transform 0.2s ease;
        }

        .err-brand:hover {
            transform: translateY(-1px);
        }

        .err-brand img {
            height: clamp(32px, 5vw, 40px);
            width: auto;
            object-fit: contain;
        }

        .err-brand span {
            font-family: var(--font-display);
            font-size: clamp(0.98rem, 2.2vw, 1.15rem);
            font-weight: 800;
            letter-spacing: 0.05em;
            color: var(--text-white);
            text-transform: uppercase;
        }

        .err-brand span b {
            color: var(--gold);
        }

        .err-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 12px;
            border-radius: 9999px;
            background: rgba(31, 158, 134, 0.12);
            border: 1px solid rgba(31, 158, 134, 0.35);
            color: #4ade80;
            font-size: 0.76rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .err-pulse-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 8px #22c55e;
            animation: pulse-green 2s infinite ease-out;
        }

        @keyframes pulse-green {
            0% { transform: scale(0.9); opacity: 1; box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.6); }
            70% { transform: scale(1.1); opacity: 0.7; box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
            100% { transform: scale(0.9); opacity: 1; box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        /* Ícone central em anel dourado duplo */
        .err-icon-stage {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: clamp(16px, 3vw, 24px);
        }

        .err-icon-halo {
            position: absolute;
            inset: -14px;
            border-radius: 50%;
            border: 1px dashed rgba(245, 179, 1, 0.35);
            animation: rotate-radar 22s linear infinite;
        }

        @keyframes rotate-radar {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .err-icon-circle {
            width: clamp(64px, 12vw, 76px);
            height: clamp(64px, 12vw, 76px);
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(245, 179, 1, 0.16) 0%, rgba(216, 150, 0, 0.08) 100%);
            border: 1.5px solid var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1.8rem, 4vw, 2.2rem);
            color: var(--gold-light);
            box-shadow: 0 0 25px rgba(245, 179, 1, 0.35);
            animation: pulse-ring 3s infinite ease-in-out;
            flex-shrink: 0;
        }

        @keyframes pulse-ring {
            0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(245, 179, 1, 0.3); }
            50% { transform: scale(1.05); box-shadow: 0 0 35px rgba(245, 179, 1, 0.55); }
        }

        /* Código de erro tipográfico gigante */
        .err-code-wrap {
            position: relative;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: clamp(12px, 2.5vw, 18px);
        }

        .err-code-number {
            font-family: var(--font-display);
            font-size: clamp(4.2rem, 12vw, 6.4rem);
            font-weight: 900;
            line-height: 0.95;
            letter-spacing: -0.04em;
            background: linear-gradient(145deg, #ffffff 15%, var(--gold-light) 50%, var(--gold) 85%, var(--gold-deep) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 6px 30px rgba(245, 179, 1, 0.3));
            user-select: none;
        }

        .err-code-category {
            margin-top: 8px;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--gold);
            background: rgba(245, 179, 1, 0.1);
            border: 1px solid rgba(245, 179, 1, 0.28);
            padding: 4px 14px;
            border-radius: 9999px;
            display: inline-block;
        }

        /* Textos explicativos */
        .err-title {
            font-family: var(--font-display);
            font-size: clamp(1.4rem, 3.8vw, 1.95rem);
            font-weight: 800;
            color: var(--text-white);
            margin-bottom: 8px;
            letter-spacing: -0.02em;
            line-height: 1.25;
        }

        .err-subtitle {
            font-size: clamp(0.96rem, 2.4vw, 1.12rem);
            font-weight: 600;
            color: var(--gold-light);
            margin-bottom: 14px;
            line-height: 1.45;
        }

        .err-message {
            font-size: clamp(0.88rem, 2vw, 0.98rem);
            color: var(--text-slate);
            line-height: 1.65;
            margin: 0 auto clamp(24px, 4vw, 32px);
            max-width: 520px;
        }

        /* Grupo de botões de ação */
        .err-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            align-items: center;
            margin-bottom: clamp(24px, 4vw, 32px);
        }

        .err-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 13px 26px;
            font-size: 0.94rem;
            font-weight: 700;
            border-radius: var(--radius-btn);
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            min-height: 48px;
        }

        .err-btn-gold {
            background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 40%, var(--gold-deep) 100%);
            color: #12161f;
            box-shadow: 0 4px 20px rgba(245, 179, 1, 0.35);
        }

        .err-btn-gold:hover {
            background: linear-gradient(135deg, #ffe285 0%, var(--gold-light) 50%, var(--gold) 100%);
            color: #080a0f;
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(245, 179, 1, 0.55);
        }

        .err-btn-whatsapp {
            background: rgba(37, 211, 102, 0.14);
            border: 1px solid rgba(37, 211, 102, 0.45);
            color: #34d399;
            box-shadow: 0 4px 18px rgba(37, 211, 102, 0.2);
        }

        .err-btn-whatsapp:hover {
            background: #25d366;
            color: #ffffff;
            border-color: #25d366;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(37, 211, 102, 0.45);
        }

        .err-btn-outline {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: var(--text-heading);
        }

        .err-btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            color: #fff;
            transform: translateY(-2px);
        }

        /* Barra de atalhos rápidos ("Para onde deseja ir?") */
        .err-shortcuts {
            border-top: 1px solid var(--border-subtle);
            padding-top: 20px;
            margin-bottom: 24px;
        }

        .err-shortcuts-title {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-dim);
            font-weight: 700;
            margin-bottom: 12px;
        }

        .err-shortcuts-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }

        .err-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--text-slate);
            font-size: 0.82rem;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .err-chip i {
            color: var(--gold);
            font-size: 0.78rem;
        }

        .err-chip:hover {
            background: rgba(245, 179, 1, 0.12);
            border-color: rgba(245, 179, 1, 0.35);
            color: var(--text-white);
            transform: translateY(-1px);
        }

        /* Bloco retrátil de diagnóstico técnico seguro */
        .err-details-accordion {
            margin-top: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            padding-top: 16px;
        }

        .err-details-accordion summary {
            font-size: 0.76rem;
            color: var(--text-dim);
            cursor: pointer;
            user-select: none;
            list-style: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
        }

        .err-details-accordion summary::-webkit-details-marker {
            display: none;
        }

        .err-details-accordion summary:hover {
            color: var(--gold);
        }

        .err-diag-box {
            margin-top: 12px;
            padding: 12px 16px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.76rem;
            color: var(--text-slate);
            text-align: left;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 8px;
        }

        .err-diag-box span b {
            color: var(--gold);
            font-weight: 600;
        }

        /* Rodapé de integridade e segurança */
        .err-footer {
            margin-top: 22px;
            padding-top: 16px;
            border-top: 1px solid var(--border-subtle);
            font-size: 0.8rem;
            color: var(--text-dim);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .err-footer a {
            color: var(--text-slate);
            text-decoration: none;
            transition: color 0.2s;
        }

        .err-footer a:hover {
            color: var(--gold);
        }

        .err-footer-shield {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-slate);
            font-weight: 500;
        }

        .err-footer-shield i {
            color: var(--gold);
        }

        /* Responsividade para telas móveis */
        @media (max-width: 640px) {
            .err-card {
                padding: 28px 18px;
                border-radius: 20px;
            }

            .err-header {
                justify-content: center;
                text-align: center;
            }

            .err-actions {
                flex-direction: column;
                width: 100%;
            }

            .err-btn {
                width: 100%;
            }

            .err-shortcuts-grid {
                gap: 6px;
            }

            .err-chip {
                font-size: 0.78rem;
                padding: 6px 12px;
            }

            .err-footer {
                justify-content: center;
                text-align: center;
                flex-direction: column;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    {{-- Efeito visual de profundidade espacial e malha de monitoramento --}}
    <div class="err-bg-atmosphere">
        <div class="err-bg-grid"></div>
        <div class="err-glow-orb-primary"></div>
        <div class="err-glow-orb-secondary"></div>
    </div>

    {{-- Cartão Principal da Página de Erro --}}
    <main class="err-card" role="main">
        {{-- Cabeçalho com Logotipo e Status da Plataforma --}}
        <header class="err-header">
            <a href="{{ url('/') }}" class="err-brand" title="Golden Rastreamento">
                <img src="{{ asset('img/logo-golden.png') }}" alt="Golden Rastreamento" onerror="this.style.display='none'">
                <span>Golden <b>Rastreamento</b></span>
            </a>

            <div class="err-status-pill">
                <span class="err-pulse-dot"></span>
                <span>Plataforma Online</span>
            </div>
        </header>

        {{-- Palco do Ícone com Anel Radar --}}
        @if(!empty($dados['icon']))
            @php
                $iconeRaw = trim($dados['icon']);
                if (!str_contains($iconeRaw, 'fa-solid') && !str_contains($iconeRaw, 'fa-regular') && !str_contains($iconeRaw, 'fa-brands') && !str_contains($iconeRaw, 'fas') && !str_contains($iconeRaw, 'far') && !str_contains($iconeRaw, 'fab')) {
                    $iconeRaw = 'fa-solid ' . (str_starts_with($iconeRaw, 'fa-') ? $iconeRaw : 'fa-' . $iconeRaw);
                }
            @endphp
            <div class="err-icon-stage">
                <div class="err-icon-halo"></div>
                <div class="err-icon-circle">
                    <i class="{{ $iconeRaw }}"></i>
                </div>
            </div>
        @endif

        {{-- Número do Código HTTP em Tipografia Dourada --}}
        <div class="err-code-wrap">
            <span class="err-code-number">{{ $dados['code'] ?? 'Erro' }}</span>
            @php
                $catMap = [
                    400 => 'Requisição Incorreta',
                    401 => 'Autenticação Restrita',
                    403 => 'Acesso Protegido',
                    404 => 'Coordenada Não Encontrada',
                    419 => 'Validade de Sessão',
                    429 => 'Proteção Contra Sobrecarga',
                    500 => 'Instabilidade no Servidor',
                    503 => 'Manutenção Preventiva',
                ];
                $categoriaLabel = $catMap[$dados['code'] ?? 0] ?? 'Alerta do Sistema';
            @endphp
            <span class="err-code-category">{{ $categoriaLabel }}</span>
        </div>

        {{-- Mensagens Dinâmicas --}}
        <h1 class="err-title">{{ $dados['title'] ?? 'Erro no Sistema' }}</h1>

        @if(!empty($dados['subtitle']))
            <p class="err-subtitle">{{ $dados['subtitle'] }}</p>
        @endif

        @if(!empty($dados['message']))
            <p class="err-message">{{ $dados['message'] }}</p>
        @endif

        {{-- Conteúdo Injetado (ex: Cronômetro da Manutenção 503) --}}
        @yield('conteudo_extra')

        {{-- Ações Principais do Usuário --}}
        <div class="err-actions">
            @if(!empty($dados['primary_button_text']))
                <a href="{{ $dados['primary_button_url'] ?? url('/') }}" class="err-btn err-btn-gold">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>{{ $dados['primary_button_text'] }}</span>
                </a>
            @endif

            @if(!empty($dados['show_whatsapp_button']))
                @php
                    $whatsappNumero = preg_replace('/[^\d]/', '', (string) \App\Models\Setting::get('contato_whatsapp', '5519997101483'));
                    $msgWhatsapp = rawurlencode($dados['whatsapp_custom_text'] ?? "Olá! Encontrei o código HTTP {$dados['code']} no site da Golden Rastreamento e gostaria de orientações.");
                @endphp
                <a href="https://wa.me/{{ $whatsappNumero }}?text={{ $msgWhatsapp }}" target="_blank" rel="noopener noreferrer" class="err-btn err-btn-whatsapp">
                    <i class="fa-brands fa-whatsapp"></i>
                    <span>Falar no WhatsApp</span>
                </a>
            @endif

            <button type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ url('/') }}'" class="err-btn err-btn-outline" title="Voltar à tela anterior">
                <i class="fa-solid fa-rotate-left"></i>
                <span>Voltar</span>
            </button>
        </div>

        {{-- Atalhos Inteligentes para Outras Páginas da Empresa --}}
        <nav class="err-shortcuts" aria-label="Navegação rápida">
            <div class="err-shortcuts-title">Para onde você deseja ir?</div>
            <div class="err-shortcuts-grid">
                <a href="{{ url('/') }}" class="err-chip"><i class="fa-solid fa-house"></i> Início</a>
                <a href="{{ Route::has('site.planos') ? route('site.planos') : url('/planos') }}" class="err-chip"><i class="fa-solid fa-layer-group"></i> Planos & Kits</a>
                <a href="{{ Route::has('site.loja.index') ? route('site.loja.index') : url('/loja') }}" class="err-chip"><i class="fa-solid fa-cart-shopping"></i> Loja Golden</a>
                <a href="{{ Route::has('site.rastreio.index') ? route('site.rastreio.index') : url('/rastreamento') }}" class="err-chip"><i class="fa-solid fa-location-dot"></i> Rastrear Pedido</a>
                <a href="{{ Route::has('site.contato') ? route('site.contato') : url('/contato') }}" class="err-chip"><i class="fa-solid fa-headset"></i> Atendimento</a>
                <a href="{{ Route::has('login') ? route('login') : url('/login') }}" class="err-chip"><i class="fa-solid fa-user-lock"></i> Área do Cliente</a>
            </div>
        </nav>

        {{-- Diagnóstico Técnico Seguro (Discreto) --}}
        <details class="err-details-accordion">
            <summary><i class="fa-solid fa-circle-info"></i> Detalhes da conexão</summary>
            <div class="err-diag-box">
                <span><b>Código:</b> HTTP {{ $dados['code'] ?? '—' }}</span>
                <span><b>Horário:</b> {{ now()->format('d/m/Y H:i:s') }} BRT</span>
                <span><b>Protocolo:</b> Seguro (SSL/TLS)</span>
                <span><b>Servidor:</b> Golden Cluster Node</span>
            </div>
        </details>

        {{-- Rodapé do Cartão --}}
        <footer class="err-footer">
            <span>&copy; {{ date('Y') }} Golden Rastreamento Veicular.</span>
            <span class="err-footer-shield">
                <i class="fa-solid fa-shield-halved"></i> Conexão Segura e Criptografada
            </span>
        </footer>
    </main>

    @stack('scripts')
</body>
</html>
