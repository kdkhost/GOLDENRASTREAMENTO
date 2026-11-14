@php
    $dados = app(\App\Services\ErrorPageService::class)->obter(503);
    $manutencao = app(\App\Services\MaintenanceService::class);
    $emManutencao = $manutencao->estaAtivo();

    if ($emManutencao) {
        $dados['title'] = $manutencao->titulo() ?: $dados['title'];
        $dados['subtitle'] = $manutencao->subtitulo() ?: $dados['subtitle'];
        $dados['message'] = $manutencao->mensagem() ?: $dados['message'];
        $dados['icon'] = 'fa-screwdriver-wrench';
    }
@endphp
@extends('errors.layout', ['dados' => $dados])

@push('styles')
<style>
    /* Estilos Modernos e Responsivos para Manutenção (503) */
    .err-503-panel {
        margin: clamp(20px, 3.5vw, 28px) auto;
        max-width: 520px;
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .err-timer-box {
        background: rgba(245, 179, 1, 0.05);
        border: 1px solid rgba(245, 179, 1, 0.28);
        border-radius: 18px;
        padding: clamp(16px, 3vw, 22px);
        box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5), inset 0 0 20px rgba(245, 179, 1, 0.04);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }

    .err-timer-badge {
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

    .err-timer-badge i {
        font-size: 0.88rem;
        animation: spin-slow 10s linear infinite;
    }

    @keyframes spin-slow {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .err-countdown-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: clamp(8px, 2vw, 14px);
        align-items: center;
        justify-content: center;
    }

    .err-digit-card {
        background: rgba(14, 18, 28, 0.85);
        border: 1px solid rgba(245, 179, 1, 0.25);
        border-radius: 14px;
        padding: clamp(10px, 2vw, 16px) 8px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
        position: relative;
        overflow: hidden;
    }

    .err-digit-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--gold), transparent);
    }

    .err-digit-number {
        font-family: var(--font-display);
        font-size: clamp(1.8rem, 5vw, 2.4rem);
        font-weight: 900;
        line-height: 1;
        color: #ffffff;
        text-shadow: 0 2px 10px rgba(245, 179, 1, 0.4);
        display: block;
        letter-spacing: -0.02em;
    }

    .err-digit-label {
        margin-top: 6px;
        font-size: clamp(0.68rem, 1.8vw, 0.75rem);
        font-weight: 700;
        color: var(--text-dim);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        display: block;
    }

    .err-hotline-box {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        padding: 16px 20px;
        text-align: left;
    }

    .err-hotline-title {
        font-size: 0.84rem;
        font-weight: 700;
        color: #ffffff;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
    }

    .err-hotline-title i {
        color: var(--gold);
    }

    .err-hotline-items {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .err-hotline-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 6px;
        font-size: 0.85rem;
        color: var(--text-slate);
        padding: 6px 10px;
        background: rgba(255, 255, 255, 0.02);
        border-radius: 8px;
        text-decoration: none;
        transition: var(--transition);
    }

    .err-hotline-item:hover {
        background: rgba(245, 179, 1, 0.08);
        color: #ffffff;
    }

    .err-hotline-item span.label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--text-dim);
        font-size: 0.8rem;
    }

    .err-hotline-item span.val {
        color: var(--gold-light);
        font-weight: 600;
    }

    @media (max-width: 480px) {
        .err-countdown-grid {
            gap: 6px;
        }
        .err-digit-card {
            padding: 8px 4px;
        }
        .err-hotline-item {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>
@endpush

@section('conteudo_extra')
    @if($emManutencao)
        @php
            $previsao = $manutencao->previsaoFim();
            $exibirCronometro = $manutencao->exibirCronometro() && $previsao && $previsao->isFuture();
        @endphp

        <div class="err-503-panel">
            @if($exibirCronometro)
                <div id="countdown-box" class="err-timer-box">
                    <div class="err-timer-badge">
                        <i class="fa-solid fa-stopwatch"></i>
                        <span>Previsão de Retorno dos Serviços</span>
                    </div>

                    <div id="countdown-timer" class="err-countdown-grid">
                        <div class="err-digit-card">
                            <span id="cd-horas" class="err-digit-number">00</span>
                            <span class="err-digit-label">Horas</span>
                        </div>
                        <div class="err-digit-card">
                            <span id="cd-minutos" class="err-digit-number">00</span>
                            <span class="err-digit-label">Minutos</span>
                        </div>
                        <div class="err-digit-card">
                            <span id="cd-segundos" class="err-digit-number">00</span>
                            <span class="err-digit-label">Segundos</span>
                        </div>
                    </div>
                </div>

                @push('scripts')
                    <script>
                        (function() {
                            const targetTime = new Date("{{ $previsao->toIso8601String() }}").getTime();
                            const cdBox = document.getElementById('countdown-timer');
                            function updateCountdown() {
                                const now = new Date().getTime();
                                const diff = targetTime - now;
                                if (diff <= 0) {
                                    if (cdBox) {
                                        cdBox.innerHTML = "<div style='grid-column: 1 / -1; padding: 14px; background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.4); border-radius: 12px; color: #4ade80; font-weight: 700; font-size: 0.92rem;'><i class='fa-solid fa-circle-check me-2'></i> Retornando a qualquer instante...</div>";
                                    }
                                    return;
                                }
                                const hours = Math.floor(diff / (1000 * 60 * 60));
                                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                                const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                                const elH = document.getElementById('cd-horas');
                                const elM = document.getElementById('cd-minutos');
                                const elS = document.getElementById('cd-segundos');

                                if (elH) elH.textContent = String(hours).padStart(2, '0');
                                if (elM) elM.textContent = String(minutes).padStart(2, '0');
                                if (elS) elS.textContent = String(seconds).padStart(2, '0');
                            }
                            updateCountdown();
                            setInterval(updateCountdown, 1000);
                        })();
                    </script>
                @endpush
            @endif

            @if($manutencao->exibirPlantao())
                <div class="err-hotline-box">
                    <div class="err-hotline-title">
                        <i class="fa-solid fa-headset"></i>
                        <span>Canais do Plantão Técnico de Emergência:</span>
                    </div>
                    <div class="err-hotline-items">
                        @if($manutencao->telefone())
                            @php
                                $telRaw = preg_replace('/[^\d\+]/', '', (string) $manutencao->telefone());
                            @endphp
                            <a href="tel:{{ $telRaw }}" class="err-hotline-item" title="Ligar para o plantão de suporte">
                                <span class="label"><i class="fa-solid fa-phone"></i> Telefone 24h:</span>
                                <span class="val">{{ $manutencao->telefone() }}</span>
                            </a>
                        @endif
                        @if($manutencao->email())
                            <a href="mailto:{{ $manutencao->email() }}" class="err-hotline-item" title="Enviar e-mail para o suporte">
                                <span class="label"><i class="fa-solid fa-envelope"></i> E-mail Técnico:</span>
                                <span class="val">{{ $manutencao->email() }}</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
@endsection
