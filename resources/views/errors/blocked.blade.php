<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Bloqueado pelo Escudo de Segurança | Golden Rastreamento</title>
    <link rel="icon" type="image/png" href="{{ asset('img/favicon.png') }}">
    @include('partials.icones')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --bg-base: #06080d;
            --bg-card: rgba(14, 17, 26, 0.92);
            --red-alert: #ef4444;
            --red-glow: rgba(239, 68, 68, 0.28);
            --gold: #f5b301;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --font-main: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
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
            padding: 24px 16px;
            position: relative;
            overflow-x: hidden;
        }
        .bg-radar {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at center, rgba(239, 68, 68, 0.08) 0%, transparent 65%),
                linear-gradient(to right, rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 100% 100%, 36px 36px, 36px 36px;
            pointer-events: none;
            z-index: 1;
        }
        .shield-box {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 620px;
            background: var(--bg-card);
            border: 1px solid rgba(239, 68, 68, 0.35);
            border-radius: 20px;
            padding: 40px 32px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8), 0 0 40px -5px var(--red-glow);
            backdrop-filter: blur(16px);
        }
        .shield-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.12);
            border: 2px solid var(--red-alert);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.2rem;
            color: var(--red-alert);
            animation: pulse-red 2.4s infinite;
        }
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.5); }
            70% { box-shadow: 0 0 0 20px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        .badge-threat {
            display: inline-block;
            background: rgba(239, 68, 68, 0.18);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.45);
            font-size: 0.76rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            padding: 6px 14px;
            border-radius: 9999px;
            margin-bottom: 18px;
        }
        h1 {
            font-size: 1.65rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 12px;
            letter-spacing: -0.01em;
        }
        p.desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 26px;
        }
        .incident-card {
            background: rgba(0, 0, 0, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 16px 20px;
            font-family: var(--font-mono);
            font-size: 0.84rem;
            text-align: left;
            margin-bottom: 28px;
            color: #cbd5e1;
        }
        .incident-card div {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }
        .incident-card div:last-child { border-bottom: none; }
        .incident-card span.label { color: #64748b; }
        .incident-card span.val { color: var(--gold); font-weight: 600; }
        .btn-return {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.16);
            color: #e2e8f0;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .btn-return:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.3);
        }
        .legal-notice {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 0.76rem;
            color: #475569;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="bg-radar"></div>

    <div class="shield-box">
        <div class="shield-icon">
            <i class="fas fa-shield-halved"></i>
        </div>

        <span class="badge-threat">
            <i class="fas fa-triangle-exclamation me-1"></i> Bloqueio de Defesa Militar Ativo
        </span>

        <h1>Acesso Interrompido por Segurança</h1>

        <p class="desc">
            Esta requisição foi interceptada pelo sistema automatizado de segurança perimetral da Golden Rastreamento após a identificação de tráfego incompatível ou potencial ameaça.
        </p>

        <div class="incident-card">
            <div>
                <span class="label">ID DO INCIDENTE:</span>
                <span class="val">{{ $incidenteId ?? 'SEC-'.strtoupper(bin2hex(random_bytes(4))) }}</span>
            </div>
            <div>
                <span class="label">ENDEREÇO IP:</span>
                <span class="val">{{ request()->ip() }}</span>
            </div>
            <div>
                <span class="label">DATA/HORA:</span>
                <span class="val">{{ now()->format('d/m/Y H:i:s') }}</span>
            </div>
            <div>
                <span class="label">MOTIVO REGISTRADO:</span>
                <span class="val" style="color: #f87171;">{{ $motivo ?? 'Tráfego suspeito interceptado' }}</span>
            </div>
        </div>

        <a href="{{ url('/') }}" class="btn-return">
            <i class="fas fa-arrow-left"></i> Retornar à Página Principal
        </a>

        <p class="legal-notice">
            A infraestrutura de segurança monitora tentativas de intrusão, injeção de parâmetros maliciosos e varreduras não autorizadas conforme as disposições legais e regulatórias vigentes.
        </p>
    </div>
</body>
</html>

