@php use App\Services\Whmcs\Cliente as Financeiro; @endphp

{{-- O retrato do financeiro para esta ficha, lido na hora: cadastro completo, serviços e
     faturas. Some por inteiro quando a integração não tem credencial — sem porta, não há o que
     mostrar nem o que prometer. Fatura paga e contrato encerrado aparecem: é o histórico que a
     equipe consulta ao atender, e o filtro de vitrine do cliente não manda aqui. --}}
@if($financeiro['situacao'] !== 'sem_configuracao')
    @php $c = $financeiro['cadastro']; @endphp

    @if($c !== null)
        @php
            $identidade = array_filter([
                'Nome no financeiro' => $c['nome'],
                'Empresa' => $c['empresa'],
                'E-mail' => $c['email'],
                'Telefone' => $c['telefone'],
                'Documento' => $c['documento'],
                'Nascimento' => $c['nascimento']?->format('d/m/Y'),
            ], fn ($v): bool => $v !== null && $v !== '');

            $endereco = array_filter([
                'Endereço' => $c['endereco'],
                'Complemento' => $c['complemento'],
                'Cidade / UF' => trim(($c['cidade'] ?? '').($c['uf'] !== '' ? ' / '.$c['uf'] : ''), ' /'),
                'CEP' => $c['cep'],
                'País' => $c['pais'],
            ], fn ($v): bool => $v !== null && $v !== '');

            $conta = array_filter([
                'Grupo no financeiro' => $c['grupo'],
                'Cliente desde' => $c['desde']?->format('d/m/Y'),
                'Último acesso' => $c['ultimo_acesso']?->format('d/m/Y H:i'),
                'Crédito em conta' => $c['credito_cents'] !== 0 ? brl($c['credito_cents']) : '',
                'Idioma' => $c['idioma'] !== '' ? Financeiro::rotuloDoIdioma($c['idioma']) : '',
                'Moeda' => $c['moeda'],
                'Plataforma' => $c['plataforma'],
                'Usuário da plataforma' => $c['usuario_plataforma'],
                'Nota fiscal' => $c['nota_fiscal'],
                'Observações do financeiro' => $c['observacoes'],
            ], fn ($v): bool => $v !== null && $v !== '');

            $emAberto = collect($financeiro['faturas'] ?? [])
                ->filter(fn (array $fatura): bool => $fatura['saldo_cents'] > 0)
                ->sum('saldo_cents');
        @endphp

        <x-admin.card class="mt-3" title="Cadastro no financeiro" icon="fa-database"
            :descricao="'Conta #'.$c['id'].' no WHMCS — lido na hora, nada é copiado para esta ficha.'">
            @if($c['nfe'])
                <div class="alert alert-info py-2 small mb-3">
                    <i class="fas fa-file-invoice me-1" aria-hidden="true"></i>
                    O financeiro está marcado para <strong>emitir nota fiscal</strong> para esta conta.
                </div>
            @endif
            <div class="row g-3">
                <div class="col-md-6">
                    <dl class="defs">
                        <dt>Situação no financeiro</dt>
                        <dd><x-admin.status :rotulo="Financeiro::rotuloDoCliente($c['situacao'])" :tom="Financeiro::tomDoCliente($c['situacao'])" /></dd>
                        @foreach($identidade as $rotulo => $valor)
                            <dt>{{ $rotulo }}</dt><dd>{{ $valor }}</dd>
                        @endforeach
                        @foreach($endereco as $rotulo => $valor)
                            <dt>{{ $rotulo }}</dt><dd>{{ $valor }}</dd>
                        @endforeach
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="defs">
                        @foreach($conta as $rotulo => $valor)
                            <dt>{{ $rotulo }}</dt><dd>{{ $valor }}</dd>
                        @endforeach
                        @if($c['campos_extras'] !== [])
                            <dt>Outros campos</dt>
                            <dd>
                                @foreach($c['campos_extras'] as $campo)
                                    <div class="fw-normal small">Campo #{{ $campo['id'] }}: {{ $campo['valor'] }}</div>
                                @endforeach
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
        </x-admin.card>
    @elseif($financeiro['situacao'] === 'sem_conta')
        <x-admin.card class="mt-3" title="Cadastro no financeiro" icon="fa-database">
            <p class="small mb-2 text-secondary">
                Esta ficha ainda não está ligada a nenhuma conta do WHMCS, então não há cadastro de lá para mostrar.
                Use <strong>Revincular ao financeiro</strong> na ficha acima: o painel procura a conta pelo e-mail de acesso
                e, quando o financeiro confirma, grava o número.
            </p>
            <p class="small mb-0 text-secondary">
                Se o financeiro responder que não existe conta para esse e-mail, o cadastro de uma das pontas está diferente —
                o daqui ou o de lá — e só a equipe consegue conferir a outra ponta.
            </p>
        </x-admin.card>
    @else
        <x-admin.card class="mt-3" title="Cadastro no financeiro" icon="fa-database">
            <p class="small mb-0 text-secondary">
                <i class="fas fa-triangle-exclamation me-1 text-gold" aria-hidden="true"></i>
                O financeiro não respondeu à consulta da conta #{{ $cliente->whmcs_id }}. Isto <strong>não</strong> quer dizer que o
                cadastro esteja vazio: ele não pôde ser lido agora. Serviços e faturas também não foram consultados — a tela
                evita esperar três vezes o mesmo tempo limite. Tente de novo em instantes.
            </p>
        </x-admin.card>
    @endif

    @if($c !== null)
        <x-admin.table class="mt-3" :headers="['Serviço', 'center:Situação', 'center:Vencimento', 'end:Valor', 'end:Abrir']"
            title="Serviços contratados" icon="fa-satellite-dish"
            :descricao="($financeiro['servicos'] !== null ? count($financeiro['servicos']).' serviço(s) na conta do financeiro.' : null)"
            empty="Nenhum serviço contratado nesta conta do financeiro.">
            @foreach($financeiro['servicos'] ?? [] as $servico)
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $servico['nome'] }}</span>
                        @php
                            $detalhes = array_filter([$servico['grupo'], $servico['dominio'], $servico['servidor'] !== '' ? 'Servidor '.$servico['servidor'] : '']);
                        @endphp
                        @if($detalhes !== [])
                            <div class="text-secondary small">{{ implode(' · ', $detalhes) }}</div>
                        @endif
                    </td>
                    <td class="text-center"><x-admin.status :rotulo="Financeiro::rotuloDoServico($servico['status'])" :tom="Financeiro::tomDoServico($servico['status'])" /></td>
                    <td class="text-center small text-nowrap">{{ $servico['proximo_vencimento']?->format('d/m/Y') ?? '—' }}</td>
                    <td class="text-end">
                        <span class="fw-semibold text-nowrap d-block">{{ brl($servico['valor_cents']) }}</span>
                        <span class="text-secondary small">{{ $servico['ciclo'] !== '' ? Financeiro::rotuloDoCiclo($servico['ciclo']) : '—' }}</span>
                    </td>
                    <td class="text-end">
                        @if($servico['url'] !== '')
                            <a class="btn btn-sm btn-outline-secondary" href="{{ $servico['url'] }}" target="_blank" rel="noopener noreferrer"
                               aria-label="Abrir {{ $servico['nome'] }} no financeiro"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                        @else
                            <span class="small text-secondary">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if($financeiro['servicos'] === null)
                <tr>
                    <td colspan="5" class="text-center text-secondary py-4 small">
                        O financeiro não respondeu à consulta de serviços. A lista não está vazia — ela não pôde ser lida agora.
                    </td>
                </tr>
            @endif
        </x-admin.table>

        <x-admin.table class="mt-3" :headers="['Fatura', 'center:Vencimento', 'center:Situação', 'end:Total', 'end:Abrir']"
            title="Faturas" icon="fa-file-invoice-dollar"
            :descricao="($financeiro['faturas'] !== null
                ? count($financeiro['faturas']).' fatura(s) no financeiro'.($emAberto > 0 ? ' — '.brl($emAberto).' em aberto.' : '.')
                : null)"
            empty="Nenhuma fatura emitida nesta conta do financeiro.">
            @foreach($financeiro['faturas'] ?? [] as $fatura)
                <tr>
                    <td>
                        <span class="fw-semibold text-nowrap">#{{ $fatura['numero'] }}</span>
                        <div class="text-secondary small">
                            @if($fatura['paga_em'])
                                Paga em {{ $fatura['paga_em']->format('d/m/Y') }}
                            @elseif($fatura['emitida'])
                                Emitida em {{ $fatura['emitida']->format('d/m/Y') }}
                            @endif
                        </div>
                    </td>
                    <td class="text-center small text-nowrap">{{ $fatura['vencimento']?->format('d/m/Y') ?? '—' }}</td>
                    <td class="text-center"><x-admin.status :rotulo="Financeiro::rotuloDaFatura($fatura['status'])" :tom="Financeiro::tomDaFatura($fatura['status'])" /></td>
                    <td class="text-end">
                        <span class="small text-nowrap d-block">{{ brl($fatura['total_cents']) }}</span>
                        @if($fatura['saldo_cents'] > 0)
                            <span class="small fw-semibold text-danger text-nowrap">{{ brl($fatura['saldo_cents']) }} em aberto</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($fatura['url'] !== '')
                            <a class="btn btn-sm btn-outline-secondary" href="{{ $fatura['url'] }}" target="_blank" rel="noopener noreferrer"
                               aria-label="Abrir a fatura {{ $fatura['numero'] }} no financeiro"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                        @else
                            <span class="small text-secondary">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if($financeiro['faturas'] === null)
                <tr>
                    <td colspan="5" class="text-center text-secondary py-4 small">
                        O financeiro não respondeu à consulta de faturas. A lista não está vazia — ela não pôde ser lida agora.
                    </td>
                </tr>
            @endif
        </x-admin.table>
    @endif
@endif
