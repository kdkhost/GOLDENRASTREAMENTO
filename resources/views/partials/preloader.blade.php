@php
    $area = ($area ?? 'site');
    if (! in_array($area, ['admin', 'portal', 'site'], true)) {
        $area = 'site';
    }

    $settingKey = match($area) {
        'admin' => 'preloader_admin_ativo',
        'portal' => 'preloader_portal_ativo',
        default => 'preloader_site_ativo',
    };

    $ativo = \App\Models\Setting::get($settingKey, '1') === '1';
    $estilo = \App\Models\Setting::get('preloader_estilo', 'radar');
    if (! in_array($estilo, ['radar', 'orbita', 'pulso', 'satelite', 'telemetria', 'circuito', 'fluxo'], true)) {
        $estilo = 'radar';
    }

    $fundo = \App\Models\Setting::get('preloader_fundo', 'radial_dark');
    if (! in_array($fundo, ['radial_dark', 'oled_black', 'glass_blur', 'aurora_gold'], true)) {
        $fundo = 'radial_dark';
    }

    $titulo = \App\Models\Setting::get('preloader_titulo', $golden['marca']['nome'] ?? 'Golden Rastreamento');
    $subtitulo = \App\Models\Setting::get('preloader_subtitulo', 'Proteção e monitoramento 24h');

    $cor = \App\Models\Setting::get('preloader_cor', '#d4a017');
    if (! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $cor)) {
        $cor = '#d4a017';
    }

    $corSecundaria = \App\Models\Setting::get('preloader_cor_secundaria', '#f59e0b');
    if (! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $corSecundaria)) {
        $corSecundaria = '#f59e0b';
    }

    $iconeTipo = \App\Models\Setting::get('preloader_icone_tipo', 'icone');
    $icone = \App\Models\Setting::get('preloader_icone', 'fa-location-crosshairs');
    if (empty($icone)) {
        $icone = 'fa-location-crosshairs';
    }

    $badgeAtivo = \App\Models\Setting::get('preloader_badge_ativo', '1') === '1';
    $badgeTexto = \App\Models\Setting::get('preloader_badge_texto', 'Sinal GPS Ativo • 24h');

    $barraAtiva = \App\Models\Setting::get('preloader_barra_ativa', '1') === '1';
    $barraEstilo = \App\Models\Setting::get('preloader_barra_estilo', 'shimmer');

    $velocidade = \App\Models\Setting::get('preloader_velocidade', 'normal');
    $tempoSaida = \App\Models\Setting::get('preloader_tempo_saida', 'suave');
    $timeoutMax = (int) \App\Models\Setting::get('preloader_timeout_max', 2400);
    if ($timeoutMax < 1000 || $timeoutMax > 10000) {
        $timeoutMax = 2400;
    }
@endphp

@if ($ativo)
    <div id="golden-preloader"
        class="golden-preloader golden-preloader--{{ $estilo }} golden-preloader--fundo-{{ $fundo }} golden-preloader--vel-{{ $velocidade }} golden-preloader--saida-{{ $tempoSaida }}"
        style="--gp-accent: {{ $cor }}; --gp-accent-secondary: {{ $corSecundaria }};"
        role="status"
        aria-live="polite"
        aria-label="Carregando {{ $titulo }}">
        <div class="golden-preloader__inner">
            <div class="golden-preloader__stage" aria-hidden="true">
                @if ($estilo === 'radar')
                    <span class="gp-ring gp-ring--1"></span>
                    <span class="gp-ring gp-ring--2"></span>
                    <span class="gp-ring gp-ring--3"></span>
                    <span class="gp-sweep"></span>
                    <span class="gp-blip gp-blip--1"></span>
                    <span class="gp-blip gp-blip--2"></span>
                @elseif ($estilo === 'orbita')
                    <span class="gp-orbit">
                        <span class="gp-satellite-node"></span>
                    </span>
                    <span class="gp-orbit gp-orbit--inner">
                        <span class="gp-satellite-node"></span>
                    </span>
                @elseif ($estilo === 'pulso')
                    <span class="gp-halo"></span>
                    <span class="gp-halo gp-halo--outer"></span>
                @elseif ($estilo === 'satelite')
                    <span class="gp-sat-track">
                        <span class="gp-sat-dish"><i class="fas fa-satellite"></i></span>
                    </span>
                    <span class="gp-sat-beam"></span>
                @elseif ($estilo === 'telemetria')
                    <span class="gp-hud-bracket gp-hud-bracket--tl"></span>
                    <span class="gp-hud-bracket gp-hud-bracket--tr"></span>
                    <span class="gp-hud-bracket gp-hud-bracket--bl"></span>
                    <span class="gp-hud-bracket gp-hud-bracket--br"></span>
                    <span class="gp-reticle"></span>
                    <span class="gp-crosshair-h"></span>
                    <span class="gp-crosshair-v"></span>
                @elseif ($estilo === 'circuito')
                    <span class="gp-circuit-ring"></span>
                    <span class="gp-circuit-ring gp-circuit-ring--outer"></span>
                @elseif ($estilo === 'fluxo')
                    <span class="gp-diamond"></span>
                    <span class="gp-diamond gp-diamond--inner"></span>
                @endif

                <div class="golden-preloader__core">
                    @if ($iconeTipo === 'logo')
                        <x-site.img imagem="marca_logo" alt="Logo" :largura="34" :altura="34" class="golden-preloader__logo" :lazy="false" />
                    @else
                        <i class="fas {{ $icone }}" aria-hidden="true"></i>
                    @endif
                </div>
            </div>

            <div class="golden-preloader__title">{{ $titulo }}</div>

            @if ($subtitulo !== '')
                <div class="golden-preloader__subtitle">{{ $subtitulo }}</div>
            @endif

            @if ($badgeAtivo && $badgeTexto !== '')
                <div class="golden-preloader__badge">
                    <span class="golden-preloader__badge-dot"></span>
                    <span>{{ $badgeTexto }}</span>
                </div>
            @endif

            @if ($barraAtiva)
                <div class="golden-preloader__bar golden-preloader__bar--{{ $barraEstilo }}" aria-hidden="true">
                    <div class="golden-preloader__bar-fill"></div>
                </div>
            @endif
        </div>
    </div>

    <script>
        (function () {
            var el = document.getElementById('golden-preloader');
            if (!el) return;
            var hidden = false;
            var hide = function () {
                if (hidden) return;
                hidden = true;
                el.classList.add('is-hidden');
            };
            window.addEventListener('load', function () {
                setTimeout(hide, 160);
            });
            // Tempo limite de segurança configurável para nunca travar a tela
            setTimeout(hide, {{ $timeoutMax }});
        })();
    </script>
@endif
