@php
    $manutencao = app(\App\Services\MaintenanceService::class);
    $errorService = app(\App\Services\ErrorPageService::class);
    $dados = $errorService->obter(503);

    $emManutencao = $manutencao->estaAtivo();
    $titulo = $manutencao->titulo() ?: ($dados['title'] ?? 'Sistema em Manutenção Programada');
    $subtitulo = $manutencao->subtitulo() ?: ($dados['subtitle'] ?? 'Estamos atualizando a infraestrutura para melhor atendê-lo.');
    $mensagem = $manutencao->mensagem() ?: ($dados['message'] ?? 'Nossa equipe técnica está aprimorando os servidores da Golden Rastreamento. Todos os rastreadores veiculares continuam transmitindo normalmente via satélite e celular.');

    $previsao = $manutencao->previsaoFim();
    $exibirCronometro = $manutencao->exibirCronometro() && $previsao && $previsao->isFuture();

    $exibirAnimacao = $manutencao->exibirAnimacao();
    $velocidadeAnimacao = $manutencao->velocidadeAnimacao();
    $exibirSatelite = $manutencao->exibirSatelite();

    $exibirWhatsapp = $manutencao->exibirWhatsapp();
    $whatsappNumero = $manutencao->whatsappNumero();
    $whatsappMsg = rawurlencode($manutencao->whatsappMensagem());

    $exibirPlantao = $manutencao->exibirPlantao();
    $telefonePlantao = $manutencao->telefone();
    $emailPlantao = $manutencao->email();

    $exibirRedes = $manutencao->exibirRedes();
    $instagram = $manutencao->instagram();
    $facebook = $manutencao->facebook();
    $youtube = $manutencao->youtube();
    $linkedin = $manutencao->linkedin();
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="theme-color" content="#06080d">
    <title>{{ $titulo }} | Golden Rastreamento</title>
    @include('partials.favicon')

    {{-- Tipografia Oficial Golden --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">

    {{-- Ícones FontAwesome --}}
    @include('partials.icones')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        :root {
            --bg-void: #06080d;
            --bg-card: rgba(12, 16, 26, 0.86);
            --gold: #f5b301;
            --gold-light: #ffd056;
            --gold-deep: #d89600;
            --gold-glow: rgba(245, 179, 1, 0.28);
            --teal: #1f9e86;
            --teal-bright: #2dd4bf;
            --text-main: #f8fafc;
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
            color: var(--text-main);
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

        /* Canvas de Tráfego e Satélite em Tela Cheia */
        #traffic-sat-canvas {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        /* Gradientes Sutis de Profundidade */
        .sky-glow {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background:
                radial-gradient(circle at 80% 15%, rgba(31, 158, 134, 0.12) 0%, transparent 50%),
                radial-gradient(circle at 20% 85%, rgba(245, 179, 1, 0.08) 0%, transparent 50%);
        }

        /* Cartão Central Glassmorphism */
        .maint-card {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 680px;
            background: var(--bg-card);
            border: 1px solid rgba(245, 179, 1, 0.24);
            border-top: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: var(--radius-card);
            padding: clamp(28px, 5vw, 48px) clamp(20px, 4.5vw, 42px);
            text-align: center;
            box-shadow:
                0 30px 90px -12px rgba(0, 0, 0, 0.9),
                0 0 50px -10px var(--gold-glow);
            backdrop-filter: blur(22px) saturate(180%);
            -webkit-backdrop-filter: blur(22px);
            transition: var(--transition);
        }

        /* Header com Marca e Status do Sistema */
        .maint-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: clamp(20px, 3.5vw, 30px);
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .maint-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: transform 0.2s ease;
        }

        .maint-brand:hover {
            transform: translateY(-1px);
        }

        .maint-brand img {
            height: clamp(32px, 5vw, 40px);
            width: auto;
            object-fit: contain;
        }

        .maint-brand span {
            font-family: var(--font-display);
            font-size: clamp(0.96rem, 2.2vw, 1.15rem);
            font-weight: 800;
            letter-spacing: 0.05em;
            color: #ffffff;
            text-transform: uppercase;
        }

        .maint-brand span b {
            color: var(--gold);
        }

        .maint-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 13px;
            border-radius: 9999px;
            background: rgba(31, 158, 134, 0.14);
            border: 1px solid rgba(31, 158, 134, 0.4);
            color: #4ade80;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .maint-pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 10px #22c55e;
            animation: pulse-dot 2s infinite ease-out;
        }

        @keyframes pulse-dot {
            0% { transform: scale(0.9); opacity: 1; box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
            70% { transform: scale(1.15); opacity: 0.7; box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
            100% { transform: scale(0.9); opacity: 1; box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        /* Palco do Ícone de Ferramenta & Satélite */
        .maint-icon-stage {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: clamp(14px, 2.5vw, 20px);
        }

        .maint-icon-halo {
            position: absolute;
            inset: -14px;
            border-radius: 50%;
            border: 1px dashed rgba(245, 179, 1, 0.35);
            animation: rotate-maint-radar 22s linear infinite;
        }

        @keyframes rotate-maint-radar {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .maint-icon-circle {
            width: clamp(66px, 12vw, 78px);
            height: clamp(66px, 12vw, 78px);
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(245, 179, 1, 0.18) 0%, rgba(216, 150, 0, 0.08) 100%);
            border: 1.5px solid var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1.8rem, 4vw, 2.2rem);
            color: var(--gold-light);
            box-shadow: 0 0 30px rgba(245, 179, 1, 0.4);
            animation: pulse-maint-ring 3s infinite ease-in-out;
            flex-shrink: 0;
        }

        @keyframes pulse-maint-ring {
            0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(245, 179, 1, 0.3); }
            50% { transform: scale(1.05); box-shadow: 0 0 35px rgba(245, 179, 1, 0.6); }
        }

        /* Títulos e Textos */
        .maint-title {
            font-family: var(--font-display);
            font-size: clamp(1.4rem, 3.8vw, 2rem);
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
            line-height: 1.25;
        }

        .maint-subtitle {
            font-size: clamp(0.96rem, 2.2vw, 1.12rem);
            font-weight: 600;
            color: var(--gold-light);
            margin-bottom: 14px;
            line-height: 1.45;
        }

        .maint-message {
            font-size: clamp(0.88rem, 2vw, 0.98rem);
            color: var(--text-slate);
            line-height: 1.65;
            margin: 0 auto clamp(20px, 3.5vw, 28px);
            max-width: 540px;
        }

        /* Barra de Telemetria Satelital Ativa */
        .maint-telemetry-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 8px 18px;
            background: rgba(31, 158, 134, 0.1);
            border: 1px solid rgba(31, 158, 134, 0.35);
            border-radius: 9999px;
            font-size: clamp(0.74rem, 1.8vw, 0.82rem);
            color: #5eead4;
            font-weight: 600;
            margin-bottom: clamp(20px, 3.5vw, 28px);
            max-width: 100%;
        }

        .maint-telemetry-badge i {
            color: #2dd4bf;
            font-size: 0.95rem;
        }

        /* Cronômetro Regressivo Digital */
        .maint-timer-box {
            background: rgba(245, 179, 1, 0.05);
            border: 1px solid rgba(245, 179, 1, 0.28);
            border-radius: 18px;
            padding: clamp(16px, 3vw, 22px);
            margin: 0 auto clamp(20px, 3.5vw, 28px);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5), inset 0 0 20px rgba(245, 179, 1, 0.04);
            backdrop-filter: blur(12px);
        }

        .maint-timer-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--gold);
            margin-bottom: 14px;
        }

        .maint-timer-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: clamp(6px, 1.8vw, 12px);
            align-items: center;
            justify-content: center;
        }

        .maint-digit-card {
            background: rgba(14, 18, 28, 0.88);
            border: 1px solid rgba(245, 179, 1, 0.25);
            border-radius: 12px;
            padding: clamp(10px, 2vw, 16px) 6px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
            position: relative;
            overflow: hidden;
        }

        .maint-digit-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }

        .maint-digit-number {
            font-family: var(--font-display);
            font-size: clamp(1.6rem, 4.5vw, 2.3rem);
            font-weight: 900;
            line-height: 1;
            color: #ffffff;
            text-shadow: 0 2px 10px rgba(245, 179, 1, 0.4);
            display: block;
            letter-spacing: -0.02em;
        }

        .maint-digit-label {
            margin-top: 6px;
            font-size: clamp(0.64rem, 1.6vw, 0.72rem);
            font-weight: 700;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: block;
        }

        /* Ações Principais (WhatsApp & Plantão) */
        .maint-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            align-items: center;
            margin-bottom: clamp(20px, 3.5vw, 28px);
        }

        .maint-btn {
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

        .maint-btn-whatsapp {
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            color: #ffffff;
            box-shadow: 0 4px 20px rgba(37, 211, 102, 0.35);
        }

        .maint-btn-whatsapp:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(37, 211, 102, 0.55);
            color: #ffffff;
        }

        .maint-btn-outline {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.16);
            color: var(--text-main);
        }

        .maint-btn-outline:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.3);
            color: #fff;
            transform: translateY(-2px);
        }

        /* Caixa de Plantão de Emergência */
        .maint-hotline-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 16px 20px;
            text-align: left;
            margin-bottom: clamp(20px, 3.5vw, 28px);
        }

        .maint-hotline-title {
            font-size: 0.84rem;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .maint-hotline-title i {
            color: var(--gold);
        }

        .maint-hotline-items {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .maint-hotline-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
            font-size: 0.85rem;
            color: var(--text-slate);
            padding: 7px 12px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            text-decoration: none;
            transition: var(--transition);
        }

        .maint-hotline-item:hover {
            background: rgba(245, 179, 1, 0.08);
            color: #ffffff;
        }

        .maint-hotline-item span.label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-dim);
            font-size: 0.8rem;
        }

        .maint-hotline-item span.val {
            color: var(--gold-light);
            font-weight: 600;
        }

        /* Redes Sociais Oficiais */
        .maint-social-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: clamp(18px, 3vw, 24px);
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .maint-social-title {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-dim);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .maint-social-btn {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-slate);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            text-decoration: none;
            transition: var(--transition);
        }

        .maint-social-btn:hover {
            background: rgba(245, 179, 1, 0.16);
            border-color: rgba(245, 179, 1, 0.4);
            color: var(--gold-light);
            transform: translateY(-2px);
        }

        /* Rodapé de Segurança e Login */
        .maint-footer {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 0.78rem;
            color: var(--text-dim);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .maint-footer a {
            color: var(--text-slate);
            text-decoration: none;
            transition: color 0.2s;
        }

        .maint-footer a:hover {
            color: var(--gold);
        }

        .maint-footer-shield {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-slate);
        }

        .maint-footer-shield i {
            color: var(--gold);
        }

        @media (max-width: 640px) {
            .maint-card {
                padding: 24px 16px;
                border-radius: 20px;
            }

            .maint-header {
                justify-content: center;
                text-align: center;
            }

            .maint-actions {
                flex-direction: column;
                width: 100%;
            }

            .maint-btn {
                width: 100%;
            }

            .maint-timer-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }

            .maint-hotline-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .maint-footer {
                justify-content: center;
                text-align: center;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    {{-- Luz ambiente suave --}}
    <div class="sky-glow"></div>

    {{-- Canvas Animado de Alta Performance: Malha Viária, Carros com Faróis e Satélite Orbital --}}
    @if($exibirAnimacao)
        <canvas id="traffic-sat-canvas" aria-hidden="true"></canvas>
    @endif

    {{-- Cartão Principal da Página de Manutenção --}}
    <main class="maint-card" role="main">
        {{-- Cabeçalho com Logotipo Oficial e Status --}}
        <header class="maint-header">
            <a href="{{ url('/') }}" class="maint-brand" title="Golden Rastreamento">
                <img src="{{ asset('img/logo-golden.png') }}" alt="Golden Rastreamento" onerror="this.style.display='none'">
                <span>Golden <b>Rastreamento</b></span>
            </a>

            <div class="maint-status-pill">
                <span class="maint-pulse-dot"></span>
                <span>Manutenção Programada</span>
            </div>
        </header>

        {{-- Palco do Ícone em Anel Radar --}}
        <div class="maint-icon-stage">
            <div class="maint-icon-halo"></div>
            <div class="maint-icon-circle">
                <i class="fa-solid fa-satellite-dish"></i>
            </div>
        </div>

        {{-- Título, Subtítulo e Mensagem --}}
        <h1 class="maint-title">{{ $titulo }}</h1>
        <p class="maint-subtitle">{{ $subtitulo }}</p>
        <p class="maint-message">{{ $mensagem }}</p>

        {{-- Selo de Telemetria Contínua 24h --}}
        <div class="maint-telemetry-badge">
            <i class="fa-solid fa-satellite"></i>
            <span>Rede Satelital 100% Conectada • Veículos Monitorados em Tempo Real 24h</span>
        </div>

        {{-- Cronômetro Regressivo ao Vivo --}}
        @if($exibirCronometro)
            <div id="maint-countdown-box" class="maint-timer-box">
                <div class="maint-timer-title">
                    <i class="fa-solid fa-stopwatch fa-spin-pulse"></i>
                    <span>Previsão de Retorno dos Serviços</span>
                </div>

                <div id="countdown-timer" class="maint-timer-grid">
                    <div class="maint-digit-card">
                        <span id="cd-dias" class="maint-digit-number">00</span>
                        <span class="maint-digit-label">Dias</span>
                    </div>
                    <div class="maint-digit-card">
                        <span id="cd-horas" class="maint-digit-number">00</span>
                        <span class="maint-digit-label">Horas</span>
                    </div>
                    <div class="maint-digit-card">
                        <span id="cd-minutos" class="maint-digit-number">00</span>
                        <span class="maint-digit-label">Minutos</span>
                    </div>
                    <div class="maint-digit-card">
                        <span id="cd-segundos" class="maint-digit-number">00</span>
                        <span class="maint-digit-label">Segundos</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- Botões de Ação Principal (WhatsApp em Destaque) --}}
        <div class="maint-actions">
            @if($exibirWhatsapp && !empty($whatsappNumero))
                <a href="https://wa.me/{{ $whatsappNumero }}?text={{ $whatsappMsg }}" target="_blank" rel="noopener noreferrer" class="maint-btn maint-btn-whatsapp" title="Falar no WhatsApp oficial">
                    <i class="fa-brands fa-whatsapp fa-lg"></i>
                    <span>Atendimento no WhatsApp</span>
                </a>
            @endif

            <button type="button" onclick="window.location.reload();" class="maint-btn maint-btn-outline" title="Atualizar a página">
                <i class="fa-solid fa-rotate"></i>
                <span>Verificar se Voltou</span>
            </button>
        </div>

        {{-- Canais de Plantão de Emergência --}}
        @if($exibirPlantao && (!empty($telefonePlantao) || !empty($emailPlantao)))
            <div class="maint-hotline-box">
                <div class="maint-hotline-title">
                    <i class="fa-solid fa-headset"></i>
                    <span>Canais do Plantão Técnico de Emergência:</span>
                </div>
                <div class="maint-hotline-items">
                    @if(!empty($telefonePlantao))
                        @php
                            $telRaw = preg_replace('/[^\d\+]/', '', (string) $telefonePlantao);
                        @endphp
                        <a href="tel:{{ $telRaw }}" class="maint-hotline-item" title="Ligar para o plantão 24h">
                            <span class="label"><i class="fa-solid fa-phone"></i> Telefonia 24h:</span>
                            <span class="val">{{ $telefonePlantao }}</span>
                        </a>
                    @endif
                    @if(!empty($emailPlantao))
                        <a href="mailto:{{ $emailPlantao }}" class="maint-hotline-item" title="Enviar e-mail de suporte">
                            <span class="label"><i class="fa-solid fa-envelope"></i> E-mail de Suporte:</span>
                            <span class="val">{{ $emailPlantao }}</span>
                        </a>
                    @endif
                </div>
            </div>
        @endif

        {{-- Redes Sociais Oficiais --}}
        @if($exibirRedes && (!empty($instagram) || !empty($facebook) || !empty($youtube) || !empty($linkedin)))
            <div>
                <div class="maint-social-title">Siga a Golden Rastreamento nas Redes</div>
                <div class="maint-social-row">
                    @if(!empty($instagram))
                        <a href="{{ $instagram }}" target="_blank" rel="noopener noreferrer" class="maint-social-btn" title="Instagram Golden Rastreamento">
                            <i class="fa-brands fa-instagram"></i>
                        </a>
                    @endif
                    @if(!empty($facebook))
                        <a href="{{ $facebook }}" target="_blank" rel="noopener noreferrer" class="maint-social-btn" title="Facebook Golden Rastreamento">
                            <i class="fa-brands fa-facebook-f"></i>
                        </a>
                    @endif
                    @if(!empty($youtube))
                        <a href="{{ $youtube }}" target="_blank" rel="noopener noreferrer" class="maint-social-btn" title="Canal Oficial no YouTube">
                            <i class="fa-brands fa-youtube"></i>
                        </a>
                    @endif
                    @if(!empty($linkedin))
                        <a href="{{ $linkedin }}" target="_blank" rel="noopener noreferrer" class="maint-social-btn" title="LinkedIn Oficial">
                            <i class="fa-brands fa-linkedin-in"></i>
                        </a>
                    @endif
                </div>
            </div>
        @endif

        {{-- Rodapé do Cartão --}}
        <footer class="maint-footer">
            <span>&copy; {{ date('Y') }} Golden Rastreamento Veicular.</span>
            <span class="maint-footer-shield">
                <i class="fa-solid fa-shield-halved"></i> Monitoramento Contínuo
            </span>
            <a href="{{ route('login') }}" class="small" title="Acesso do Administrador">
                <i class="fa-solid fa-lock me-1"></i> Acesso Restrito
            </a>
        </footer>
    </main>

    {{-- Script do Contador Regressivo --}}
    @if($exibirCronometro && $previsao)
        <script>
            (function() {
                const targetTime = new Date("{{ $previsao->toIso8601String() }}").getTime();
                const timerContainer = document.getElementById('countdown-timer');

                function updateCountdown() {
                    const now = new Date().getTime();
                    const diff = targetTime - now;

                    if (diff <= 0) {
                        if (timerContainer) {
                            timerContainer.innerHTML = "<div style='grid-column: 1 / -1; padding: 14px; background: rgba(34, 197, 94, 0.14); border: 1px solid rgba(34, 197, 94, 0.4); border-radius: 12px; color: #4ade80; font-weight: 700; font-size: 0.95rem;'><i class='fa-solid fa-circle-check me-2'></i> Retornando as operações a qualquer instante...</div>";
                        }
                        return;
                    }

                    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                    const elD = document.getElementById('cd-dias');
                    const elH = document.getElementById('cd-horas');
                    const elM = document.getElementById('cd-minutos');
                    const elS = document.getElementById('cd-segundos');

                    if (elD) elD.textContent = String(days).padStart(2, '0');
                    if (elH) elH.textContent = String(hours).padStart(2, '0');
                    if (elM) elM.textContent = String(minutes).padStart(2, '0');
                    if (elS) elS.textContent = String(seconds).padStart(2, '0');
                }

                updateCountdown();
                setInterval(updateCountdown, 1000);
            })();
        </script>
    @endif

    {{-- Script de Simulação: Carros Circulando nas Avenidas e Satélite Orbital com Telemetria --}}
    @if($exibirAnimacao)
        <script>
            (function() {
                const canvas = document.getElementById('traffic-sat-canvas');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                if (!ctx) return;

                let width = 0;
                let height = 0;
                let dpr = window.devicePixelRatio || 1;

                function resize() {
                    width = window.innerWidth;
                    height = window.innerHeight;
                    dpr = window.devicePixelRatio || 1;
                    canvas.width = width * dpr;
                    canvas.height = height * dpr;
                    ctx.resetTransform && ctx.resetTransform();
                    ctx.scale(dpr, dpr);
                    buildRoadNetwork();
                }

                // Velocidade baseada na configuração do Admin
                const speedSetting = "{{ $velocidadeAnimacao }}";
                const speedMultiplier = speedSetting === 'rapida' ? 1.6 : (speedSetting === 'suave' ? 0.7 : 1.0);
                const hasSatellite = {{ $exibirSatelite ? 'true' : 'false' }};

                // Rede de Avenidas e Vias
                let roads = [];
                function buildRoadNetwork() {
                    roads = [];
                    // Vias principais longitudinais e transversais estilizadas
                    // Linhas de avenidas com faixas de tráfego
                    const countH = Math.max(3, Math.floor(height / 240));
                    for (let i = 1; i <= countH; i++) {
                        const y = (height / (countH + 1)) * i;
                        roads.push({
                            type: 'horizontal',
                            y: y,
                            dir: i % 2 === 0 ? 1 : -1,
                            lanes: 2
                        });
                    }

                    const countV = Math.max(3, Math.floor(width / 320));
                    for (let j = 1; j <= countV; j++) {
                        const x = (width / (countV + 1)) * j;
                        roads.push({
                            type: 'vertical',
                            x: x,
                            dir: j % 2 === 0 ? 1 : -1,
                            lanes: 2
                        });
                    }

                    // Avenidas diagonais modernas
                    roads.push({
                        type: 'diagonal',
                        x1: -50,
                        y1: height * 0.2,
                        x2: width + 50,
                        y2: height * 0.85,
                        dir: 1
                    });
                    roads.push({
                        type: 'diagonal',
                        x1: width + 50,
                        y1: height * 0.15,
                        x2: -50,
                        y2: height * 0.75,
                        dir: -1
                    });
                }

                // Criação de Carros com Faróis e Lanterna
                const CAR_COUNT = Math.min(26, Math.max(14, Math.floor((window.innerWidth * window.innerHeight) / 55000)));
                const cars = [];
                const carPalette = [
                    { body: '#141c2c', trim: '#f5b301', light: '#ffd056', beam: 'rgba(255, 208, 86, 0.4)' },
                    { body: '#0f172a', trim: '#2dd4bf', light: '#5eead4', beam: 'rgba(45, 212, 191, 0.35)' },
                    { body: '#1e293b', trim: '#f5b301', light: '#ffd056', beam: 'rgba(255, 208, 86, 0.35)' },
                    { body: '#0b1320', trim: '#e2e8f0', light: '#ffffff', beam: 'rgba(255, 255, 255, 0.35)' }
                ];

                function spawnCar() {
                    const roadIndex = Math.floor(Math.random() * roads.length);
                    const road = roads[roadIndex] || { type: 'horizontal', y: height * 0.5, dir: 1 };
                    const theme = carPalette[Math.floor(Math.random() * carPalette.length)];

                    let x = 0, y = 0, angle = 0, vx = 0, vy = 0;
                    const baseSpeed = (1.2 + Math.random() * 1.5) * speedMultiplier;

                    if (road.type === 'horizontal') {
                        x = road.dir > 0 ? -40 - Math.random() * 200 : width + 40 + Math.random() * 200;
                        const laneOffset = (Math.random() > 0.5 ? 8 : -8);
                        y = road.y + laneOffset;
                        angle = road.dir > 0 ? 0 : Math.PI;
                        vx = road.dir * baseSpeed;
                        vy = 0;
                    } else if (road.type === 'vertical') {
                        y = road.dir > 0 ? -40 - Math.random() * 200 : height + 40 + Math.random() * 200;
                        const laneOffset = (Math.random() > 0.5 ? 8 : -8);
                        x = road.x + laneOffset;
                        angle = road.dir > 0 ? Math.PI / 2 : -Math.PI / 2;
                        vx = 0;
                        vy = road.dir * baseSpeed;
                    } else if (road.type === 'diagonal') {
                        const t = Math.random();
                        x = road.x1 + (road.x2 - road.x1) * t;
                        y = road.y1 + (road.y2 - road.y1) * t;
                        const dx = road.x2 - road.x1;
                        const dy = road.y2 - road.y1;
                        angle = Math.atan2(dy, dx) * road.dir;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        vx = (dx / dist) * baseSpeed * road.dir;
                        vy = (dy / dist) * baseSpeed * road.dir;
                    }

                    return {
                        x: x,
                        y: y,
                        vx: vx,
                        vy: vy,
                        angle: angle,
                        length: 16 + Math.random() * 6,
                        width: 8 + Math.random() * 2,
                        theme: theme,
                        road: road,
                        trail: [],
                        pingAlpha: 0,
                        pingRadius: 0,
                        pingText: '',
                        lastPingTime: 0
                    };
                }

                function initCars() {
                    cars.length = 0;
                    for (let i = 0; i < CAR_COUNT; i++) {
                        cars.push(spawnCar());
                    }
                }

                // Satélite Orbital
                const satellite = {
                    x: width * 0.78,
                    y: 90,
                    orbitRadius: 40,
                    angle: 0,
                    pulseTimer: 0,
                    pulses: [],
                    beams: []
                };

                let lastTime = performance.now();

                function render(now) {
                    const dt = Math.min((now - lastTime) / 1000, 0.1);
                    lastTime = now;

                    ctx.clearRect(0, 0, width, height);

                    // 1. Desenhar Malha Viária da Cidade
                    ctx.lineWidth = 1;
                    roads.forEach(road => {
                        ctx.save();
                        if (road.type === 'horizontal') {
                            // Asfalto da pista
                            ctx.fillStyle = 'rgba(11, 16, 28, 0.45)';
                            ctx.fillRect(0, road.y - 20, width, 40);

                            // Bordas da avenida
                            ctx.strokeStyle = 'rgba(31, 158, 134, 0.14)';
                            ctx.beginPath();
                            ctx.moveTo(0, road.y - 20); ctx.lineTo(width, road.y - 20);
                            ctx.moveTo(0, road.y + 20); ctx.lineTo(width, road.y + 20);
                            ctx.stroke();

                            // Linha tracejada central
                            ctx.strokeStyle = 'rgba(245, 179, 1, 0.15)';
                            ctx.setLineDash([8, 12]);
                            ctx.beginPath();
                            ctx.moveTo(0, road.y); ctx.lineTo(width, road.y);
                            ctx.stroke();
                        } else if (road.type === 'vertical') {
                            ctx.fillStyle = 'rgba(11, 16, 28, 0.45)';
                            ctx.fillRect(road.x - 20, 0, 40, height);

                            ctx.strokeStyle = 'rgba(31, 158, 134, 0.14)';
                            ctx.beginPath();
                            ctx.moveTo(road.x - 20, 0); ctx.lineTo(road.x - 20, height);
                            ctx.moveTo(road.x + 20, 0); ctx.lineTo(road.x + 20, height);
                            ctx.stroke();

                            ctx.strokeStyle = 'rgba(245, 179, 1, 0.15)';
                            ctx.setLineDash([8, 12]);
                            ctx.beginPath();
                            ctx.moveTo(road.x, 0); ctx.lineTo(road.x, height);
                            ctx.stroke();
                        } else if (road.type === 'diagonal') {
                            ctx.strokeStyle = 'rgba(245, 179, 1, 0.12)';
                            ctx.setLineDash([12, 16]);
                            ctx.beginPath();
                            ctx.moveTo(road.x1, road.y1); ctx.lineTo(road.x2, road.y2);
                            ctx.stroke();
                        }
                        ctx.restore();
                    });

                    // 2. Atualizar e Desenhar Carros
                    cars.forEach((car, index) => {
                        car.x += car.vx;
                        car.y += car.vy;

                        // Gravar rastro suave
                        if (now % 3 < 1) {
                            car.trail.push({ x: car.x, y: car.y });
                            if (car.trail.length > 8) car.trail.shift();
                        }

                        // Reaparecer se saiu da tela
                        if (car.x < -80 || car.x > width + 80 || car.y < -80 || car.y > height + 80) {
                            cars[index] = spawnCar();
                            return;
                        }

                        // Desenhar Rastro de Lanternas (Light Trails)
                        if (car.trail.length > 2) {
                            ctx.save();
                            ctx.strokeStyle = 'rgba(239, 68, 68, 0.3)';
                            ctx.lineWidth = 2;
                            ctx.beginPath();
                            ctx.moveTo(car.trail[0].x, car.trail[0].y);
                            for (let t = 1; t < car.trail.length; t++) {
                                ctx.lineTo(car.trail[t].x, car.trail[t].y);
                            }
                            ctx.stroke();
                            ctx.restore();
                        }

                        ctx.save();
                        ctx.translate(car.x, car.y);
                        ctx.rotate(car.angle);

                        // Feixes de Faróis Dianteiros (Projeção Cônica Dourada)
                        const beamGrad = ctx.createRadialGradient(car.length * 0.5, 0, 2, car.length * 0.5 + 45, 0, 45);
                        beamGrad.addColorStop(0, car.theme.beam);
                        beamGrad.addColorStop(1, 'rgba(0, 0, 0, 0)');
                        ctx.fillStyle = beamGrad;
                        ctx.beginPath();
                        ctx.moveTo(car.length * 0.5, -car.width * 0.4);
                        ctx.lineTo(car.length * 0.5 + 48, -18);
                        ctx.lineTo(car.length * 0.5 + 48, 18);
                        ctx.lineTo(car.length * 0.5, car.width * 0.4);
                        ctx.closePath();
                        ctx.fill();

                        // Chassi do Veículo
                        ctx.fillStyle = car.theme.body;
                        ctx.strokeStyle = car.theme.trim;
                        ctx.lineWidth = 1.2;
                        ctx.beginPath();
                        ctx.roundRect(-car.length * 0.5, -car.width * 0.5, car.length, car.width, 3);
                        ctx.fill();
                        ctx.stroke();

                        // Parabrisa e Teto
                        ctx.fillStyle = 'rgba(255, 255, 255, 0.15)';
                        ctx.fillRect(-car.length * 0.2, -car.width * 0.35, car.length * 0.4, car.width * 0.7);

                        // Faróis dianteiros (pontos de luz)
                        ctx.fillStyle = car.theme.light;
                        ctx.shadowColor = car.theme.light;
                        ctx.shadowBlur = 6;
                        ctx.fillRect(car.length * 0.48, -car.width * 0.38, 2, 2);
                        ctx.fillRect(car.length * 0.48, car.width * 0.38 - 2, 2, 2);

                        // Lanternas traseiras (vermelho/âmbar)
                        ctx.fillStyle = '#ef4444';
                        ctx.shadowColor = '#ef4444';
                        ctx.shadowBlur = 5;
                        ctx.fillRect(-car.length * 0.5, -car.width * 0.38, 1.8, 2);
                        ctx.fillRect(-car.length * 0.5, car.width * 0.38 - 2, 1.8, 2);

                        ctx.restore();

                        // Anel de Ping / Telemetria Satelital no Carro
                        if (car.pingAlpha > 0) {
                            ctx.save();
                            ctx.strokeStyle = `rgba(45, 212, 191, ${car.pingAlpha})`;
                            ctx.lineWidth = 1.5;
                            ctx.beginPath();
                            ctx.arc(car.x, car.y, car.pingRadius, 0, Math.PI * 2);
                            ctx.stroke();

                            if (car.pingText) {
                                ctx.font = '9px monospace';
                                ctx.fillStyle = `rgba(245, 179, 1, ${car.pingAlpha})`;
                                ctx.fillText(car.pingText, car.x + 12, car.y - 10);
                            }
                            ctx.restore();

                            car.pingRadius += 0.8;
                            car.pingAlpha -= dt * 1.2;
                        }
                    });

                    // 3. Atualizar e Desenhar Satélite Orbital & Feixes
                    if (hasSatellite) {
                        satellite.angle += dt * 0.4;
                        const satCenterX = width > 768 ? width * 0.82 : width * 0.5;
                        const satCenterY = 75;
                        const satX = satCenterX + Math.cos(satellite.angle) * satellite.orbitRadius;
                        const satY = satCenterY + Math.sin(satellite.angle) * 16;

                        // Emissão de Pulsos de Satélite
                        satellite.pulseTimer += dt;
                        if (satellite.pulseTimer > 1.8) {
                            satellite.pulseTimer = 0;
                            satellite.pulses.push({ radius: 10, alpha: 0.85 });

                            // Conectar Feixe de Telemetria com um ou dois carros na pista
                            const eligibleCars = cars.filter(c => c.x > 50 && c.x < width - 50 && c.y > 50 && c.y < height - 50);
                            if (eligibleCars.length > 0) {
                                const targetCar = eligibleCars[Math.floor(Math.random() * eligibleCars.length)];
                                satellite.beams.push({
                                    startX: satX,
                                    startY: satY,
                                    car: targetCar,
                                    progress: 0,
                                    alpha: 1
                                });
                                targetCar.pingAlpha = 0.9;
                                targetCar.pingRadius = 6;
                                targetCar.pingText = `GPS FIX ${Math.floor(Math.random() * 40 + 10)}ms`;
                            }
                        }

                        // Desenhar Ondas Concêntricas de Rádio do Satélite
                        for (let p = satellite.pulses.length - 1; p >= 0; p--) {
                            const pulse = satellite.pulses[p];
                            ctx.save();
                            ctx.strokeStyle = `rgba(45, 212, 191, ${pulse.alpha * 0.5})`;
                            ctx.lineWidth = 1.2;
                            ctx.setLineDash([4, 6]);
                            ctx.beginPath();
                            ctx.arc(satX, satY, pulse.radius, 0, Math.PI * 2);
                            ctx.stroke();
                            ctx.restore();

                            pulse.radius += 1.8;
                            pulse.alpha -= dt * 0.4;
                            if (pulse.alpha <= 0) {
                                satellite.pulses.splice(p, 1);
                            }
                        }

                        // Desenhar Feixes Laser de Conexão com os Veículos
                        for (let b = satellite.beams.length - 1; b >= 0; b--) {
                            const beam = satellite.beams[b];
                            beam.progress += dt * 3.5;
                            beam.alpha -= dt * 0.9;

                            ctx.save();
                            const beamGrad = ctx.createLinearGradient(beam.startX, beam.startY, beam.car.x, beam.car.y);
                            beamGrad.addColorStop(0, `rgba(245, 179, 1, ${beam.alpha * 0.8})`);
                            beamGrad.addColorStop(0.5, `rgba(45, 212, 191, ${beam.alpha})`);
                            beamGrad.addColorStop(1, `rgba(34, 197, 94, ${beam.alpha * 0.9})`);
                            ctx.strokeStyle = beamGrad;
                            ctx.lineWidth = 1.8;
                            ctx.beginPath();
                            ctx.moveTo(beam.startX, beam.startY);
                            ctx.lineTo(beam.car.x, beam.car.y);
                            ctx.stroke();

                            // Ponto de luz viajando pelo feixe
                            const travelT = Math.min(1, beam.progress);
                            const dotX = beam.startX + (beam.car.x - beam.startX) * travelT;
                            const dotY = beam.startY + (beam.car.y - beam.startY) * travelT;
                            ctx.fillStyle = '#ffffff';
                            ctx.shadowColor = '#f5b301';
                            ctx.shadowBlur = 10;
                            ctx.beginPath();
                            ctx.arc(dotX, dotY, 3, 0, Math.PI * 2);
                            ctx.fill();
                            ctx.restore();

                            if (beam.alpha <= 0) {
                                satellite.beams.splice(b, 1);
                            }
                        }

                        // Desenho Vetorial do Satélite
                        ctx.save();
                        ctx.translate(satX, satY);

                        // Painéis Solares (Laterais)
                        ctx.fillStyle = '#0284c7';
                        ctx.strokeStyle = '#38bdf8';
                        ctx.lineWidth = 1;
                        ctx.fillRect(-26, -5, 14, 10);
                        ctx.strokeRect(-26, -5, 14, 10);
                        ctx.fillRect(12, -5, 14, 10);
                        ctx.strokeRect(12, -5, 14, 10);

                        // Linhas de células fotovoltaicas
                        ctx.strokeStyle = 'rgba(255, 255, 255, 0.4)';
                        ctx.beginPath();
                        ctx.moveTo(-19, -5); ctx.lineTo(-19, 5);
                        ctx.moveTo(19, -5); ctx.lineTo(19, 5);
                        ctx.stroke();

                        // Corpo Dourado do Satélite
                        ctx.fillStyle = '#f5b301';
                        ctx.shadowColor = 'rgba(245, 179, 1, 0.6)';
                        ctx.shadowBlur = 12;
                        ctx.beginPath();
                        ctx.roundRect(-8, -8, 16, 16, 3);
                        ctx.fill();

                        // Antena Parabólica apontada para baixo
                        ctx.shadowBlur = 0;
                        ctx.strokeStyle = '#ffd056';
                        ctx.lineWidth = 1.6;
                        ctx.beginPath();
                        ctx.arc(0, 10, 7, Math.PI * 0.15, Math.PI * 0.85);
                        ctx.stroke();
                        ctx.beginPath();
                        ctx.moveTo(0, 8); ctx.lineTo(0, 16);
                        ctx.stroke();

                        // Led de Status Piscante no Satélite
                        const ledBlink = Math.sin(now * 0.008) > 0;
                        ctx.fillStyle = ledBlink ? '#22c55e' : '#14532d';
                        ctx.beginPath();
                        ctx.arc(0, -3, 2, 0, Math.PI * 2);
                        ctx.fill();

                        ctx.restore();
                    }

                    if (!document.hidden) {
                        requestAnimationFrame(render);
                    }
                }

                window.addEventListener('resize', resize);
                document.addEventListener('visibilitychange', function() {
                    if (!document.hidden) {
                        lastTime = performance.now();
                        requestAnimationFrame(render);
                    }
                });

                resize();
                initCars();
                requestAnimationFrame(render);
            })();
        </script>
    @endif
</body>
</html>
