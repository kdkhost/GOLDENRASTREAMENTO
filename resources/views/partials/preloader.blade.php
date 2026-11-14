@php
    $area = ($area ?? 'site') === 'admin' ? 'admin' : 'site';
    $ativo = \App\Models\Setting::get($area === 'admin' ? 'preloader_admin_ativo' : 'preloader_site_ativo', '1') === '1';
    $estilo = \App\Models\Setting::get('preloader_estilo', 'radar');
    if (! in_array($estilo, ['radar', 'orbita', 'pulso'], true)) {
        $estilo = 'radar';
    }
    $titulo = \App\Models\Setting::get('preloader_titulo', $golden['marca']['nome'] ?? 'Golden Rastreamento');
    $subtitulo = \App\Models\Setting::get('preloader_subtitulo', 'Proteção e monitoramento 24h');
    $cor = \App\Models\Setting::get('preloader_cor', '#d4a017');
    if (! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $cor)) {
        $cor = '#d4a017';
    }
@endphp

@if ($ativo)
    <div id="golden-preloader"
        class="golden-preloader golden-preloader--{{ $estilo }}"
        style="--gp-accent: {{ $cor }};"
        role="status"
        aria-live="polite"
        aria-label="Carregando {{ $titulo }}">
        <div class="golden-preloader__inner">
            <div class="golden-preloader__stage" aria-hidden="true">
                @if ($estilo === 'radar')
                    <span class="gp-ring gp-ring--1"></span>
                    <span class="gp-ring gp-ring--2"></span>
                    <span class="gp-sweep"></span>
                @elseif ($estilo === 'orbita')
                    <span class="gp-orbit"></span>
                    <span class="gp-orbit gp-orbit--inner"></span>
                @else
                    <span class="gp-halo"></span>
                @endif
                <div class="golden-preloader__core">
                    <i class="fas fa-location-crosshairs" aria-hidden="true"></i>
                </div>
            </div>
            <div class="golden-preloader__title">{{ $titulo }}</div>
            @if ($subtitulo !== '')
                <div class="golden-preloader__subtitle">{{ $subtitulo }}</div>
            @endif
            <div class="golden-preloader__bar" aria-hidden="true">
                <div class="golden-preloader__bar-fill"></div>
            </div>
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
                setTimeout(hide, 180);
            });
            // Segurança: nunca trava a tela se algum recurso externo demorar.
            setTimeout(hide, 2400);
        })();
    </script>
@endif
