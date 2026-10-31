{{--
    O relatório do Google Ads com a leitura do copiloto.

    A métrica sai inteira da cache — esta tela nunca espera o Google. Quando a janela pedida
    ainda não chegou, o card de carregamento pergunta de novo sozinho (wire:poll) até os números
    aparecerem; quando ela está aqui, o botão “Analisar” entrega os números ao copiloto e a
    leitura fica gravada no histórico, junto com a foto do período.

    Um <div> só envolve tudo porque o Livewire exige uma única raiz por componente.
--}}
@php
    $rotulos = (array) config('google_ads.rotulos_metricas');
    $impactos = (array) config('google_ads.copiloto.impactos');
@endphp

<div>
@if ($conta === null)
    <x-admin.card title="Ligar o Google Ads" icon="fa-brands fa-google"
        descricao="O relatório lê a mesma métrica da tela de campanhas — e só existe depois de uma conta conectada.">
        <p class="small text-secondary mb-3">
            Conecte a conta do anunciante para o painel ler campanhas, cliques, custo e conversões.
            O copiloto usa esses números para escrever o diagnóstico do período.
        </p>
        <a href="{{ route('admin.google-ads.index') }}" class="btn btn-golden btn-sm">
            <i class="fas fa-chart-line me-1" aria-hidden="true"></i> Ir para a tela de métricas
        </a>
    </x-admin.card>
