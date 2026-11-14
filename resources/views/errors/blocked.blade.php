<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="theme-color" content="#06080d">
    <title>Acesso Interrompido por Segurança | Golden Rastreamento</title>
    @include('partials.favicon')

    {{-- Tipografia moderna do ecossistema Golden --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">

    {{-- Ícones FontAwesome --}}
    @include('partials.icones')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        :root {
            --bg-void: #06080d;
            --bg-panel: rgba(14, 18, 28, 0.85);
            --red-alert: #ef4444;
            --red-light: #f87171;
            --red-glow: rgba(239, 68, 68, 0.28);
            --border-panel: rgba(239, 68, 68, 0.28);
            --border-subtle: rgba(255, 255, 255, 0.08);
            --gold: #f5b301;
            --gold-light: #ffd056;
            --text-white: #ffffff;
            --text-heading: #f8fafc;
            --text-slate: #94a3b8;
            --text-dim: #64748b;
            --font-display: "Poppins", "Segoe UI", system-ui, sans-serif;
            --font-body: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
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

        /* Atmosfera de Defesa Perimetral */
        .shield-atmosphere {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .shield-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(to right, rgba(239, 68, 68, 0.04) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(239, 68, 68, 0.04) 1px, transparent 1px);
            background-size: clamp(32px, 5vw, 48px) clamp(32px, 5vw, 48px);
            mask-image: radial-gradient(circle at 50% 50%, #000 35%, transparent 85%);
            -webkit-mask-image: radial-gradient(circle at 50% 50%, #000 35%, transparent 85%);
        }

        .shield-orb-crimson {
            position: absolute;
            top: 25%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: clamp(320px, 60vw, 650px);
            height: clamp(320px, 60vw, 650px);
            background: radial-gradient(circle, rgba(239, 68, 68, 0.22) 0%, rgba(239, 68, 68, 0.04) 50%, transparent 70%);
            filter: blur(85px);
            border-radius: 50%;
            animation: pulse-threat 6s ease-in-out infinite alternate;
        }

        .shield-orb-secondary {
            position: absolute;
            bottom: 5%;
            left: 50%;
            transform: translateX(-50%);
            width: clamp(260px, 45vw, 500px);
            height: clamp(180px, 35vw, 320px);
            background: radial-gradient(ellipse at center, rgba(245, 179, 1, 0.1) 0%, transparent 65%);
            filter: blur(80px);
            border-radius: 50%;
        }

        @keyframes pulse-threat {
            0% { transform: translate(-50%, -50%) scale(0.94); opacity: 0.75; }
            100% { transform: translate(-50%, -50%) scale(1.06); opacity: 1; }
        }

        /* Cartão Glassmorphism de Segurança */
        .shield-card {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 660px;
            background: var(--bg-panel);
            border: 1px solid var(--border-panel);
            border-top: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: var(--radius-card);
            padding: clamp(30px, 5vw, 48px) clamp(20px, 4.5vw, 40px);
            text-align: center;
            box-shadow:
                0 30px 80px -15px rgba(0, 0, 0, 0.9),
                0 0 50px -10px var(--red-glow);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px);
            transition: var(--transition);
        }

        /* Header */
        .shield-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: clamp(22px, 3.5vw, 32px);
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-subtle);
        }

        .shield-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: transform 0.2s ease;
        }

        .shield-brand:hover {
            transform: translateY(-1px);
        }

        .shield-brand img {
            height: clamp(32px, 5vw, 38px);
            width: auto;
            object-fit: contain;
        }

        .shield-brand span {
            font-family: var(--font-display);
            font-size: clamp(0.96rem, 2.2vw, 1.1rem);
            font-weight: 800;
            letter-spacing: 0.05em;
            color: var(--text-white);
            text-transform: uppercase;
        }

        .shield-brand span b {
            color: var(--gold);
        }

        .shield-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 12px;
            border-radius: 9999px;
            background: rgba(239, 68, 68, 0.14);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .shield-pulse-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--red-alert);
            box-shadow: 0 0 8px var(--red-alert);
            animation: pulse-red-dot 1.8s infinite ease-out;
        }

        @keyframes pulse-red-dot {
            0% { transform: scale(0.9); opacity: 1; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1.1); opacity: 0.7; box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.9); opacity: 1; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        /* Palco do Ícone de Defesa */
        .shield-icon-stage {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: clamp(16px, 3vw, 22px);
        }

        .shield-icon-halo {
            position: absolute;
            inset: -14px;
            border-radius: 50%;
            border: 1px dashed rgba(239, 68, 68, 0.4);
            animation: rotate-shield-radar 20s linear infinite;
        }

        @keyframes rotate-shield-radar {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .shield-icon-circle {
            width: clamp(68px, 13vw, 80px);
            height: clamp(68px, 13vw, 80px);
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.22) 0%, rgba(185, 28, 28, 0.12) 100%);
            border: 2px solid var(--red-alert);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(2rem, 4.5vw, 2.4rem);
            color: #ffffff;
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.45);
            animation: pulse-shield-ring 3s infinite ease-in-out;
            flex-shrink: 0;
        }

        @keyframes pulse-shield-ring {
            0%, 100% { transform: scale(1); box-shadow: 0 0 25px rgba(239, 68, 68, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 0 40px rgba(239, 68, 68, 0.7); }
        }

        .badge-threat-level {
            display: inline-block;
            background: rgba(239, 68, 68, 0.14);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.4);
            font-size: 0.76rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            padding: 5px 14px;
            border-radius: 9999px;
            margin-bottom: 14px;
        }

        .shield-title {
            font-family: var(--font-display);
            font-size: clamp(1.4rem, 3.8vw, 1.95rem);
            font-weight: 800;
            color: var(--text-white);
            margin-bottom: 8px;
            letter-spacing: -0.02em;
            line-height: 1.25;
        }

        .shield-desc {
            font-size: clamp(0.88rem, 2vw, 0.98rem);
            color: var(--text-slate);
            line-height: 1.65;
            margin: 0 auto clamp(20px, 3.5vw, 26px);
            max-width: 520px;
        }

        /* Cartão de Registro do Incidente */
        .incident-card {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(239, 68, 68, 0.22);
            border-radius: 14px;
            padding: clamp(14px, 2.5vw, 18px) clamp(16px, 3vw, 22px);
            font-family: var(--font-mono);
            font-size: clamp(0.76rem, 1.8vw, 0.84rem);
            text-align: left;
            margin-bottom: clamp(22px, 3.5vw, 28px);
            color: #cbd5e1;
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.5);
        }

        .incident-card-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            padding: 7px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .incident-card-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .incident-card-item:first-child {
            padding-top: 0;
        }

        .incident-card-item span.label {
            color: var(--text-dim);
            font-weight: 500;
            letter-spacing: 0.04em;
        }

        .incident-card-item span.val {
            color: var(--gold-light);
            font-weight: 600;
        }

        .incident-card-item span.val-threat {
            color: var(--red-light);
            font-weight: 700;
        }

        /* Botões de Ação */
        .shield-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            align-items: center;
            margin-bottom: clamp(22px, 3.5vw, 28px);
        }

        .shield-btn {
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

        .shield-btn-whatsapp {
            background: rgba(37, 211, 102, 0.16);
            border: 1px solid rgba(37, 211, 102, 0.45);
            color: #34d399;
            box-shadow: 0 4px 18px rgba(37, 211, 102, 0.22);
        }

        .shield-btn-whatsapp:hover {
            background: #25d366;
            color: #ffffff;
            border-color: #25d366;
            transform: translateY(-2px);
            box-shadow: 0 8px 26px rgba(37, 211, 102, 0.5);
        }

        .shield-btn-outline {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: var(--text-heading);
        }

        .shield-btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            color: #fff;
            transform: translateY(-2px);
        }

        /* Aviso Legal */
        .shield-legal {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-subtle);
            font-size: 0.78rem;
            color: var(--text-dim);
            line-height: 1.55;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .shield-legal i {
            color: var(--gold);
        }

        @media (max-width: 640px) {
            .shield-card {
                padding: 26px 16px;
                border-radius: 20px;
            }
            .shield-header {
                justify-content: center;
                text-align: center;
            }
            .shield-actions {
                flex-direction: column;
                width: 100%;
            }
            .shield-btn {
                width: 100%;
            }
            .incident-card-item {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="shield-atmosphere">
        <div class="shield-grid"></div>
        <div class="shield-orb-crimson"></div>
        <div class="shield-orb-secondary"></div>
    </div>

    @php
        $incidenteCodigo = $incidenteId ?? 'SEC-' . strtoupper(bin2hex(random_bytes(4)));
        $ipCliente = request()->ip() ?? 'Não identificado';
        $motivoFormatado = $motivo ?? 'Tráfego incompatível interceptado';
        $horaIncidente = now()->format('d/m/Y H:i:s') . ' BRT';
        $whatsappNumero = preg_replace('/[^\d]/', '', (string) \App\Models\Setting::get('contato_whatsapp', '5519997101483'));
        $msgSuporte = rawurlencode("Olá! Meu acesso foi temporariamente interceptado pelo Escudo de Segurança da Golden Rastreamento. Protocolo: {$incidenteCodigo} (IP: {$ipCliente}). Poderiam verificar a liberação?");
    @endphp

    <main class="shield-card" role="main">
        <header class="shield-header">
            <a href="{{ url('/') }}" class="shield-brand" title="Golden Rastreamento">
                <img src="{{ asset('img/logo-golden.png') }}" alt="Golden Rastreamento" onerror="this.style.display='none'">
                <span>Golden <b>Rastreamento</b></span>
            </a>

            <div class="shield-status-pill">
                <span class="shield-pulse-dot"></span>
                <span>Defesa Perimetral Ativa</span>
            </div>
        </header>

        <div class="shield-icon-stage">
            <div class="shield-icon-halo"></div>
            <div class="shield-icon-circle">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
        </div>

        <div>
            <span class="badge-threat-level">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> Bloqueio Preventivo de Segurança
            </span>
        </div>

        <h1 class="shield-title">Acesso Interrompido por Segurança</h1>

        <p class="shield-desc">
            Esta requisição foi interceptada pela camada perimetral da Golden Rastreamento após a detecção de requisições incomuns ou tráfego incompatível com as políticas de acesso seguro.
        </p>

        <div class="incident-card">
            <div class="incident-card-item">
                <span class="label">ID DO INCIDENTE:</span>
                <span class="val">{{ $incidenteCodigo }}</span>
            </div>
            <div class="incident-card-item">
                <span class="label">ENDEREÇO IP:</span>
                <span class="val">{{ $ipCliente }}</span>
            </div>
            <div class="incident-card-item">
                <span class="label">REGISTRO DE TEMPO:</span>
                <span class="val">{{ $horaIncidente }}</span>
            </div>
            <div class="incident-card-item">
                <span class="label">MOTIVO DETECTADO:</span>
                <span class="val-threat">{{ $motivoFormatado }}</span>
            </div>
            <div class="incident-card-item">
                <span class="label">STATUS DO FLUXO:</span>
                <span class="val" style="color: #4ade80;">Interceptação Preventiva</span>
            </div>
        </div>

        <div class="shield-actions">
            <a href="https://wa.me/{{ $whatsappNumero }}?text={{ $msgSuporte }}" target="_blank" rel="noopener noreferrer" class="shield-btn shield-btn-whatsapp" title="Falar com o suporte técnico para revisão imediata">
                <i class="fa-brands fa-whatsapp"></i>
                <span>Solicitar Revisão no WhatsApp</span>
            </a>

            <a href="{{ url('/') }}" class="shield-btn shield-btn-outline" title="Retornar à página inicial">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Voltar ao Início</span>
            </a>
        </div>

        <p class="shield-legal">
            <i class="fa-solid fa-lock"></i>
            Monitoramento contínuo em conformidade com as diretrizes de integridade, proteção de dados e segurança de redes.
        </p>
    </main>
</body>
</html>
