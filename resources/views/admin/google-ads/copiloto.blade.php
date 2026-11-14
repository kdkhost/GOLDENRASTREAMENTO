@extends('admin.layouts.app')
@section('title', 'Copiloto do Ads')
@section('page_title', 'Copiloto do Ads')
@section('page_description', 'A assistente que escreve a campanha inteira a partir do que você pede: orçamento, regiões, grupos, palavras-chave e anúncio — e só publica no Google depois do seu aval.')

@section('page_actions')
<a href="{{ route('admin.google-ads.relatorio.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-lines me-1" aria-hidden="true"></i> Relatório</a>
<a href="{{ route('admin.google-ads.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chart-line me-1" aria-hidden="true"></i> Métricas</a>
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('css/golden-ads.css') }}?v={{ filemtime(public_path('css/golden-ads.css')) }}">
@endsection

@section('content')
@php
    $ehAdmin = auth()->user()->isAdmin();
    $rotulosObjetivo = $objetivos;
@endphp

<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="Pedir uma campanha" icon="fa-wand-magic-sparkles"
            descricao="Descreva o que você quer em português. O copiloto devolve a estrutura completa, já dentro das travas do Google.">
            @if ($ia_pronta)
                <form method="POST" action="{{ route('admin.google-ads.copiloto.gerar') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold d-flex align-items-center gap-2 mb-2">
                                <i class="fas fa-bullseye text-golden"></i> Qual é o foco principal do seu anúncio?
                            </label>
                            <div class="row g-2" id="seletor-objetivo">
                                <div class="col-12 col-md-6">
                                    <div class="card p-3 h-100 cursor-pointer border hover-border-golden objetivo-card active" data-objetivo="vendas" style="cursor: pointer; background: rgba(212,160,23,0.06); border-color: rgba(212,160,23,0.4) !important;">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-warning text-dark"><i class="fas fa-shopping-cart me-1"></i> Vendas Diretas</span>
                                            <span class="badge bg-success-subtle text-success small py-0">Alta Conversão</span>
                                        </div>
                                        <div class="small fw-semibold text-light mb-1">Kits de Rastreadores e Planos</div>
                                        <div class="text-secondary small">Focado em fechamento imediato com planos a partir de R$ 49,90/mês, rastreador J16 4G com chip M2M, preços transparentes e chamada "Compre Já".</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="card p-3 h-100 cursor-pointer border hover-border-golden objetivo-card" data-objetivo="pesquisa" style="cursor: pointer; background: rgba(255,255,255,0.02); border-color: rgba(255,255,255,0.1);">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-info-subtle text-info"><i class="fas fa-search me-1"></i> Pesquisa & Tráfego</span>
                                            <span class="badge bg-primary-subtle text-primary small py-0">Volume de Buscas</span>
                                        </div>
                                        <div class="small fw-semibold text-light mb-1">Captura de Dúvidas e Busca Orgânica</div>
                                        <div class="text-secondary small">Focado em capturar quem busca no Google por "como rastrear carro", "melhor rastreador com bloqueio", autoridade da Golden e cliques qualificados.</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="card p-2 h-100 cursor-pointer border hover-border-golden objetivo-card" data-objetivo="leads" style="cursor: pointer; background: rgba(255,255,255,0.02); border-color: rgba(255,255,255,0.1);">
                                        <div class="fw-semibold small text-light mb-1"><i class="fas fa-truck text-secondary me-1"></i> Leads & Frotas</div>
                                        <div class="text-secondary small" style="font-size: 0.78rem;">Orçamentos para empresas, telemetria e gestão de frota.</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="card p-2 h-100 cursor-pointer border hover-border-golden objetivo-card" data-objetivo="whatsapp" style="cursor: pointer; background: rgba(255,255,255,0.02); border-color: rgba(255,255,255,0.1);">
                                        <div class="fw-semibold small text-light mb-1"><i class="fab fa-whatsapp text-success me-1"></i> WhatsApp Direto</div>
                                        <div class="text-secondary small" style="font-size: 0.78rem;">Inicie conversas imediatas com a equipe comercial.</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="card p-2 h-100 cursor-pointer border hover-border-golden objetivo-card" data-objetivo="ligacoes" style="cursor: pointer; background: rgba(255,255,255,0.02); border-color: rgba(255,255,255,0.1);">
                                        <div class="fw-semibold small text-light mb-1"><i class="fas fa-phone text-warning me-1"></i> Ligações Imediatas</div>
                                        <div class="text-secondary small" style="font-size: 0.78rem;">Ligações diretas para a central de vendas da Golden.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($contas->isNotEmpty())
                            <x-admin.field name="conta" type="select" label="Conta do Google Ads" :options="$contas->mapWithKeys(fn ($c) => [$c->id => $c->nomeTela()])->all()"
                                col="col-12 col-md-6" empty="Escolher depois, na hora de aplicar" :opcional="true"
                                hint="Onde a campanha vai nascer quando você aplicar o plano. O plano pode ser escrito antes de conectar a conta." />
                        @endif

                        <x-admin.field name="nome" label="Nome da campanha" :required="true" maxlength="120" col="col-12 col-md-6"
                            placeholder="Ex.: Vendas — Kit Rastreador J16 4G e Planos" />

                        <x-admin.field name="objetivo" type="select" label="Objetivo confirmado" :options="$rotulosObjetivo" :required="true"
                            col="col-12 col-md-6" value="vendas" hint="Selecione ou clique nos cartões acima." />

                        <x-admin.field name="orcamento" type="number" label="Orçamento diário (R$)" :required="true" col="col-12 col-md-6"
                            step="0.01" min="1" max="5000" placeholder="40,00"
                            hint="Quanto pode ser gasto por dia, no máximo." />

                        <x-admin.field name="url" type="url" label="Página de destino" :required="true" col="col-12" maxlength="255"
                            placeholder="https://goldenrastreamento.com.br/planos"
                            hint="É a página que o anúncio abre. Só o que existe no site." />

                        <x-admin.field name="regioes" type="textarea" label="Regiões" :required="true" :rows="2" col="col-12" maxlength="255"
                            placeholder="São Paulo&#10;Campinas&#10;Santos"
                            hint="Uma região por linha. Vale cidade, estado ou país — o Google resolve o resto." />

                        <x-admin.field name="publico" type="textarea" label="Público" :rows="3" col="col-12" maxlength="500" :opcional="true"
                            placeholder="Transportadoras e locadoras com frota de 10 a 200 veículos." />

                        <x-admin.field name="oferta" type="textarea" label="Oferta e argumentos" :rows="3" col="col-12" maxlength="500" :opcional="true"
                            placeholder="Instalação no mesmo dia, suporte 24 h e plataforma própria para acompanhar."
                            hint="O que pode ser prometido — nada além disso é dito no anúncio." />

                        <x-admin.field name="observacoes" type="textarea" label="Observações" :rows="2" col="col-12" maxlength="1000" :opcional="true"
                            placeholder="O que evitar, restrições de horário, termos que não podem aparecer." />

                        <div class="col-12 d-flex flex-wrap align-items-center gap-2">
                            <button class="btn btn-golden">
                                <i class="fas fa-wand-magic-sparkles me-1" aria-hidden="true"></i> Gerar o plano
                            </button>
                            <span class="form-hint">A geração leva alguns segundos: a página não sai daqui até a ficha do plano abrir.</span>
                        </div>
                    </div>
                </form>
            @else
                <ul class="ads-passos list-unstyled mb-3">
                    <li class="is-pendente">
                        <span class="ads-passos__marca"><i class="fas fa-key" aria-hidden="true"></i></span>
                        <div>
                            <div class="fw-semibold">Falta a chave de IA da capacidade “conversa”</div>
                            <div class="small text-secondary">Sem ela o copiloto não escreve plano nem analisa relatório. O que já foi gerado continua na lista abaixo.</div>
                        </div>
                    </li>
                </ul>
                @if ($ia_faltando !== [])
                    <ul class="small text-secondary ps-3">
                        @foreach ($ia_faltando as $aviso)
                            <li>{{ $aviso }}</li>
                        @endforeach
                    </ul>
                @endif
                @can('admin')
                    <a href="{{ route('admin.settings.index') }}#aba-chaves" class="btn btn-golden btn-sm">
                        <i class="fas fa-key me-1" aria-hidden="true"></i> Abrir as chaves de integração
                    </a>
                @else
                    <p class="form-hint mb-0">As chaves ficam com o administrador.</p>
                @endcan
            @endif
        </x-admin.card>

        <x-admin.table title="Planos gerados" icon="fa-list-check" :paginator="$planos"
            :headers="['Campanha', 'Situação', 'center:Força', 'end:Orçamento/dia', 'Criado em', 'end:Ações']"
            empty="Nenhum plano ainda — o primeiro sai do formulário acima.">
            @foreach ($planos as $p)
                @php
                    $forca = (array) ($p->validacao()['forca'] ?? []);
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $p->nome }}</div>
                        <div class="small text-secondary">
                            {{ $rotulosObjetivo[$p->objetivo] ?? $p->objetivo }}
                            @if ($p->user)
                                · {{ $p->user->name }}
                            @endif
                        </div>
                    </td>
                    <td><x-admin.status :rotulo="$p->rotuloStatus()" :tom="tom_por_status('plano_ads', $p->status)" /></td>
                    <td class="text-center">
                        @if (($forca['rotulo'] ?? '') !== '')
                            <x-admin.status :rotulo="$forca['rotulo'].' · '.($forca['nota'] ?? 0)" :tom="tom_por_status('forca_anuncio', $forca['chave'] ?? '')" />
                        @else
                            <span class="text-secondary">—</span>
                        @endif
                    </td>
                    <td class="text-end">{{ brl($p->orcamento_diario_centavos) }}</td>
                    <td class="small text-secondary">{{ $p->created_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('admin.google-ads.copiloto.show', $p) }}" class="btn btn-sm btn-outline-secondary" title="Abrir o plano">
                                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </a>
                            @if ($p->status !== \App\Models\GoogleAdsPlano::APLICADO)
                                <a href="{{ route('admin.google-ads.copiloto.editar', $p) }}" class="btn btn-sm btn-outline-secondary" title="Editar o plano">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-admin.table>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="A ficha do copiloto" icon="fa-robot"
            descricao="Quem escreve o anúncio, com que tom e sob quais instruções.">
            <form method="POST" action="{{ route('admin.google-ads.copiloto.ficha') }}" class="row g-3" data-atualiza=".app-content">
                @csrf
                <x-admin.field name="nome" label="Nome" :value="$ficha->nome" :required="true" maxlength="60" col="col-12"
                    :travado="! $ehAdmin" />
                <x-admin.field name="cargo" label="Cargo" :value="$ficha->cargo" :required="true" maxlength="120" col="col-12"
                    :travado="! $ehAdmin" />
                <x-admin.field name="tom" label="Tom" :value="$ficha->tom" maxlength="160" col="col-12" :opcional="true"
                    placeholder="direto, técnico e sem promessa exagerada" :travado="! $ehAdmin" />
                <x-admin.field name="instrucoes" type="textarea" label="Instruções" :value="$ficha->instrucoes" :rows="6" maxlength="4000"
                    col="col-12" :opcional="true"
                    hint="Vai como ordem para a IA em toda geração: o que ela pode dizer, o que nunca pode prometer."
                    :travado="! $ehAdmin" />

                @if ($ehAdmin)
                    <div class="col-12">
                        <button class="btn btn-golden"><i class="fas fa-save me-1" aria-hidden="true"></i> Salvar ficha</button>
                    </div>
                @else
                    <div class="col-12"><p class="form-hint mb-0">Só o administrador edita a ficha.</p></div>
                @endif
            </form>
        </x-admin.card>

        <x-admin.card title="Ele consegue responder?" icon="fa-plug" class="mt-3">
            @if ($ia_pronta)
                <p class="mb-2"><x-admin.status rotulo="pronto para escrever" :tom="tom_por_flag(true)" /></p>
                <p class="small text-secondary mb-0">
                    Modelo: <code>{{ $ia_modelo['provedor'].'/'.$ia_modelo['modelo'] }}</code>
                </p>
            @else
                <p class="mb-2"><x-admin.status rotulo="sem provedor ligado" :tom="tom_por_flag(false)" /></p>
                <p class="small text-secondary mb-0">A chave da capacidade “conversa” alimenta o copiloto e a Ana.</p>
            @endif

            @if ($ia_faltando !== [])
                <ul class="small text-secondary mt-2 mb-0 ps-3">
                    @foreach ($ia_faltando as $aviso)
                        <li>{{ $aviso }}</li>
                    @endforeach
                </ul>
            @endif

            <p class="form-hint mt-2 mb-0">
                <a href="{{ route('admin.settings.index') }}#aba-chaves"><i class="fas fa-key me-1" aria-hidden="true"></i> Chaves e modelos por capacidade</a>
            </p>
        </x-admin.card>

        <x-admin.card title="Como o plano vira campanha" icon="fa-shield-halved" class="mt-3"
            descricao="A ordem é a trava: nada é publicado no Google sem passar por aqui.">
            <ul class="ads-passos list-unstyled mb-0">
                <li class="is-feito">
                    <span class="ads-passos__marca"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i></span>
                    <div>
                        <div class="fw-semibold">A IA escreve o plano</div>
                        <div class="small text-secondary">O texto passa pelas travas do Google: caracteres, quantidade de títulos, orçamento e regiões.</div>
                    </div>
                </li>
                <li class="{{ $ehAdmin ? 'is-pendente' : '' }}">
                    <span class="ads-passos__marca"><i class="fas fa-user-check" aria-hidden="true"></i></span>
                    <div>
                        <div class="fw-semibold">Uma pessoa aprova</div>
                        <div class="small text-secondary">
                            @if ($ehAdmin)
                                Você abre a ficha, confere a estrutura e aprova.
                            @else
                                Só o administrador aprova.
                            @endif
                        </div>
                    </div>
                </li>
                <li>
                    <span class="ads-passos__marca"><i class="fas fa-pause" aria-hidden="true"></i></span>
                    <div>
                        <div class="fw-semibold">A campanha nasce pausada</div>
                        <div class="small text-secondary">Aplicar cria a campanha no Google já pausada; começar a veicular é um ato separado, na tela de campanhas.</div>
                    </div>
                </li>
            </ul>
        </x-admin.card>

        <x-admin.card title="Credenciais do Google Ads" icon="fa-brands fa-google" class="mt-3">
            @if ($credenciais_faltando->isEmpty())
                <p class="mb-0"><x-admin.status rotulo="credenciais preenchidas" :tom="tom_por_flag(true)" /></p>
            @else
                <p class="small text-secondary mb-2">Sem estas chaves nenhum plano pode ser publicado — gerar e aprovar continua funcionando.</p>
                <ul class="ads-faltas list-unstyled mb-2">
                    @foreach ($credenciais_faltando as $nome)
                        <li><i class="fas fa-circle-dot" aria-hidden="true"></i> <code>{{ $nome }}</code></li>
                    @endforeach
                </ul>
                @can('admin')
                    <a href="{{ route('admin.settings.index') }}#aba-chaves" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="fas fa-key me-1" aria-hidden="true"></i> Abrir as chaves
                    </a>
                @else
                    <p class="form-hint mb-0">As chaves ficam com o administrador.</p>
                @endcan
            @endif
        </x-admin.card>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const cards = document.querySelectorAll('.objetivo-card');
    const selectObjetivo = document.querySelector('select[name="objetivo"]');
    const inputNome = document.querySelector('input[name="nome"]');
    const inputUrl = document.querySelector('input[name="url"]');
    const inputOferta = document.querySelector('textarea[name="oferta"]');

    const sugestoes = {
        vendas: {
            nome: 'Vendas — Kit Rastreador J16 4G e Planos',
            url: 'https://goldenrastreamento.com.br/planos',
            oferta: 'Kit Rastreador J16 4G homologado Anatel com chip M2M multi-operadora, planos a partir de R$ 49,90/mês, bloqueio remoto antifurto imediato e instalação expressa sem carência.'
        },
        pesquisa: {
            nome: 'Pesquisa — Rastreamento Veicular e Segurança 24h',
            url: 'https://goldenrastreamento.com.br/como-funciona',
            oferta: 'Plataforma web e app Android/iOS em tempo real, tecnologia 4G/2G, alertas de ignição e cerca virtual. Atendimento 24h e suporte dedicado da Golden Rastreamento.'
        },
        leads: {
            nome: 'Leads — Gestão de Frotas e Telemetria Corporativa',
            url: 'https://goldenrastreamento.com.br/solucoes',
            oferta: 'Redução de custos operacionais, controle de velocidade e telemetria avançada para caminhões, frotas e transportadoras. Solicite proposta personalizada.'
        },
        whatsapp: {
            nome: 'Vendas WhatsApp — Cotação Imediata de Rastreador',
            url: 'https://goldenrastreamento.com.br/contato',
            oferta: 'Fale direto com especialista no WhatsApp, tire dúvidas sobre compatibilidade veicular e contrate sem burocracia.'
        },
        ligacoes: {
            nome: 'Ligações — Central Comercial Golden Rastreamento',
            url: 'https://goldenrastreamento.com.br/contato',
            oferta: 'Ligue agora para nossa central comercial e garanta condições especiais de instalação para seu carro ou moto.'
        }
    };

    function selecionarObjetivo(obj) {
        cards.forEach(c => {
            const ehEsse = c.getAttribute('data-objetivo') === obj;
            if (ehEsse) {
                c.style.background = 'rgba(212,160,23,0.08)';
                c.style.borderColor = 'rgba(212,160,23,0.5)';
            } else {
                c.style.background = 'rgba(255,255,255,0.02)';
                c.style.borderColor = 'rgba(255,255,255,0.1)';
            }
        });

        if (selectObjetivo) {
            selectObjetivo.value = obj;
        }

        if (sugestoes[obj]) {
            if (inputNome && (!inputNome.value || inputNome.value.startsWith('Vendas —') || inputNome.value.startsWith('Pesquisa —') || inputNome.value.startsWith('Leads —') || inputNome.value.startsWith('Ligações —'))) {
                inputNome.value = sugestoes[obj].nome;
            }
            if (inputUrl && (!inputUrl.value || inputUrl.value.includes('goldenrastreamento.com.br'))) {
                inputUrl.value = sugestoes[obj].url;
            }
            if (inputOferta && !inputOferta.value) {
                inputOferta.placeholder = sugestoes[obj].oferta;
            }
        }
    }

    cards.forEach(c => {
        c.addEventListener('click', function () {
            const obj = this.getAttribute('data-objetivo');
            selecionarObjetivo(obj);
        });
    });

    if (selectObjetivo) {
        selectObjetivo.addEventListener('change', function () {
            selecionarObjetivo(this.value);
        });
        selecionarObjetivo(selectObjetivo.value || 'vendas');
    }
});
</script>
@endsection
