@php use App\Services\Whmcs\Cliente as Financeiro; @endphp

{{-- O retrato do financeiro para esta ficha, lido na hora: o cadastro inteiro e a conta completa —
     serviços, adicionais, domínios, faturas, transações, créditos, pedidos, contatos, chamados e
     orçamentos. Some por inteiro quando a integração não tem credencial — sem porta, não há o que
     mostrar nem o que prometer. Fatura paga e contrato encerrado aparecem: é o histórico que a
     equipe consulta ao atender, e o filtro de vitrine do cliente não manda aqui.

     Cada lista existe em três estados, e a tela distingue os três: lida e vazia (a tabela diz
     "nenhum"), lida e cheia, ou não lida (a linha `financeiro-mudo` explica que nada foi consultado
     de errado por descuido — o financeiro é que não respondeu). --}}
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
                'IP do último acesso' => $c['ultimo_ip'],
                'Crédito em conta' => $c['credito_cents'] !== 0 ? brl($c['credito_cents']) : '',
                'Idioma' => $c['idioma'] !== '' ? Financeiro::rotuloDoIdioma($c['idioma']) : '',
                'Moeda' => $c['moeda'],
                'Meio de pagamento padrão' => $c['forma_de_pagamento'],
                'Plataforma' => $c['plataforma'],
                'Usuário da plataforma' => $c['usuario_plataforma'],
                'Nota fiscal' => $c['nota_fiscal'],
                'Observações do financeiro' => $c['observacoes'],
            ], fn ($v): bool => $v !== null && $v !== '');

            // O que o financeiro já calculou para a própria ficha: a soma dele, não uma conta
            // refeita aqui. Chave que ele não devolveu não aparece inventada.
            $resumo = array_filter([
                'Faturas em aberto' => isset($c['resumo']['faturas_em_aberto']) ? (string) $c['resumo']['faturas_em_aberto'] : '',
                'Valor em aberto' => isset($c['resumo']['em_aberto_cents']) ? brl($c['resumo']['em_aberto_cents']) : '',
                'Próximo vencimento' => isset($c['resumo']['proximo_vencimento']) ? $c['resumo']['proximo_vencimento']->format('d/m/Y') : '',
                'Serviços' => isset($c['resumo']['servicos']) ? $c['resumo']['servicos'].' ('.($c['resumo']['servicos_ativos'] ?? 0).' ativos)' : '',
                'Domínios' => isset($c['resumo']['dominios']) ? (string) $c['resumo']['dominios'] : '',
            ], fn ($v): bool => $v !== null && $v !== '');

            // As caixas da conta, com a situação que o financeiro escreveu. `null` é "não
            // respondeu aquela chave" e aparece como "não informado" — nunca como "não", que
            // mudaria a leitura de uma cobrança automática.
            $sinais = [
                'E-mail verificado' => $c['email_verificado'],
                'Cobrança automática no cartão' => $c['cobranca_automatica'],
                'Recebe e-mails do financeiro' => $c['recebe_emails'],
                'Recebe promoções' => $c['recebe_marketing'],
                'Recebe avisos de vencimento' => $c['avisos_de_vencimento'],
                'Juros por atraso' => $c['juros_por_atraso'],
                'Faturas separadas por serviço' => $c['faturas_separadas'],
                'Isento de imposto' => $c['isento_de_imposto'],
                'Entrar como (login único)' => $c['login_unico'],
            ];

            $cartao = $c['cartao_de_reserva'];

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
                        @if($cartao !== [])
                            <dt>Cartão em reserva</dt>
                            <dd>
                                {{ $cartao['bandeira'] ?: 'Cartão' }}
                                @if($cartao['ultimos_digitos'] !== '')
                                    <span class="fw-normal small text-secondary">terminando {{ $cartao['ultimos_digitos'] }}</span>
                                @endif
                                @if($cartao['validade'] !== '')
                                    <span class="fw-normal small text-secondary">validade {{ $cartao['validade'] }}</span>
                                @endif
                            </dd>
                        @endif
                        @if($c['contato_de_cobranca'] !== null && $c['contato_de_cobranca'] > 0)
                            <dt>Contato de cobrança</dt>
                            <dd>Contato #{{ $c['contato_de_cobranca'] }} da conta</dd>
                        @endif
                        @if($resumo !== [])
                            <dt>Resumo do financeiro</dt>
                            <dd>
                                @foreach($resumo as $rotuloResumo => $valorResumo)
                                    <div class="fw-normal small">{{ $rotuloResumo }}: {{ $valorResumo }}</div>
                                @endforeach
                            </dd>
                        @endif
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

            <hr>

            <div class="row g-3">
                <div class="col-md-6">
                    <h2 class="h6 mb-2">Caixas da conta no financeiro</h2>
                    <ul class="list-unstyled small mb-0 d-flex flex-wrap gap-2">
                        @foreach($sinais as $rotuloSinal => $valorSinal)
                            <li>
                                <span class="text-secondary">{{ $rotuloSinal }}:</span>
                                <x-admin.status :rotulo="Financeiro::rotuloDoSinal($valorSinal)" :tom="Financeiro::tomDoSinal($valorSinal)" />
                            </li>
                        @endforeach
                    </ul>
                    @if($c['preferencias_de_email'] !== [])
                        <div class="small text-secondary mt-2">
                            Preferências de e-mail declaradas:
                            @foreach($c['preferencias_de_email'] as $preferencia)
                                <x-admin.status :rotulo="$preferencia['rotulo'].($preferencia['ativo'] ? '' : ' (desligado)')" :tom="$preferencia['ativo'] ? 'green' : 'neutral'" />
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="col-md-6">
                    <h2 class="h6 mb-2">
                        Quem tem acesso à conta
                        @if($c['usuarios'] !== [])
                            <span class="form-hint d-block fw-normal">Nome e e-mail de cada acesso; senha e token não saem do financeiro.</span>
                        @endif
                    </h2>
                    @if($c['usuarios'] === [])
                        <p class="small text-secondary mb-0">
                            O financeiro não listou acessos além do cadastro principal — ou não devolveu essa informação.
                        </p>
                    @else
                        <ul class="list-unstyled small mb-0">
                            @foreach($c['usuarios'] as $usuario)
                                <li class="mb-1">
                                    <span class="fw-semibold">{{ $usuario['nome'] ?: 'Sem nome' }}</span>
                                    <span class="text-secondary">{{ $usuario['email'] }}</span>
                                    @if($usuario['titular'] === true)
                                        <x-admin.status rotulo="Titular" tom="green" />
                                    @elseif($usuario['titular'] === false)
                                        <x-admin.status rotulo="Subconta" tom="blue" />
                                    @endif
                                    @if($usuario['ultimo_acesso'])
                                        <div class="text-secondary">Último acesso {{ $usuario['ultimo_acesso']->format('d/m/Y H:i') }}</div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
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
                cadastro esteja vazio: ele não pôde ser lido agora. Nenhuma outra lista foi consultada — a tela
                evita esperar dez vezes o mesmo tempo limite. Tente de novo em instantes.
            </p>
        </x-admin.card>
    @endif

    @if($c !== null)
        @php
            $h = $financeiro['historico'];

            // As seções de contrato (serviço, adicional, domínio, fatura, pagamento) são sempre
            // desenhadas: quando o financeiro respondeu vazio, a tabela diz "nenhum", e é isso que a
            // equipe precisa poder provar para o cliente. As seções de histórico (crédito, pedido,
            // contato, chamado, orçamento) entram na página quando há o que ler nelas — ou quando a
            // leitura falhou ou nem foi tentada, caso em que a linha `financeiro-mudo` explica o
            // motivo em vez de deixar a lista sumir em silêncio.
            $secaoAparece = fn (string $secao): bool => ($h[$secao] ?? null) !== [];
        @endphp

        <x-admin.table class="mt-3" :headers="['Serviço', 'center:Situação', 'center:Vencimento', 'end:Valor', 'end:Abrir']"
            title="Serviços contratados" icon="fa-satellite-dish"
            :descricao="($h['servicos'] !== null ? count($h['servicos']).' serviço(s) na conta do financeiro.' : null)"
            empty="Nenhum serviço contratado nesta conta do financeiro.">
            @foreach($h['servicos'] ?? [] as $servico)
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $servico['nome'] }}</span>
                        @php
                            $detalhes = array_filter([$servico['grupo'], $servico['dominio'], $servico['servidor'] !== '' ? 'Servidor '.$servico['servidor'] : '']);
                            $extras = array_filter([
                                $servico['forma_de_pagamento'],
                                $servico['pedido_numero'] !== '' ? 'Pedido '.$servico['pedido_numero'] : '',
                                $servico['motivo_da_suspensao'] !== '' ? 'Suspenso: '.$servico['motivo_da_suspensao'] : '',
                            ]);
                        @endphp
                        @if($detalhes !== [])
                            <div class="text-secondary small">{{ implode(' · ', $detalhes) }}</div>
                        @endif
                        @if($extras !== [])
                            <div class="text-secondary small">{{ implode(' · ', $extras) }}</div>
                        @endif
                        @foreach($servico['campos_extras'] as $campoExtra)
                            <div class="text-secondary small">{{ $campoExtra['rotulo'] }}: {{ $campoExtra['valor'] }}</div>
                        @endforeach
                        @foreach($servico['opcoes'] as $opcao)
                            <div class="text-secondary small">{{ $opcao['rotulo'] }}: {{ $opcao['valor'] }}</div>
                        @endforeach
                        @if($servico['observacoes'] !== '')
                            <div class="text-secondary small">{{ $servico['observacoes'] }}</div>
                        @endif
                    </td>
                    <td class="text-center"><x-admin.status :rotulo="Financeiro::rotuloDoServico($servico['status'])" :tom="Financeiro::tomDoServico($servico['status'])" /></td>
                    <td class="text-center small text-nowrap">
                        {{ $servico['proximo_vencimento']?->format('d/m/Y') ?? '—' }}
                        @if($servico['contratado_em'])
                            <div class="text-secondary">desde {{ $servico['contratado_em']->format('d/m/Y') }}</div>
                        @endif
                    </td>
                    <td class="text-end">
                        <span class="fw-semibold text-nowrap d-block">{{ brl($servico['valor_cents']) }}</span>
                        <span class="text-secondary small">{{ $servico['ciclo'] !== '' ? Financeiro::rotuloDoCiclo($servico['ciclo']) : '—' }}</span>
                        @if($servico['primeiro_pagamento_cents'] > 0 && $servico['primeiro_pagamento_cents'] !== $servico['valor_cents'])
                            <div class="text-secondary small">1º: {{ brl($servico['primeiro_pagamento_cents']) }}</div>
                        @endif
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
            @if($h['servicos'] === null)
                <x-admin.financeiro-mudo :oQue="'de serviços'" />
            @endif
        </x-admin.table>

        <x-admin.table class="mt-3" :headers="['Adicional', 'center:Situação', 'center:Vencimento', 'end:Valor', 'end:Abrir']"
            title="Adicionais contratados" icon="fa-plus"
            :descricao="($h['addons'] !== null ? count($h['addons']).' adicional(is) na conta do financeiro.' : null)"
            empty="Nenhum adicional contratado nesta conta do financeiro.">
            @foreach($h['addons'] ?? [] as $addon)
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $addon['nome'] }}</span>
                        @php
                            $linhaAdicional = array_filter([
                                $addon['servico'] !== '' ? 'Do serviço '.$addon['servico'] : '',
                                $addon['forma_de_pagamento'],
                                $addon['contratado_em'] ? 'desde '.$addon['contratado_em']->format('d/m/Y') : '',
                            ]);
                        @endphp
                        @if($linhaAdicional !== [])
                            <div class="text-secondary small">{{ implode(' · ', $linhaAdicional) }}</div>
                        @endif
                    </td>
                    <td class="text-center"><x-admin.status :rotulo="Financeiro::rotuloDoServico($addon['status'])" :tom="Financeiro::tomDoServico($addon['status'])" /></td>
                    <td class="text-center small text-nowrap">{{ $addon['proximo_vencimento']?->format('d/m/Y') ?? '—' }}</td>
                    <td class="text-end">
                        <span class="fw-semibold text-nowrap d-block">{{ brl($addon['valor_cents']) }}</span>
                        <span class="text-secondary small">{{ $addon['ciclo'] !== '' ? Financeiro::rotuloDoCiclo($addon['ciclo']) : '—' }}</span>
                    </td>
                    <td class="text-end">
                        @if($addon['url'] !== '')
                            <a class="btn btn-sm btn-outline-secondary" href="{{ $addon['url'] }}" target="_blank" rel="noopener noreferrer"
                               aria-label="Abrir {{ $addon['nome'] }} no financeiro"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                        @else
                            <span class="small text-secondary">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if($h['addons'] === null)
                <x-admin.financeiro-mudo :oQue="'de adicionais'" />
            @endif
        </x-admin.table>

        <x-admin.table class="mt-3" :headers="['Domínio', 'center:Situação', 'center:Registro', 'center:Vence em', 'end:Valor']"
            title="Domínios" icon="fa-globe"
            :descricao="($h['dominios'] !== null ? count($h['dominios']).' domínio(s) na conta do financeiro.' : null)"
            empty="Nenhum domínio nesta conta do financeiro.">
            @foreach($h['dominios'] ?? [] as $dominio)
                <tr>
                    <td>
                        <span class="fw-semibold text-nowrap">{{ $dominio['dominio'] ?: 'Domínio sem nome' }}</span>
                        @php
                            $origem = array_filter([
                                $dominio['tipo'] !== '' ? Financeiro::rotuloDoTipoDeDominio($dominio['tipo']) : '',
                                $dominio['registrador'],
                                $dominio['renovacao_automatica'] === null ? '' : ($dominio['renovacao_automatica'] ? 'renova sozinho' : 'não renova sozinho'),
                            ]);
                        @endphp
                        @if($origem !== [])
                            <div class="text-secondary small">{{ implode(' · ', $origem) }}</div>
                        @endif
                        @if($dominio['observacoes'] !== '')
                            <div class="text-secondary small">{{ $dominio['observacoes'] }}</div>
                        @endif
                    </td>
                    <td class="text-center"><x-admin.status :rotulo="Financeiro::rotuloDoDomino($dominio['status'])" :tom="Financeiro::tomDoDomino($dominio['status'])" /></td>
                    <td class="text-center small text-nowrap">
                        {{ $dominio['registrado_em']?->format('d/m/Y') ?? '—' }}
                        @if($dominio['proximo_vencimento'])
                            <div class="text-secondary">cobra {{ $dominio['proximo_vencimento']->format('d/m/Y') }}</div>
                        @endif
                    </td>
                    <td class="text-center small text-nowrap">{{ $dominio['vence_em']?->format('d/m/Y') ?? '—' }}</td>
                    <td class="text-end">
                        <span class="fw-semibold text-nowrap d-block">{{ brl($dominio['valor_cents']) }}</span>
                        <span class="text-secondary small">{{ $dominio['ciclo'] !== '' ? Financeiro::rotuloDoCiclo($dominio['ciclo']) : '—' }}</span>
                    </td>
                </tr>
            @endforeach
            @if($h['dominios'] === null)
                <x-admin.financeiro-mudo :colunas="5" :oQue="'de domínios'" />
            @endif
        </x-admin.table>

        <x-admin.table class="mt-3" :headers="['Fatura', 'center:Vencimento', 'center:Situação', 'end:Total', 'end:Abrir']"
            title="Faturas" icon="fa-file-invoice-dollar"
            :descricao="($h['faturas'] !== null
                ? count($h['faturas']).' fatura(s) no financeiro'.($emAberto > 0 ? ' — '.brl($emAberto).' em aberto.' : '.')
                : null)"
            empty="Nenhuma fatura emitida nesta conta do financeiro.">
            @foreach($h['faturas'] ?? [] as $fatura)
                <tr>
                    <td>
                        <span class="fw-semibold text-nowrap">#{{ $fatura['numero'] }}</span>
                        <div class="text-secondary small">
                            @if($fatura['paga_em'])
                                Paga em {{ $fatura['paga_em']->format('d/m/Y') }}
                            @elseif($fatura['emitida'])
                                Emitida em {{ $fatura['emitida']->format('d/m/Y') }}
                            @endif
                            @if($fatura['metodo_rotulo'] !== '')
                                · {{ $fatura['metodo_rotulo'] }}
                            @endif
                        </div>
                        <div class="text-secondary small">
                            @if($fatura['cancelada_em'])
                                Cancelada em {{ $fatura['cancelada_em']->format('d/m/Y') }}
                            @elseif($fatura['reembolsada_em'])
                                Reembolsada em {{ $fatura['reembolsada_em']->format('d/m/Y') }}
                            @elseif($fatura['ultima_tentativa'])
                                Última tentativa {{ $fatura['ultima_tentativa']->format('d/m/Y H:i') }}
                            @endif
                        </div>
                        @if($fatura['imposto_cents'] > 0 || $fatura['credito_aplicado_cents'] > 0)
                            <div class="text-secondary small">
                                @if($fatura['imposto_cents'] > 0) Imposto {{ brl($fatura['imposto_cents']) }} · @endif
                                @if($fatura['credito_aplicado_cents'] > 0) Crédito {{ brl($fatura['credito_aplicado_cents']) }} @endif
                            </div>
                        @endif
                        @if($fatura['observacoes'] !== '')
                            <div class="text-secondary small">{{ $fatura['observacoes'] }}</div>
                        @endif
                    </td>
                    <td class="text-center small text-nowrap">{{ $fatura['vencimento']?->format('d/m/Y') ?? '—' }}</td>
                    <td class="text-center"><x-admin.status :rotulo="Financeiro::rotuloDaFatura($fatura['status'])" :tom="Financeiro::tomDaFatura($fatura['status'])" /></td>
                    <td class="text-end">
                        <span class="small text-nowrap d-block">{{ brl($fatura['total_cents']) }}@if($fatura['moeda'] !== '') <span class="text-secondary">{{ $fatura['moeda'] }}</span>@endif</span>
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
            @if($h['faturas'] === null)
                <x-admin.financeiro-mudo :oQue="'de faturas'" />
            @endif
        </x-admin.table>

        <x-admin.table class="mt-3" :headers="['Pagamento', 'center:Data', 'center:Forma', 'end:Entrada', 'end:Saída', 'end:Abrir']"
            title="Pagamentos e movimentos" icon="fa-money-bill-transfer"
            :descricao="($h['transacoes'] !== null ? count($h['transacoes']).' lançamento(s) no extrato do financeiro.' : null)"
            empty="Nenhum pagamento registrado nesta conta do financeiro.">
            @foreach($h['transacoes'] ?? [] as $transacao)
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $transacao['descricao'] ?: 'Sem descrição' }}</span>
                        @if($transacao['referencia'] !== '')
                            <div class="text-secondary small text-truncate" title="{{ $transacao['referencia'] }}">Ref. {{ $transacao['referencia'] }}</div>
                        @endif
                        @if($transacao['taxa_cents'] !== 0)
                            <div class="text-secondary small">Taxa {{ brl($transacao['taxa_cents']) }}</div>
                        @endif
                    </td>
                    <td class="text-center small text-nowrap">
                        {{ $transacao['data']?->format('d/m/Y') ?? '—' }}
                        @if($transacao['moeda'] !== '')
                            <div class="text-secondary">{{ $transacao['moeda'] }}</div>
                        @endif
                    </td>
                    <td class="text-center small">{{ $transacao['forma'] ?: '—' }}</td>
                    <td class="text-end small text-nowrap {{ $transacao['entrada_cents'] > 0 ? 'fw-semibold text-success' : 'text-secondary' }}">
                        {{ $transacao['entrada_cents'] > 0 ? brl($transacao['entrada_cents']) : '—' }}
                    </td>
                    <td class="text-end small text-nowrap {{ $transacao['saida_cents'] > 0 ? 'fw-semibold text-danger' : 'text-secondary' }}">
                        {{ $transacao['saida_cents'] > 0 ? brl($transacao['saida_cents']) : '—' }}
                    </td>
                    <td class="text-end">
                        @if($transacao['url'] !== '')
                            <a class="btn btn-sm btn-outline-secondary" href="{{ $transacao['url'] }}" target="_blank" rel="noopener noreferrer"
                               aria-label="Abrir a fatura deste lançamento no financeiro"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                        @else
                            <span class="small text-secondary">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if($h['transacoes'] === null)
                <x-admin.financeiro-mudo :colunas="6" :oQue="'de pagamentos'" />
            @endif
        </x-admin.table>

        @if($secaoAparece('creditos'))
            <x-admin.table class="mt-3" :headers="['Crédito', 'center:Data', 'end:Valor', 'end:Abrir']"
                title="Movimentos de crédito" icon="fa-scale-balanced"
                :descricao="($h['creditos'] !== null ? count($h['creditos']).' lançamento(s) no extrato de crédito.' : null)"
                empty="Nenhum movimento de crédito nesta conta do financeiro.">
                @foreach($h['creditos'] ?? [] as $credito)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $credito['descricao'] ?: 'Sem descrição' }}</span>
                        </td>
                        <td class="text-center small text-nowrap">{{ $credito['data']?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-end small text-nowrap">{{ brl($credito['valor_cents']) }}</td>
                        <td class="text-end">
                            @if($credito['url'] !== '')
                                <a class="btn btn-sm btn-outline-secondary" href="{{ $credito['url'] }}" target="_blank" rel="noopener noreferrer"
                                   aria-label="Abrir a fatura deste crédito no financeiro"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                            @else
                                <span class="small text-secondary">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @if($h['creditos'] === null)
                    <x-admin.financeiro-mudo :colunas="4" :oQue="'de créditos'" />
                @endif
            </x-admin.table>
        @endif

        @if($secaoAparece('pedidos'))
            <x-admin.table class="mt-3" :headers="['Pedido', 'center:Data', 'center:Situação', 'center:Pagamento', 'end:Valor', 'end:Abrir']"
                title="Pedidos no financeiro" icon="fa-cart-shopping"
                :descricao="($h['pedidos'] !== null ? count($h['pedidos']).' pedido(s) da conta — o que nasceu do serviço comprado.' : null)"
                empty="Nenhum pedido registrado nesta conta do financeiro.">
                @foreach($h['pedidos'] ?? [] as $pedido)
                    <tr>
                        <td>
                            <span class="fw-semibold text-nowrap">#{{ $pedido['numero'] }}</span>
                            @if($pedido['forma'] !== '')
                                <div class="text-secondary small">{{ $pedido['forma'] }}</div>
                            @endif
                            @if($pedido['itens'] !== [])
                                <ul class="list-unstyled small text-secondary mb-0 mt-1">
                                    @foreach($pedido['itens'] as $item)
                                        <li>
                                            {{ $item['tipo'] !== '' ? Financeiro::rotuloDoTipoDeItem($item['tipo']) : 'Item' }}:
                                            {{ $item['nome'] ?: ($item['dominio'] ?: 'sem nome') }}
                                            @if($item['quantidade'] !== null && $item['quantidade'] !== 1)
                                                <span class="text-secondary">({{ $item['quantidade'] }}x)</span>
                                            @endif
                                            @if($item['valor_cents'] > 0)
                                                — {{ brl($item['valor_cents']) }}
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if($pedido['observacoes'] !== '')
                                <div class="text-secondary small">{{ $pedido['observacoes'] }}</div>
                            @endif
                        </td>
                        <td class="text-center small text-nowrap">{{ $pedido['data']?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-center"><x-admin.status :rotulo="Financeiro::rotuloDoPedido($pedido['status'])" :tom="Financeiro::tomDoPedido($pedido['status'])" /></td>
                        <td class="text-center small">{{ $pedido['pagamento'] ?: '—' }}</td>
                        <td class="text-end small text-nowrap">{{ brl($pedido['valor_cents']) }}</td>
                        <td class="text-end">
                            @if($pedido['url'] !== '')
                                <a class="btn btn-sm btn-outline-secondary" href="{{ $pedido['url'] }}" target="_blank" rel="noopener noreferrer"
                                   aria-label="Abrir a fatura do pedido {{ $pedido['numero'] }} no financeiro"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                            @else
                                <span class="small text-secondary">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @if($h['pedidos'] === null)
                    <x-admin.financeiro-mudo :colunas="6"
                        :texto="$c['userid'] > 0
                            ? 'O financeiro não respondeu à consulta de pedidos. A lista não está vazia — ela não pôde ser lida agora.'
                            : 'Os pedidos não foram consultados: o financeiro não devolveu o número de usuário desta conta, e a consulta sem ele traria pedido de outras pessoas.'" />
                @endif
            </x-admin.table>
        @endif

        @if($secaoAparece('chamados'))
            <x-admin.table class="mt-3" :headers="['Chamado', 'center:Situação', 'center:Prioridade', 'center:Aberto em', 'center:Última resposta']"
                title="Chamados no financeiro" icon="fa-headset"
                :descricao="($h['chamados'] !== null ? count($h['chamados']).' chamado(s) abertos no suporte do financeiro — os do portal estão na ficha acima.' : null)"
                empty="Nenhum chamado aberto no suporte do financeiro.">
                @foreach($h['chamados'] ?? [] as $chamado)
                    <tr>
                        <td>
                            <span class="fw-semibold">#{{ $chamado['numero'] }}</span>
                            <span class="text-secondary">{{ $chamado['assunto'] ?: 'sem assunto' }}</span>
                            @php
                                $trilha = array_filter([
                                    $chamado['departamento'] !== '' ? Financeiro::rotuloDoDepartamento($chamado['departamento']) : '',
                                    $chamado['atendente'],
                                    $chamado['servico'],
                                    $chamado['quem_abriu'],
                                ]);
                            @endphp
                            @if($trilha !== [])
                                <div class="text-secondary small">{{ implode(' · ', $trilha) }}</div>
                            @endif
                        </td>
                        <td class="text-center"><x-admin.status :rotulo="Financeiro::rotuloDoChamado($chamado['status'])" :tom="Financeiro::tomDoChamado($chamado['status'])" /></td>
                        <td class="text-center">
                            @if($chamado['prioridade'] !== '')
                                <x-admin.status :rotulo="Financeiro::rotuloDaPrioridade($chamado['prioridade'])" :tom="Financeiro::tomDaPrioridade($chamado['prioridade'])" />
                            @else
                                <span class="small text-secondary">—</span>
                            @endif
                        </td>
                        <td class="text-center small text-nowrap">{{ $chamado['aberto_em']?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-center small text-nowrap">{{ $chamado['ultima_resposta']?->format('d/m/Y H:i') ?? '—' }}</td>
                    </tr>
                @endforeach
                @if($h['chamados'] === null)
                    <x-admin.financeiro-mudo :oQue="'de chamados'" />
                @endif
            </x-admin.table>
        @endif

        @if($secaoAparece('orcamentos'))
            <x-admin.table class="mt-3" :headers="['Orçamento', 'center:Situação', 'center:Enviado', 'center:Válido até', 'end:Total']"
                title="Orçamentos" icon="fa-file-signature"
                :descricao="($h['orcamentos'] !== null ? count($h['orcamentos']).' orçamento(s) da conta no financeiro.' : null)"
                empty="Nenhum orçamento para esta conta no financeiro.">
                @foreach($h['orcamentos'] ?? [] as $orcamento)
                    <tr>
                        <td>
                            <span class="fw-semibold">#{{ $orcamento['id'] }}</span>
                            <span class="text-secondary">{{ $orcamento['assunto'] ?: 'sem assunto' }}</span>
                            @if($orcamento['criado_em'])
                                <div class="text-secondary small">Criado em {{ $orcamento['criado_em']->format('d/m/Y') }}</div>
                            @endif
                            @if($orcamento['aceito_em'])
                                <div class="text-secondary small">Aceito em {{ $orcamento['aceito_em']->format('d/m/Y') }}</div>
                            @endif
                            @if($orcamento['itens'] !== [])
                                <ul class="list-unstyled small text-secondary mb-0 mt-1">
                                    @foreach($orcamento['itens'] as $item)
                                        <li>{{ $item['nome'] ?: 'Item' }}@if($item['valor_cents'] > 0) — {{ brl($item['valor_cents']) }}@endif</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if($orcamento['observacoes_para_o_cliente'] !== '')
                                <div class="text-secondary small">{{ $orcamento['observacoes_para_o_cliente'] }}</div>
                            @endif
                        </td>
                        <td class="text-center"><x-admin.status :rotulo="Financeiro::rotuloDoOrcamento($orcamento['status'])" :tom="Financeiro::tomDoOrcamento($orcamento['status'])" /></td>
                        <td class="text-center small text-nowrap">{{ $orcamento['enviado_em']?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-center small text-nowrap">{{ $orcamento['valido_ate']?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-end small text-nowrap">{{ brl($orcamento['total_cents']) }}</td>
                    </tr>
                @endforeach
                @if($h['orcamentos'] === null)
                    <x-admin.financeiro-mudo :oQue="'de orçamentos'" />
                @endif
            </x-admin.table>
        @endif

        @if($secaoAparece('contatos'))
            <x-admin.table class="mt-3" :headers="['Contato', 'center:Acesso', 'center:Desde']"
                title="Contatos da conta" icon="fa-address-book"
                :descricao="($h['contatos'] !== null ? count($h['contatos']).' contato(s) cadastrado(s) no financeiro.' : null)"
                empty="Nenhum contato além do titular nesta conta do financeiro.">
                @foreach($h['contatos'] ?? [] as $contato)
                    @php
                        $linha = array_filter([
                            $contato['email'],
                            $contato['telefone'],
                            $contato['documento'] !== '' ? 'Doc. '.$contato['documento'] : '',
                            trim($contato['endereco'].' '.($contato['cidade'] !== '' ? '- '.$contato['cidade'].'/'.$contato['uf'] : '')),
                        ]);
                    @endphp
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $contato['nome'] ?: 'Sem nome' }}</span>
                            @if($contato['empresa'] !== '')
                                <span class="text-secondary">{{ $contato['empresa'] }}</span>
                            @endif
                            @if($linha !== [])
                                <div class="text-secondary small">{{ implode(' · ', $linha) }}</div>
                            @endif
                            @if($contato['permissoes'] !== [])
                                <div class="text-secondary small">Recebe: {{ implode(', ', $contato['permissoes']) }}</div>
                            @endif
                            @foreach($contato['avisos_por_email'] as $aviso)
                                <div class="text-secondary small">{{ $aviso['rotulo'] }}: {{ $aviso['ativo'] ? 'sim' : 'não' }}</div>
                            @endforeach
                        </td>
                        <td class="text-center">
                            @if($contato['sub_conta'] === true)
                                <x-admin.status rotulo="Subconta" tom="blue" />
                            @elseif($contato['sub_conta'] === false)
                                <x-admin.status rotulo="Só contato" tom="neutral" />
                            @else
                                <span class="small text-secondary">não informado</span>
                            @endif
                        </td>
                        <td class="text-center small text-nowrap">{{ $contato['desde']?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @endforeach
                @if($h['contatos'] === null)
                    <x-admin.financeiro-mudo :colunas="3"
                        :texto="$c['userid'] > 0
                            ? 'O financeiro não respondeu à consulta de contatos. A lista não está vazia — ela não pôde ser lida agora.'
                            : 'Os contatos não foram consultados: o financeiro não devolveu o número de usuário desta conta, e a consulta sem ele traria contato de outras pessoas.'" />
                @endif
            </x-admin.table>
        @endif
    @endif
@endif
