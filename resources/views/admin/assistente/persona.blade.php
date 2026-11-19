@extends('admin.layouts.app')
@section('title', 'Assistente Virtual — ' . ($assistente->nome ?: 'Ana'))
@section('page_title', 'Central da Assistente Virtual (' . ($assistente->nome ?: 'Ana') . ')')
@section('page_description', 'Configure personalidade, treinamento, o que ela deve fazer, restrições, permissões no sistema e atendimento humanizado.')

@section('page_actions')
<a href="{{ route('admin.assistente.conhecimento.index') }}" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-book-open me-1"></i> Base de Conhecimento ({{ $totalConhecimento }})
</a>
<a href="{{ route('admin.assistente.conversas.index') }}" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-comments me-1"></i> Conversas
</a>
<button type="submit" form="formAssistente" class="btn btn-sm btn-golden shadow-sm fw-semibold">
    <i class="fas fa-save me-1"></i> Salvar alterações
</button>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.assistente.persona.update') }}" id="formAssistente" data-sem-ajax>
    @csrf
    <input type="hidden" name="acessos_enviado" value="1">

    <div class="row g-3">
        <div class="col-lg-8">
            {{-- Navegação por Abas Organizadas --}}
            <ul class="nav nav-pills gap-2 mb-3 bg-body-tertiary p-2 rounded-3 border border-secondary border-opacity-25" id="anaTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold small" id="tab-personalidade-btn" data-bs-toggle="pill" data-bs-target="#tab-personalidade" type="button" role="tab">
                        <i class="fas fa-user-astronaut me-1"></i> 1. Personalidade
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold small" id="tab-treinamento-btn" data-bs-toggle="pill" data-bs-target="#tab-treinamento" type="button" role="tab">
                        <i class="fas fa-graduation-cap me-1"></i> 2. Treinamento & Restrições
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold small" id="tab-acessos-btn" data-bs-toggle="pill" data-bs-target="#tab-acessos" type="button" role="tab">
                        <i class="fas fa-shield-halved me-1"></i> 3. Acessos no Sistema
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold small" id="tab-falas-btn" data-bs-toggle="pill" data-bs-target="#tab-falas" type="button" role="tab">
                        <i class="fas fa-comment-dots me-1"></i> 4. Falas do Fluxo
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold small" id="tab-bolha-btn" data-bs-toggle="pill" data-bs-target="#tab-bolha" type="button" role="tab">
                        <i class="fas fa-headset me-1"></i> 5. Bolha & Humano
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="anaTabsContent">
                {{-- ABA 1: PERSONALIDADE & CANAIS --}}
                <div class="tab-pane fade show active" id="tab-personalidade" role="tabpanel">
                    <x-admin.card title="Identidade, Tom de Voz e Canais" icon="fa-robot"
                        descricao="Define como a assistente se apresenta, o estilo de comunicação com o cliente e em quais canais ela está ativa.">
                        <div class="row g-3">
                            <x-admin.field name="nome" label="Nome da Assistente" :value="$assistente->nome" :required="true" col="col-12 col-md-5" maxlength="120" placeholder="Ex.: Ana" />
                            <x-admin.field name="cargo" label="Cargo / Especialidade" :value="$assistente->cargo" col="col-12 col-md-7" maxlength="160" :opcional="true"
                                placeholder="Consultora de Rastreamento Veicular · Golden Rastreamento" />

                            <x-admin.field name="tom" type="select" label="Tom de voz e postura" :value="$assistente->tom" :options="$tons" col="col-12 col-md-6" :required="true"
                                hint="Recomendado: Cordial, humanizado e consultivo." />
                            <x-admin.field name="voz" type="select" label="Voz das respostas em áudio" :value="$assistente->voz" :options="$vozes" col="col-12 col-md-6" empty="A voz padrão da casa" :opcional="true"
                                hint="Só exibe vozes dos provedores de IA com chave configurada." />

                            <div class="col-12 mt-2 pt-3 border-top">
                                <label class="form-label fw-semibold mb-2"><i class="fas fa-broadcast-tower me-1 text-golden"></i> Onde a {{ $assistente->nome ?: 'Ana' }} atende</label>
                                <div class="row g-2">
                                    <div class="col-12 col-md-6">
                                        <div class="p-3 rounded border bg-light-subtle">
                                            <x-admin.switch name="ativo" label="Assistente ligada (Interruptor Geral)" :checked="$assistente->ativo" hint="Se desligado, o chat fica apenas para equipe humana." />
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="p-3 rounded border bg-light-subtle">
                                            <x-admin.switch name="no_site" label="Atender na bolha do Site e Loja" :checked="$assistente->no_site" hint="Exibe o chat inteligente nas páginas públicas e na loja." />
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="p-3 rounded border bg-light-subtle">
                                            <x-admin.switch name="no_funil" label="Atender nas Páginas de Funil" :checked="$assistente->no_funil" hint="Ajuda visitantes vindos de campanhas e anúncios." />
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="p-3 rounded border bg-light-subtle">
                                            <x-admin.switch name="no_chamado" label="Atender no Chat dos Chamados" :checked="$assistente->no_chamado" hint="Faz triagem inicial dentro do Portal do Cliente." />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-admin.card>
                </div>

                {{-- ABA 2: TREINAMENTO, COMPORTAMENTO & RESTRIÇÕES --}}
                <div class="tab-pane fade" id="tab-treinamento" role="tabpanel">
                    <x-admin.card title="Treinamento, Comportamento e Restrições" icon="fa-graduation-cap"
                        descricao="Ensine exatamente o que a Ana deve fazer durante o atendimento, o que é proibido fazer e orientações especiais da Golden Rastreamento.">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 p-3 rounded bg-body-tertiary border border-secondary border-opacity-25">
                            <div class="small text-secondary">
                                <i class="fas fa-lightbulb text-golden me-1"></i>
                                Escreva em linguagem natural, como se estivesse treinando uma nova consultora da sua equipe.
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRestaurarPadraoAna">
                                <i class="fas fa-wand-magic-sparkles me-1"></i> Carregar modelo humanizado Golden
                            </button>
                        </div>

                        <div class="row g-3">
                            <x-admin.field name="objetivo_atendimento" type="textarea"
                                label="O que a Ana deve fazer durante o atendimento (Missão e Tarefas)"
                                :value="$assistente->objetivo_atendimento ?: ($padroesPersona['objetivo_atendimento'] ?? '')"
                                col="col-12" :rows="5" maxlength="5000" :opcional="true" data-summernote="false"
                                hint="Explique qual o papel dela: acolher, tirar dúvidas sobre rastreadores/planos/chips, orientar compras na loja, ajudar com 2ª via de fatura ou encaminhar ao comercial." />

                            <x-admin.field name="restricoes" type="textarea"
                                label="Restrições e Proibições (O que a Ana NÃO pode fazer)"
                                :value="$assistente->restricoes ?: ($padroesPersona['restricoes'] ?? '')"
                                col="col-12" :rows="5" maxlength="5000" :opcional="true" data-summernote="false"
                                hint="Regras rígidas de segurança: nunca inventar valores, nunca divulgar preços de itens sob consulta, não prometer descontos ou instalação gratuita, nunca pedir senha ou cartão." />

                            <x-admin.field name="regras" type="textarea"
                                label="Estilo de Conversa e Conduta Humanizada"
                                :value="$assistente->regras"
                                col="col-12" :rows="5" maxlength="8000" :opcional="true" data-summernote="false"
                                hint="Como ela deve escrever: tamanho das respostas, cordialidade, adaptação ao cliente, postura firme em emergências/roubo." />

                            <x-admin.field name="instrucoes_extras" type="textarea"
                                label="Aprendizado Extra & Orientações Personalizadas do Administrador"
                                :value="$assistente->instrucoes_extras ?: ($padroesPersona['instrucoes_extras'] ?? '')"
                                col="col-12" :rows="5" maxlength="5000" :opcional="true" data-summernote="false"
                                hint="Use este espaço para ensinar novidades, campanhas vigentes, diferenciais dos produtos/chips ou respostas específicas que você quer que a Ana memorize." />
                        </div>
                    </x-admin.card>
                </div>

                {{-- ABA 3: ACESSOS E PERMISSÕES NO SISTEMA --}}
                <div class="tab-pane fade" id="tab-acessos" role="tabpanel">
                    <x-admin.card title="Permissões e Acessos da Ana no Sistema" icon="fa-shield-halved"
                        descricao="Ligue ou desligue exatamente o que a Ana tem permissão para consultar ou executar durante o atendimento.">
                        <div class="row g-3">
                            @foreach($acessosDefinicao as $chaveAcesso => $defAcesso)
                                <div class="col-12 col-md-6">
                                    <div class="p-3 rounded border bg-light-subtle h-100">
                                        <x-admin.switch
                                            name="acessos_sistema[{{ $chaveAcesso }}]"
                                            :label="$defAcesso['rotulo']"
                                            :checked="(bool) ($acessosAtuais[$chaveAcesso] ?? $defAcesso['padrao'])"
                                            :hint="$defAcesso['descricao']"
                                        />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-admin.card>
                </div>

                {{-- ABA 4: FALAS PRONTAS & FLUXO DA CONVERSA --}}
                <div class="tab-pane fade" id="tab-falas" role="tabpanel">
                    <x-admin.card title="Falas Prontas do Fluxo de Atendimento" icon="fa-comment-dots"
                        descricao="Personalize as mensagens automáticas de cada etapa da conversa. Use :nome onde quiser inserir o primeiro nome do cliente.">
                        <div class="row g-3">
                            <div class="col-12">
                                <h6 class="fw-bold text-body border-bottom border-secondary border-opacity-25 pb-2 mb-0">
                                    <i class="fas fa-hand-sparkles text-golden me-1"></i> Etapa 1 — Abertura e Boas-vindas
                                </h6>
                            </div>
                            <x-admin.field name="saudacao" type="textarea" label="Saudação inicial" :value="$assistente->saudacao" col="col-12" :rows="2" maxlength="1000" :opcional="true" data-summernote="false"
                                hint="Primeira mensagem exibida assim que o cliente abre o chat." />
                            <x-admin.field name="pergunta_nome" type="textarea" label="Pergunta pelo nome" :value="$assistente->pergunta_nome" col="col-12 col-md-6" :rows="2" maxlength="500" :opcional="true" data-summernote="false" />
                            <x-admin.field name="confirmacao_nome" type="textarea" label="Resposta após dizer o nome" :value="$assistente->confirmacao_nome" col="col-12 col-md-6" :rows="2" maxlength="500" :opcional="true" data-summernote="false"
                                hint="Use :nome para chamar o cliente pelo primeiro nome." />

                            <div class="col-12 mt-2">
                                <h6 class="fw-bold text-body border-bottom border-secondary border-opacity-25 pb-2 mb-0">
                                    <i class="fas fa-id-card text-golden me-1"></i> Etapa 2 — Identificação do Cadastro (2ª via / Suporte personalizado)
                                </h6>
                            </div>
                            <x-admin.field name="pergunta_email" type="textarea" label="Pergunta pelo e-mail" :value="$assistente->pergunta_email" col="col-12" :rows="2" maxlength="500" :opcional="true" data-summernote="false" />
                            <x-admin.field name="confirmacao_email" type="textarea" label="Confirmação do e-mail informado" :value="$assistente->confirmacao_email" col="col-12 col-md-6" :rows="2" maxlength="500" :opcional="true" data-summernote="false" />
                            <x-admin.field name="confirmacao_cadastro" type="textarea" label="Quando o cadastro é localizado no CRM" :value="$assistente->confirmacao_cadastro" col="col-12 col-md-6" :rows="2" maxlength="500" :opcional="true" data-summernote="false" />

                            <div class="col-12 mt-2">
                                <h6 class="fw-bold text-body border-bottom border-secondary border-opacity-25 pb-2 mb-0">
                                    <i class="fas fa-star text-golden me-1"></i> Etapa 3 — Avaliação e Despedida
                                </h6>
                            </div>
                            <x-admin.field name="pedido_avaliacao" type="textarea" label="Pedido de avaliação (1 a 5 estrelas)" :value="$assistente->pedido_avaliacao" col="col-12 col-md-6" :rows="2" maxlength="500" :opcional="true" data-summernote="false" />
                            <x-admin.field name="agradecimento_avaliacao" type="textarea" label="Agradecimento pela nota" :value="$assistente->agradecimento_avaliacao" col="col-12 col-md-6" :rows="2" maxlength="500" :opcional="true" data-summernote="false" />
                            <x-admin.field name="despedida" type="textarea" label="Mensagem de despedida" :value="$assistente->despedida" col="col-12" :rows="2" maxlength="500" :opcional="true" data-summernote="false" />
                        </div>
                    </x-admin.card>
                </div>

                {{-- ABA 5: BOLHA DO SITE & ATENDIMENTO HUMANO --}}
                <div class="tab-pane fade" id="tab-bolha" role="tabpanel">
                    <x-admin.card title="Bolha do Site & Atendimento Humanizado" icon="fa-headset"
                        descricao="Configure a aparência do botão do chat no site e os dados de contato direto quando o cliente preferir falar com uma pessoa.">
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <h6 class="fw-bold text-body border-bottom border-secondary border-opacity-25 pb-2 mb-0 d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-comment-dots text-gold"></i> Aparência da Bolha no Site
                                </h6>
                            </div>
                            <x-admin.field name="dica" label="Dica ao passar o mouse" :value="$assistente->dica" col="col-12 col-md-6" maxlength="80" :opcional="true"
                                placeholder="Falar com :nome" hint="Use :nome para exibir o nome da assistente." />
                            <x-admin.field name="icone" type="icon" label="Ícone quando sem foto" :value="$assistente->icone" col="col-12 col-md-6" :opcional="true" />
                            <x-admin.field name="status" label="Selo de status" :value="$assistente->status" col="col-12 col-md-6" maxlength="60" :opcional="true"
                                placeholder="Online agora" />
                            <x-admin.field name="placeholder" label="Texto do campo de mensagem" :value="$assistente->placeholder" col="col-12 col-md-6" maxlength="120" :opcional="true"
                                placeholder="Escreva sua mensagem…" />
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <h6 class="fw-bold text-body border-bottom border-secondary border-opacity-25 pb-2 mb-0 d-flex align-items-center gap-2">
                                    <i class="fa-brands fa-whatsapp text-success"></i> Atendimento Humanizado & Contato Direto
                                </h6>
                            </div>

                            <x-admin.field name="humano" label="Texto do botão de atendimento humano" :value="$assistente->humano" col="col-12 col-md-6" maxlength="120" :opcional="true"
                                placeholder="Prefiro falar com uma pessoa →" />

                            <x-admin.field name="assistente_destino_humano" type="select" label="Destino ao clicar em atendimento humano" :value="$assistente_destino_humano" :options="[
                                'whatsapp' => 'Abrir WhatsApp oficial diretamente (com resumo da conversa)',
                                'contato' => 'Abrir formulário da página ou ir para /contato',
                                'portal' => 'Abrir chamado no Portal do Cliente (/portal/chamados/novo)',
                            ]" col="col-12 col-md-6" />

                            <x-admin.field name="contato_whatsapp" label="WhatsApp Oficial" :value="$contato_whatsapp" col="col-12 col-md-6" maxlength="32"
                                placeholder="5519997101483" hint="DDI + DDD + número (ex: 5519997101483)." />

                            <x-admin.field name="contato_telefone" label="Telefone Fixo / 0800" :value="$contato_telefone" col="col-12 col-md-6" maxlength="60" :opcional="true" />

                            <x-admin.field name="contato_email" type="email" label="E-mail da Equipe de Atendimento" :value="$contato_email" col="col-12 col-md-6" maxlength="160" :opcional="true" />

                            <x-admin.field name="assistente_horario_humano" label="Horário do Atendimento Humano" :value="$assistente_horario_humano" col="col-12 col-md-6" maxlength="120" :opcional="true"
                                placeholder="Segunda a Sexta, das 08h às 18h" />

                            <x-admin.field name="assistente_msg_humano" type="textarea" :rows="2" label="Mensagem pré-formatada do WhatsApp" :value="$assistente_msg_humano" col="col-12" maxlength="500" :opcional="true" data-summernote="false"
                                placeholder="Olá! Estava conversando com a :assistente no site e gostaria de falar com um atendente humano." />

                            <div class="col-12 mt-2">
                                <div class="p-3 rounded-3 bg-body-tertiary border border-secondary border-opacity-25 d-flex align-items-center justify-content-between flex-wrap gap-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="badge rounded-circle bg-success bg-opacity-10 text-success p-2 fs-4">
                                            <i class="fa-brands fa-whatsapp"></i>
                                        </span>
                                        <div>
                                            <strong class="d-block text-body fs-6">Canal WhatsApp Oficial Ativo</strong>
                                            <small class="text-secondary">
                                                Número ativo: <strong class="text-body font-monospace">{{ $contato_whatsapp }}</strong>
                                                @if($contato_whatsapp)
                                                    · <a href="https://wa.me/{{ preg_replace('/\D/', '', $contato_whatsapp) }}" target="_blank" rel="noopener noreferrer" class="text-gold fw-semibold">
                                                        <i class="fas fa-up-right-from-square me-1"></i> Testar link wa.me
                                                    </a>
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                    <span class="badge text-bg-success px-3 py-2 fw-semibold">
                                        <i class="fas fa-check-circle me-1"></i> Integrado ao assistente
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <span class="text-secondary small"><i class="fas fa-headset text-golden me-1"></i> Salva os dados de contato do atendimento humanizado e textos da bolha.</span>
                            <button type="submit" form="formAssistente" class="btn btn-golden shadow-sm fw-semibold">
                                <i class="fas fa-save me-1"></i> Salvar dados do atendimento
                            </button>
                        </div>
                    </x-admin.card>
                </div>
            </div>

            <div class="mt-3 p-3 rounded-3 bg-body-tertiary border border-secondary border-opacity-25 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span class="text-secondary small"><i class="fas fa-circle-info text-golden me-1"></i> As alterações em qualquer aba são salvas juntas ao clicar no botão ao lado.</span>
                <button type="submit" form="formAssistente" class="btn btn-golden shadow-sm fw-semibold">
                    <i class="fas fa-save me-1"></i> Salvar todas as configurações da {{ $assistente->nome ?: 'Ana' }}
                </button>
            </div>
        </div>

        {{-- COLUNA DIREITA: AVATAR, STATUS E TREINAMENTO RÁPIDO --}}
        <div class="col-lg-4">
            <x-admin.card title="Foto / Avatar da {{ $assistente->nome ?: 'Ana' }}" icon="fa-image"
                descricao="Imagem exibida na bolha do chat e ao lado das respostas.">
                @include('partials.upload', [
                    'campo' => \App\Models\Assistente::CAMPO_ENVIO,
                    'name' => 'avatar',
                    'valor' => old('avatar', $assistente->avatar),
                ])

                <x-admin.field name="avatar_alt" label="Descrição acessível da foto" :value="$assistente->avatar_alt" col="col-12 mt-3" maxlength="120" :opcional="true" />

                @if($assistente->avatarUrl())
                    <p class="form-text mt-2 mb-0">
                        <a href="{{ $assistente->avatarUrl() }}" target="_blank" rel="noopener">
                            <i class="fas fa-up-right-from-square me-1"></i> Conferir imagem publicada
                        </a>
                    </p>
                @endif
            </x-admin.card>

            <x-admin.card title="Status & Aprendizado" icon="fa-brain" class="mt-3">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="small fw-semibold text-secondary">Motor de IA</span>
                    @if($ligado)
                        <x-admin.status rotulo="pronta para atender" :tom="tom_por_flag(true)" />
                    @else
                        <x-admin.status rotulo="sem provedor ligado" :tom="tom_por_flag(false)" />
                    @endif
                </div>

                @if($faltando !== [])
                    <ul class="small text-secondary mb-3 ps-3">
                        @foreach($faltando as $aviso)
                            <li>{{ $aviso }}</li>
                        @endforeach
                    </ul>
                @endif

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2 rounded border bg-light-subtle text-center">
                            <div class="fs-5 fw-bold text-body">{{ $totalConhecimento }}</div>
                            <div class="small text-secondary">Itens na base</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded border bg-light-subtle text-center">
                            <div class="fs-5 fw-bold text-body">{{ $totalProdutosAtivos }}</div>
                            <div class="small text-secondary">Produtos ativos</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2">
                    <button type="submit" form="formTreinarAna" class="btn btn-outline-primary btn-sm w-100 fw-semibold">
                        <i class="fas fa-rotate me-1"></i> Sincronizar catálogo e treinar agora
                    </button>
                    <a href="{{ route('admin.assistente.conhecimento.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="fas fa-book-open me-1"></i> Gerenciar Base de Conhecimento
                    </a>
                    <a href="{{ route('admin.settings.index') }}" class="btn btn-link btn-sm text-decoration-none p-0 mt-1">
                        <i class="fas fa-key me-1"></i> Chaves de API e modelos de IA
                    </a>
                </div>
            </x-admin.card>
        </div>
    </div>
</form>

<form method="POST" action="{{ route('admin.assistente.treinar') }}" id="formTreinarAna" class="d-none">
    @csrf
</form>

<script>
(function() {
    var btnRestaurar = document.getElementById('btnRestaurarPadraoAna');
    if (!btnRestaurar) return;
    var padroes = @json($padroesPersona);
    btnRestaurar.addEventListener('click', function() {
        var campos = ['objetivo_atendimento', 'restricoes', 'regras', 'instrucoes_extras'];
        campos.forEach(function(nome) {
            var el = document.querySelector('[name="' + nome + '"]');
            if (el && padroes[nome]) {
                el.value = padroes[nome];
            }
        });
        var tom = document.querySelector('[name="tom"]');
        if (tom && padroes.tom) {
            tom.value = padroes.tom;
        }
        btnRestaurar.innerHTML = '<i class="fas fa-check text-success me-1"></i> Modelo humanizado carregado! Clique em Salvar.';
    });
})();
</script>
@endsection