@else
    <div class="card shadow-sm filters-card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                @if ($contas->count() > 1)
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="rel-conta">Conta</label>
                        <select id="rel-conta" class="form-select form-select-sm" wire:model.live="contaId">
                            @foreach ($contas as $outra)
                                <option value="{{ $outra->id }}">{{ $outra->nomeTela() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-6 col-md-3">
                    <label class="form-label" for="rel-dias">Período</label>
                    <select id="rel-dias" class="form-select form-select-sm" wire:model.live="dias">
                        @foreach ($periodos as $janela)
                            <option value="{{ $janela }}">Últimos {{ $janela }} dias</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md d-flex flex-wrap gap-2 align-items-center">
                    <button type="button" class="btn btn-sm btn-golden" wire:click="gerar"
                        wire:loading.attr="disabled" wire:target="gerar">
                        <i class="fas fa-wand-magic-sparkles me-1" aria-hidden="true"></i> Analisar com o copiloto
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="atualizar"
                        wire:loading.attr="disabled" wire:target="atualizar">
                        <i class="fas fa-rotate me-1" aria-hidden="true"></i> Atualizar números
                    </button>
                    <a class="btn btn-sm btn-outline-secondary"
                        href="{{ route('admin.google-ads.relatorio.exportar', ['dias' => $dias, 'conta' => $conta->id]) }}">
                        <i class="fas fa-file-csv me-1" aria-hidden="true"></i> Baixar CSV
                    </a>
                    <span class="form-hint" wire:loading wire:target="gerar">O copiloto está lendo os números… isso leva alguns segundos.</span>
                </div>

                <div class="col-12 small text-secondary">
                    {{ $conta->nomeTela() }} · <code>{{ $conta->numeroConta() }}</code>
                    @if ($sincronizado_em)
                        · métrica de {{ \Illuminate\Support\Carbon::parse($sincronizado_em)->format('d/m/Y H:i') }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if (! $ia_pronta)
        <div class="card shadow-sm mb-3 ads-aviso">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center py-3">
                <span class="text-tone-amber"><i class="fas fa-key" aria-hidden="true"></i></span>
                <div class="flex-grow-1 small">
                    <div class="fw-semibold">A leitura do copiloto está sem chave de IA</div>
                    <div class="text-secondary">
                        Os números continuam aqui; só a análise fica de fora até a capacidade “conversa” ter provedor ligado.
                        @foreach ($ia_faltando as $aviso)
                            <br>{{ $aviso }}
                        @endforeach
                    </div>
                </div>
                @can('admin')
                    <a href="{{ route('admin.settings.index') }}#aba-chaves" class="btn btn-sm btn-golden">Abrir as chaves</a>
                @endcan
            </div>
        </div>
    @endif

    @if ($falha)
        <div class="card shadow-sm mb-3 ads-aviso">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center py-3">
                <span class="text-tone-red"><i class="fas fa-circle-exclamation" aria-hidden="true"></i></span>
                <div class="flex-grow-1 small">{{ $falha }}</div>
            </div>
        </div>
    @endif

    @if ($erro)
        <div class="card shadow-sm mb-3 ads-aviso">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center py-3">
                <span class="text-tone-red"><i class="fas fa-circle-exclamation" aria-hidden="true"></i></span>
                <div class="flex-grow-1 small">
                    <div class="fw-semibold">A última sincronização não terminou</div>
                    <div class="text-secondary">{{ $erro['mensagem'] }}</div>
                    <div class="text-secondary">Desde {{ \Illuminate\Support\Carbon::parse($erro['desde'])->format('d/m/Y H:i') }}.</div>
                </div>
                <button type="button" class="btn btn-sm btn-golden" wire:click="atualizar">Tentar de novo</button>
            </div>
        </div>
    @endif

    @if ($carregando)
        <div class="card shadow-sm mb-3" wire:poll.3000ms="$refresh">
            <div class="card-body d-flex align-items-center gap-3 py-4">
                <span class="spinner-border spinner-border-sm text-tone-gold" aria-hidden="true"></span>
                <div>
                    <div class="fw-semibold">Carregando…</div>
                    <div class="small text-secondary">
                        A métrica destes {{ $dias }} dias está sendo sincronizada em segundo plano.
                        Esta tela se atualiza sozinha assim que os números chegam.
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($resumo)
        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6 col-lg-4">
                <x-admin.stat :label="$rotulos['impressoes']" :value="number_format($resumo['impressoes'], 0, ',', '.')" icon="fa-eye" tone="blue" pequeno />
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <x-admin.stat :label="$rotulos['cliques']" :value="number_format($resumo['cliques'], 0, ',', '.')" icon="fa-hand-pointer" tone="teal" pequeno
                    :hint="'CPC médio '.brl($resumo['cpc_medio_centavos'])" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <x-admin.stat :label="$rotulos['custo']" :value="brl($resumo['custo_centavos'])" icon="fa-sack-dollar" tone="gold" pequeno />
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <x-admin.stat :label="$rotulos['conversoes']" :value="number_format($resumo['conversoes'], 0, ',', '.')" icon="fa-circle-check" tone="green" pequeno
                    :hint="'Valor '.brl($resumo['valor_conversoes_centavos'])" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <x-admin.stat label="CTR médio" :value="$resumo['ctr'].'%'" icon="fa-percent" tone="violet" pequeno />
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <x-admin.stat :label="$rotulos['roas']"
                    :value="$resumo['roas'] === null ? '—' : number_format($resumo['roas'], 2, ',', '.').'×'"
                    icon="fa-arrow-trend-up" tone="amber" pequeno
                    :hint="$resumo['roas'] === null ? 'Sem valor de conversão registrado' : 'Para cada R$ 1 investido'" />
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <x-admin.table
                :headers="[$rotulos['campanha'], $rotulos['status'], 'center:'.$rotulos['impressoes'], 'center:'.$rotulos['cliques'], 'center:CTR', 'end:'.$rotulos['custo'], 'center:'.$rotulos['conversoes'], 'end:'.$rotulos['valor_conversoes'], 'end:'.$rotulos['roas']]"
                :empty="$carregando ? 'Os números chegam assim que a fila terminar.' : 'Nenhuma campanha com métrica neste período.'">
                @foreach ($campanhas as $c)
                    <tr wire:key="campanha-{{ $c['id'] }}">
                        <td>
                            <div class="fw-semibold">{{ $c['nome'] }}</div>
                            <div class="small text-secondary"><code>{{ $c['id'] }}</code></div>
                        </td>
                        <td><x-admin.status :rotulo="$c['status_rotulo']" :tom="tom_por_status('campanha_ads', $c['status'])" /></td>
                        <td class="text-center">{{ number_format($c['impressoes'], 0, ',', '.') }}</td>
                        <td class="text-center">{{ number_format($c['cliques'], 0, ',', '.') }}</td>
                        <td class="text-center">{{ number_format($c['ctr'], 2, ',', '.') }}%</td>
                        <td class="text-end">{{ brl($c['custo_centavos']) }}</td>
                        <td class="text-center">{{ number_format($c['conversoes'], 0, ',', '.') }}</td>
                        <td class="text-end small">{{ brl($c['valor_conversoes_centavos']) }}</td>
                        <td class="text-end">{{ $c['roas'] === null ? '—' : number_format($c['roas'], 2, ',', '.').'×' }}</td>
                    </tr>
                @endforeach
            </x-admin.table>

            @if ($campanhas->hasPages())
                <div class="card shadow-sm mt-2">
                    <div class="card-body py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span class="small text-secondary">
                            Página {{ $campanhas->currentPage() }} de {{ $campanhas->lastPage() }}
                            · {{ $campanhas->total() }} campanha(s)
                        </span>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="paginaAnterior"
                                @disabled($campanhas->onFirstPage())>
                                <i class="fas fa-chevron-left me-1" aria-hidden="true"></i> Anterior
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="proximaPagina"
                                @disabled(! $campanhas->hasMorePages())>
                                Próxima <i class="fas fa-chevron-right ms-1" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <x-admin.card title="Leitura do copiloto" icon="fa-robot"
                descricao="O que os números do período dizem — gravado junto com a foto deles.">
                @if ($relatorio)
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <span class="small text-secondary">
                            Período de {{ $relatorio->dias }} dias
                            · {{ $relatorio->gerado_em?->format('d/m/Y H:i') }}
                            @if ($relatorio->modelo)
                                · <code>{{ $relatorio->modelo }}</code>
                            @endif
                        </span>
                        @if ($relatorio->user && $relatorio->user_id !== auth()->id())
                            <span class="small text-secondary">por {{ $relatorio->user->name }}</span>
                        @endif
                    </div>

                    <p class="small mb-3">{{ $relatorio->diagnostico() }}</p>

                    @if ($relatorio->destaques() !== [])
                        <div class="ads-painel mb-3">
                            <div class="ads-painel__titulo">Destaques</div>
                            <ul class="mb-0 ps-3 small">
                                @foreach ($relatorio->destaques() as $destaque)
                                    <li>{{ $destaque }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($relatorio->recomendacoes() !== [])
                        <div class="ads-painel__titulo">Recomendações</div>
                        <div class="d-flex flex-column gap-2">
                            @foreach ($relatorio->recomendacoes() as $rec)
                                <div class="ads-recomendacao">
                                    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
                                        <div class="fw-semibold small">{{ $rec['titulo'] ?? '' }}</div>
                                        <x-admin.status :rotulo="'Impacto '.($impactos[$rec['impacto'] ?? ''] ?? ($rec['impacto'] ?? '—'))"
                                            :tom="tom_por_status('impacto_ads', $rec['impacto'] ?? '')" />
                                    </div>
                                    <div class="small text-secondary">{{ $rec['detalhe'] ?? '' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @else
                    <p class="small text-secondary mb-3">
                        Nenhuma análise guardada para {{ $dias }} dias nesta conta.
                        @if ($resumo)
                            Clique em <strong>Analisar com o copiloto</strong> para receber o diagnóstico do período — a leitura fica salva abaixo.
                        @else
                            Os números ainda estão chegando; a análise abre assim que eles aparecerem.
                        @endif
                    </p>
                @endif

                @if ($resumo && $ia_pronta)
                    <button type="button" class="btn btn-golden btn-sm mt-3" wire:click="gerar"
                        wire:loading.attr="disabled" wire:target="gerar">
                        <i class="fas fa-wand-magic-sparkles me-1" aria-hidden="true"></i>
                        {{ $relatorio ? 'Analisar de novo' : 'Analisar agora' }}
                    </button>
                    <span class="form-hint ms-2" wire:loading wire:target="gerar">Lendo os números…</span>
                @endif
            </x-admin.card>

            @if ($relatorios->isNotEmpty())
                <x-admin.card title="Histórico" icon="fa-clock-rotate-left" class="mt-3"
                    descricao="Cada leitura guarda a foto do período; abrir uma antiga não chama a IA de novo.">
                    <div class="d-flex flex-column gap-2">
                        @foreach ($relatorios as $antigo)
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 ads-recomendacao {{ $relatorio && $relatorio->id === $antigo->id ? 'is-aberta' : '' }}">
                                <div class="small">
                                    <div class="fw-semibold">{{ $antigo->gerado_em?->format('d/m/Y H:i') }}</div>
                                    <div class="text-secondary">
                                        {{ $antigo->dias }} dias
                                        @if ($antigo->user)
                                            · {{ $antigo->user->name }}
                                        @endif
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                    wire:click="ver({{ $antigo->id }})"
                                    @disabled($relatorio && $relatorio->id === $antigo->id)>
                                    {{ $relatorio && $relatorio->id === $antigo->id ? 'Aberta' : 'Ver' }}
                                </button>
                            </div>
                        @endforeach
                    </div>
                </x-admin.card>
            @endif
        </div>
    </div>
@endif
</div>
