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

@section('conteudo_extra')
    @if($emManutencao)
        @php
            $previsao = $manutencao->previsaoFim();
            $exibirCronometro = $manutencao->exibirCronometro() && $previsao && $previsao->isFuture();
        @endphp

        @if($exibirCronometro)
            <div id="countdown-box" style="margin: 24px auto; padding: 18px 24px; background: rgba(245, 179, 1, 0.08); border: 1px dashed rgba(245, 179, 1, 0.35); border-radius: 16px; max-width: 440px;">
                <p style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--gold); font-weight: 700; margin-bottom: 10px;">
                    <i class="fas fa-stopwatch me-1"></i> Previsão de Retorno dos Serviços
                </p>
                <div id="countdown-timer" style="display: flex; justify-content: center; gap: 14px; font-weight: 800; font-size: 1.4rem; color: #fff;">
                    <div><span id="cd-horas" style="color: var(--gold);">00</span><span style="display: block; font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; font-weight: 600;">Horas</span></div>
                    <span>:</span>
                    <div><span id="cd-minutos" style="color: var(--gold);">00</span><span style="display: block; font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; font-weight: 600;">Minutos</span></div>
                    <span>:</span>
                    <div><span id="cd-segundos" style="color: var(--gold);">00</span><span style="display: block; font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; font-weight: 600;">Segundos</span></div>
                </div>
            </div>

            @push('scripts')
                <script>
                    (function() {
                        const targetTime = new Date("{{ $previsao->toIso8601String() }}").getTime();
                        function updateCountdown() {
                            const now = new Date().getTime();
                            const diff = targetTime - now;
                            if (diff <= 0) {
                                document.getElementById('countdown-timer').innerHTML = "<span style='color: #4ade80;'>Retornando a qualquer instante...</span>";
                                return;
                            }
                            const hours = Math.floor(diff / (1000 * 60 * 60));
                            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                            document.getElementById('cd-horas').textContent = String(hours).padStart(2, '0');
                            document.getElementById('cd-minutos').textContent = String(minutes).padStart(2, '0');
                            document.getElementById('cd-segundos').textContent = String(seconds).padStart(2, '0');
                        }
                        updateCountdown();
                        setInterval(updateCountdown, 1000);
                    })();
                </script>
            @endpush
        @endif

        @if($manutencao->exibirPlantao())
            <div style="margin: 20px auto 28px; padding: 14px 20px; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 14px; max-width: 480px; font-size: 0.88rem; color: var(--text-muted); text-align: left;">
                <p style="font-weight: 700; color: #fff; margin-bottom: 8px;"><i class="fas fa-headset text-gold me-2"></i> Canais de Plantão de Emergência:</p>
                @if($manutencao->telefone())
                    <p style="margin-bottom: 4px;"><i class="fas fa-phone me-2 text-gold"></i> Telefone: <strong style="color: #fff;">{{ $manutencao->telefone() }}</strong></p>
                @endif
                @if($manutencao->email())
                    <p style="margin-bottom: 0;"><i class="fas fa-envelope me-2 text-gold"></i> E-mail: <strong style="color: #fff;">{{ $manutencao->email() }}</strong></p>
                @endif
            </div>
        @endif
    @endif
@endsection

