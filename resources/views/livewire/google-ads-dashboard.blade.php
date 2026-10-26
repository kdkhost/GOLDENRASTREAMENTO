{{--
    A tela de Google Ads do painel.

    Nada aqui chama a API do Google: quem fala com o Google é o job de fila, e esta tela só lê a
    cache que ele preenche. Por isso existe o "Carregando…" com polling — o número que falta está
    a caminho, e a tela pergunta de novo sozinha até ele chegar.

    Os filtros reusam o .filters-card do painel, mas amarrados ao componente (wire:model) em vez
    de um formulário GET: trocar de período pode exigir sincronização, e um <form> de HTML puro só
    conseguiria pedir isso recarregando a página inteira.

    Antes da primeira conta conectada a tela é uma só: o cartão que diz o que falta e leva ao
    lugar certo. O passo a passo completo não é despejado aqui — o cabeçalho já traz "Como usar
    esta tela", que abre a seção do guia, escrita uma vez só.

    Um <div> só envolve tudo porque o Livewire exige uma única raiz por componente.
--}}
<div>
@if ($conta === null)
    <x-admin.card title="Ligar o Google Ads" icon="fa-brands fa-google"
        descricao="Depois de ligado, esta tela mostra impressões, cliques, custo, conversões e ROAS das campanhas — lidos da cache que a fila preenche.">
        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-7">
                <ul class="ads-passos list-unstyled mb-0">
                    <li class="{{ $configurado ? 'is-feito' : 'is-pendente' }}">
                        <span class="ads-passos__marca"><i class="fas {{ $configurado ? 'fa-check' : 'fa-key' }}" aria-hidden="true"></i></span>
                        <div>
                            <div class="fw-semibold">Credenciais da API</div>
                            @if ($configurado)
                                <div class="small text-secondary">Token de desenvolvedor e aplicação OAuth preenchidos.</div>
                            @else
                                <div class="small text-secondary">
                                    Falta{{ count($credenciais_faltando) === 1 ? '' : 'm' }}
                                    {{ count($credenciais_faltando) }}
                                    {{ count($credenciais_faltando) === 1 ? 'campo' : 'campos' }} — veja ao lado.
                                    @can('admin')
                                        Quem preenche é a tela de chaves, não o servidor.
                                    @else
                                        Peça ao administrador para preencher.
                                    @endcan
                                </div>                            @endif
                        </div>
                    </li>
                    <li>
                        <span class="ads-passos__marca"><i class="fas fa-plug" aria-hidden="true"></i></span>
                        <div>
                            <div class="fw-semibold">Conta do anunciante</div>
                            <div class="small text-secondary">
                                O Google pergunta em qual conta você entra e quais permissões dá ao painel.
                                Para nós ele só lê; campanha só pausa ou reativa com confirmação.
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="col-12 col-lg-5">
                <div class="ads-painel">
                    @if (! $configurado)
                        <div class="ads-painel__titulo">O que falta preencher</div>
                        <ul class="ads-faltas list-unstyled mb-3">
                            @foreach ($credenciais_faltando as $nome)
                                <li><i class="fas fa-circle-dot" aria-hidden="true"></i> <code>{{ $nome }}</code></li>
                            @endforeach
                        </ul>
                        @can('admin')
                            <a href="{{ route('admin.settings.index') }}#chaves-google-ads" class="btn btn-golden btn-sm w-100">
                                <i class="fas fa-key me-1" aria-hidden="true"></i> Abrir as chaves de integração
                            </a>
                        @else
                            <div class="small text-secondary">As chaves ficam com o administrador.</div>
                        @endcan
                    @else
                        <div class="ads-painel__titulo">Tudo pronto para conectar</div>
                        <p class="small text-secondary mb-3">
                            A conta aparece aqui com o nome, o número e a moeda que o Google devolver.
                        </p>
                        <a href="{{ route('google-ads.redirect') }}" class="btn btn-golden btn-sm w-100">
                            <i class="fa-brands fa-google me-1" aria-hidden="true"></i> Conectar conta Google Ads
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </x-admin.card>
@else
    <div class="card shadow-sm mb-3 ads-conta">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="icon tone-gold"><i class="fa-brands fa-google" aria-hidden="true"></i></div>
            <div class="flex-grow-1 overflow-hidden">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-bold">{{ $conta->nomeTela() }}</span>
                    <x-admin.status :rotulo="$conta->rotuloStatus()" :tom="tom_por_status('conexao_ads', $conta->status)" />
                </div>
                <div class="small text-secondary">
                    <code>{{ $conta->numeroConta() }}</code>
                    @if ($conta->currency_code) · {{ $conta->currency_code }} @endif
                    @if ($sincronizado_em)
                        · métrica de {{ \Illuminate\Support\Carbon::parse($sincronizado_em)->format('d/m/Y H:i') }}
                    @endif
                    @if ($conta->user_id === auth()->id())
                        · conectada por você
                    @else
                        · conectada por {{ $conta->user?->name }}
                    @endif
                </div>
            </div>
            @if ($conta->status !== \App\Models\GoogleAdsAccount::ATIVO)
                <a href="{{ route('google-ads.redirect') }}" class="btn btn-sm btn-golden">
                    <i class="fas fa-rotate me-1" aria-hidden="true"></i> Reconectar
                </a>
            @endif
        </div>

        @if ($conta->motivo_desconexao)
            <div class="card-footer bg-body-tertiary small text-tone-red">
                <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i> {{ $conta->motivo_desconexao }}
            </div>
        @endif
    </div>

    <div class="card shadow-sm filters-card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                @if ($contas->count() > 1)
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="flt-conta">Conta</label>
                        <select id="flt-conta" class="form-select form-select-sm" wire:model.live="contaId">
                            @foreach ($contas as $outra)
                                <option value="{{ $outra->id }}">{{ $outra->nomeTela() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-6 col-md-3">
                    <label class="form-label" for="flt-dias">Período</label>
                    <select id="flt-dias" class="form-select form-select-sm" wire:model.live="dias">
                        @foreach ($periodos as $janela)
                            <option value="{{ $janela }}">Últimos {{ $janela }} dias</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md">
                    <label class="form-label" for="flt-busca">Buscar</label>
                    <input type="search" id="flt-busca" class="form-control form-control-sm"
                        wire:model.live.debounce.400ms="busca" placeholder="Nome ou número da campanha...">
                </div>

                @if ($situacoes !== [])
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="flt-situacao">Situação</label>
                        <select id="flt-situacao" class="form-select form-select-sm" wire:model.live="situacao">
                            <option value="">Todas</option>
                            @foreach ($situacoes as $codigo => $rotulo)
                                <option value="{{ $codigo }}">{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                    <button type="button" class="btn btn-sm btn-golden" wire:click="atualizar"
                        wire:loading.attr="disabled" wire:target="atualizar">
                        <i class="fas fa-rotate me-1" aria-hidden="true"></i> Atualizar agora
                    </button>
                    <a href="{{ route('admin.google-ads.index') }}" class="btn btn-sm btn-outline-secondary">Limpar filtros</a>
                    <span class="form-hint" wire:loading wire:target="atualizar">Pedindo a conta do Google…</span>
                </div>
            </div>
        </div>
    </div>

    @if ($erro)
        <div class="card shadow-sm mb-3 ads-aviso">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center py-4">
                <span class="text-tone-red"><i class="fas fa-circle-exclamation fa-lg" aria-hidden="true"></i></span>
                <div class="flex-grow-1">
                    <div class="fw-semibold">A última sincronização não terminou</div>
                    <div class="small text-secondary">{{ $erro['mensagem'] }}</div>
                    <div class="small text-secondary">
                        Desde {{ \Illuminate\Support\Carbon::parse($erro['desde'])->format('d/m/Y H:i') }}.
                    </div>
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
                        Esta tela se atualiza sozinha assim que os números chegam — nada aqui espera pelo Google.
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
                <x-admin.stat :label="$rotulos['conversoes']" :value="number_format($resumo['conversoes'], 0, ',', '.')" icon="fa-circle-check" tone="green" pequeno />
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <x-admin.stat label="CTR médio" :value="$resumo['ctr'].'%'" icon="fa-percent" tone="violet" pequeno />
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <x-admin.stat :label="$rotulos['roas']"
                    :value="$resumo['roas'] === null ? '—' : number_format($resumo['roas'], 2, ',', '.').'×'"
                    icon="fa-arrow-trend-up" tone="amber" pequeno
                    :hint="'Valor das conversões '.brl($resumo['valor_conversoes_centavos'])" />
            </div>
        </div>
    @endif

    <x-admin.table
        :headers="[$rotulos['campanha'], $rotulos['status'], 'center:'.$rotulos['impressoes'], 'center:'.$rotulos['cliques'], 'center:CTR', 'end:CPC', 'end:'.$rotulos['custo'], 'center:'.$rotulos['conversoes'], 'end:'.$rotulos['valor_conversoes'], 'end:'.$rotulos['roas'], 'end:Ações']"
        :empty="$carregando ? 'Os números chegam assim que a fila terminar.' : 'Nenhuma campanha com esse filtro.'"
        :paginator="$campanhas">
        @foreach ($campanhas as $c)
            <tr>
                <td>
                    <div class="fw-semibold">{{ $c['nome'] }}</div>
                    <div class="small text-secondary"><code>{{ $c['id'] }}</code></div>
                </td>
                <td><x-admin.status :rotulo="$c['status_rotulo']" :tom="tom_por_status('campanha_ads', $c['status'])" /></td>
                <td class="text-center">{{ number_format($c['impressoes'], 0, ',', '.') }}</td>
                <td class="text-center">{{ number_format($c['cliques'], 0, ',', '.') }}</td>
                <td class="text-center">{{ number_format($c['ctr'], 2, ',', '.') }}%</td>
                <td class="text-end small">{{ brl($c['cpc_medio_centavos']) }}</td>
                <td class="text-end">{{ brl($c['custo_centavos']) }}</td>
                <td class="text-center">{{ number_format($c['conversoes'], 0, ',', '.') }}</td>
                <td class="text-end small">{{ brl($c['valor_conversoes_centavos']) }}</td>
                <td class="text-end">
                    {{ $c['roas'] === null ? '—' : number_format($c['roas'], 2, ',', '.').'×' }}
                </td>
                <td>
                    <div class="table-actions">
                        @if (in_array($c['status'], \App\Services\GoogleAds\GoogleAdsService::STATUS_ESCREVEVEIS, true))
                            @php $pausar = $c['status'] === 'ENABLED'; @endphp
                            <form method="POST"
                                action="{{ route('admin.google-ads.campanhas.status', array_merge(['campanha' => $c['id']], array_filter(['dias' => $dias, 'busca' => $busca ?: null, 'situacao' => $situacao ?: null, 'conta' => $contaId]))) }}"
                                data-confirmar="{{ $pausar ? 'Pausar '.$c['nome'].' no Google Ads? Os anúncios param de ser exibidos até você reativar.' : 'Reativar '.$c['nome'].' no Google Ads? A campanha volta a gastar assim que o Google aceitar.' }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $pausar ? 'PAUSED' : 'ENABLED' }}">
                                <input type="hidden" name="conta" value="{{ $conta->id }}">
                                <input type="hidden" name="dias" value="{{ $dias }}">
                                <button class="btn btn-sm btn-outline-secondary"
                                    title="{{ $pausar ? 'Pausar no Google' : 'Ativar no Google' }}">
                                    <i class="fas fa-{{ $pausar ? 'pause' : 'play' }}" aria-hidden="true"></i>
                                </button>
                            </form>
                        @else
                            <span class="text-secondary small" title="O painel só escreve em campanhas ativas ou pausadas.">—</span>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-admin.table>
@endif
</div>
