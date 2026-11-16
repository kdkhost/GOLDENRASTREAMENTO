@extends('admin.layouts.app')
@section('title', 'Assistente virtual')
@section('page_title', 'Assistente virtual')
@section('page_description', 'Quem atende o cliente na bolha do site, na página de funil e dentro do chamado.')

@section('page_actions')
<a href="{{ route('admin.assistente.conhecimento.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-book-open me-1"></i> O que ele sabe</a>
<a href="{{ route('admin.assistente.conversas.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-comments me-1"></i> Conversas</a>
<button type="submit" form="formAssistente" class="btn btn-sm btn-golden shadow-sm fw-semibold"><i class="fas fa-save me-1"></i> Salvar alterações</button>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.assistente.persona.update') }}" id="formAssistente" data-sem-ajax>
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="A ficha" icon="fa-robot"
                descricao="É o que o cliente lê antes de qualquer resposta: nome, cargo, saudação e o tom de fala.">
                <div class="row g-3">
                    <x-admin.field name="nome" label="Nome" :value="$assistente->nome" :required="true" col="col-12 col-md-5" maxlength="120" placeholder="Ex.: Ana" />
                    <x-admin.field name="cargo" label="Cargo" :value="$assistente->cargo" col="col-12 col-md-7" maxlength="160" :opcional="true"
                        placeholder="Assistente virtual da Golden Rastreamento" />
                    <x-admin.field name="saudacao" type="textarea" label="Saudação" :value="$assistente->saudacao" col="col-12" :rows="3" maxlength="1000" :opcional="true" data-summernote="false"
                        hint="A primeira frase da conversa, quando o cliente abre a bolha." />
                    <x-admin.field name="pergunta_nome" type="textarea" label="Pergunta pelo nome" :value="$assistente->pergunta_nome" col="col-12" :rows="2" maxlength="500" :opcional="true" data-summernote="false"
                        hint="A segunda frase da bolha, logo depois da saudação: o atendimento não começa sem nome. Quantas vezes ela se repete para quem não responde se ajusta em Sistema → Configurações → Chaves de integração → Assistente." />
                    <x-admin.field name="confirmacao_nome" type="textarea" label="Resposta a quem disse o nome" :value="$assistente->confirmacao_nome" col="col-12" :rows="2" maxlength="500" :opcional="true" data-summernote="false"
                        hint="A frase que reconhece a pessoa logo depois de ela se apresentar. Escreva :nome no lugar do nome dito — vale só o primeiro nome, como no resto do atendimento." />
                    <x-admin.field name="pergunta_email" type="textarea" label="Pergunta pelo e-mail" :value="$assistente->pergunta_email" col="col-12" :rows="2" maxlength="500" :opcional="true" data-summernote="false"
                        hint="O que ele diz quando o cliente pede atendimento personalizado sem ter se identificado. É por esse e-mail que a conta é encontrada. Quantas vezes a pergunta se repete se ajusta em Sistema → Configurações → Chaves de integração → Assistente." />
                    <x-admin.field name="confirmacao_email" type="textarea" label="Resposta a quem disse o e-mail" :value="$assistente->confirmacao_email" col="col-12" :rows="2" maxlength="500" :opcional="true" data-summernote="false"
                        hint="A frase de confirmação logo depois de a pessoa informar o endereço. Escreva :nome para usar o nome dela." />
                    <x-admin.field name="confirmacao_cadastro" type="textarea" label="Resposta a quem foi reconhecido no cadastro" :value="$assistente->confirmacao_cadastro" col="col-12" :rows="2" maxlength="500" :opcional="true" data-summernote="false"
                        hint="Dita no lugar da anterior quando o e-mail encontra um cliente na ficha do CRM. É o que avisa que a conta já foi localizada. Escreva :nome para chamá-lo." />
                    <x-admin.field name="pedido_avaliacao" type="textarea" label="Pedido de avaliação" :value="$assistente->pedido_avaliacao" col="col-12" :rows="2" maxlength="500" :opcional="true" data-summernote="false"
                        hint="Dito uma única vez no fim do atendimento — quando a máquina resolveu o assunto ou quando o cliente se despediu. Escreva :nome para chamá-lo." />
                    <x-admin.field name="agradecimento_avaliacao" type="textarea" label="Agradecimento pela nota" :value="$assistente->agradecimento_avaliacao" col="col-12" :rows="2" maxlength="500" :opcional="true" data-summernote="false"
                        hint="A resposta depois de o cliente dar a nota de 1 a 5. A nota fica gravada na conversa e na tela de conversas." />
                    <x-admin.field name="despedida" type="textarea" label="Despedida" :value="$assistente->despedida" col="col-12" :rows="2" maxlength="500" :opcional="true" data-summernote="false"
                        hint="Dito quando o cliente encerra a conversa e a pesquisa já não tem mais o que pedir. Escreva :nome no lugar de quem se despede. A despedida é regra, não pergunta: nunca vira chamado para a equipe." />
                    <x-admin.field name="tom" type="select" label="Tom de fala" :value="$assistente->tom" :options="$tons" col="col-12 col-md-6" :required="true"
                        hint="Como a máquina monta as frases." />
                    <x-admin.field name="voz" type="select" label="Voz das respostas em áudio" :value="$assistente->voz" :options="$vozes" col="col-12 col-md-6" empty="A voz padrão da casa" :opcional="true"
                        hint="Só as vozes dos provedores com chave ligada. A resposta falada depende do interruptor da tela de chaves." />
                    <x-admin.field name="regras" type="textarea" label="Regras do atendimento" :value="$assistente->regras" col="col-12" :rows="7" maxlength="8000" :opcional="true" data-summernote="false"
                        hint="Uma regra por linha, escrita do jeito que você quer que ela seja lida. Vai para a máquina exatamente como foi escrita." />

                    <div class="col-12">
                        <p class="form-label mb-2">Onde ele atende</p>
                    </div>
                    <x-admin.switch name="ativo" label="Assistente ligado" :checked="$assistente->ativo" col="col-12 col-md-3" />
                    <x-admin.switch name="no_site" label="No site" :checked="$assistente->no_site" col="col-6 col-md-3" />
                    <x-admin.switch name="no_funil" label="Na página de funil" :checked="$assistente->no_funil" col="col-6 col-md-3" />
                    <x-admin.switch name="no_chamado" label="No chat do chamado" :checked="$assistente->no_chamado" col="col-12 col-md-3" />
                </div>

                <div class="mt-4 pt-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <span class="text-secondary small"><i class="fas fa-robot me-1"></i> Identidade, tom de fala e regras de atendimento.</span>
                    <button type="submit" form="formAssistente" class="btn btn-golden shadow-sm fw-semibold"><i class="fas fa-save me-1"></i> Salvar alterações da ficha</button>
                </div>
            </x-admin.card>

            <x-admin.card title="Bolha & Atendimento Humanizado" icon="fa-headset" class="mt-3"
                descricao="Configure a aparência da bolha do chat no site e os dados de contato do atendimento humanizado quando o cliente preferir falar com uma pessoa.">
                
                {{-- Seção: Textos da Bolha --}}
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <h6 class="fw-bold text-body border-bottom border-secondary border-opacity-25 pb-2 mb-0 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-comment-dots text-gold"></i> Textos da Bolha no Site
                        </h6>
                    </div>
                    <x-admin.field name="dica" label="Dica ao passar o mouse" :value="$assistente->dica" col="col-12 col-md-6" maxlength="80" :opcional="true"
                        placeholder="Falar com :nome" hint="Use :nome para o nome da ficha." />
                    <x-admin.field name="icone" type="icon" label="Ícone sem foto" :value="$assistente->icone" col="col-12 col-md-6" :opcional="true"
                        hint="Só aparece quando não há rosto enviado. Selecione pelo ícone." />
                    <x-admin.field name="status" label="Selo de status" :value="$assistente->status" col="col-12 col-md-6" maxlength="60" :opcional="true"
                        placeholder="Online agora" />
                    <x-admin.field name="placeholder" label="Texto do campo de mensagem" :value="$assistente->placeholder" col="col-12 col-md-6" maxlength="120" :opcional="true"
                        placeholder="Escreva sua mensagem…" />
                </div>

                {{-- Seção: Dados do Atendimento Humanizado --}}
                <div class="row g-3">
                    <div class="col-12">
                        <h6 class="fw-bold text-body border-bottom border-secondary border-opacity-25 pb-2 mb-0 d-flex align-items-center gap-2">
                            <i class="fa-brands fa-whatsapp text-success"></i> Dados do Atendimento Humanizado & Contato Direto
                        </h6>
                    </div>

                    <x-admin.field name="humano" label="Texto do link para atendimento humano" :value="$assistente->humano" col="col-12 col-md-6" maxlength="120" :opcional="true"
                        placeholder="Prefiro falar com uma pessoa →" hint="Texto exibido no rodapé do chat para transferir para um atendente humano." />

                    <x-admin.field name="assistente_destino_humano" type="select" label="Ação ao clicar no atendimento humano" :value="$assistente_destino_humano" :options="[
                        'whatsapp' => 'Abrir WhatsApp oficial diretamente (com resumo da conversa)',
                        'contato' => 'Abrir formulário da página ou ir para /contato',
                        'portal' => 'Abrir chamado no Portal do Cliente (/portal/chamados/novo)',
                    ]" col="col-12 col-md-6" hint="Para onde o cliente é levado quando clica em 'Prefiro falar com uma pessoa'." />

                    <x-admin.field name="contato_whatsapp" label="WhatsApp do Atendimento Humanizado" :value="$contato_whatsapp" col="col-12 col-md-6" maxlength="32"
                        placeholder="5519997101483" hint="DDI + DDD + número (ex: 5519997101483). Usado pela Ana e pelo botão de contato direto." />

                    <x-admin.field name="contato_telefone" label="Telefone Fixo / 0800 da Empresa" :value="$contato_telefone" col="col-12 col-md-6" maxlength="60" :opcional="true"
                        placeholder="(19) 99710-1483 ou 0800..." hint="Telefone exibido nas respostas e página de contato." />

                    <x-admin.field name="contato_email" type="email" label="E-mail da Equipe de Suporte / Atendimento" :value="$contato_email" col="col-12 col-md-6" maxlength="160" :opcional="true"
                        placeholder="atendimento@goldenrastreamento.com.br" hint="E-mail que recebe os chamados e resumo das conversas escaladas." />

                    <x-admin.field name="assistente_horario_humano" label="Horário do Atendimento Humano" :value="$assistente_horario_humano" col="col-12 col-md-6" maxlength="120" :opcional="true"
                        placeholder="Segunda a Sexta, das 08h às 18h" hint="Horário informado aos clientes caso chamem fora do expediente." />

                    <x-admin.field name="assistente_msg_humano" type="textarea" :rows="2" label="Mensagem pré-formatada do WhatsApp" :value="$assistente_msg_humano" col="col-12" maxlength="500" :opcional="true" data-summernote="false"
                        placeholder="Olá! Estava conversando com a :assistente no site e gostaria de falar com um atendente humano." hint="Mensagem enviada automaticamente ao abrir o WhatsApp do consultor humano. Use :assistente para o nome do robô." />

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
                    <span class="text-secondary small"><i class="fas fa-headset text-gold me-1"></i> Salva os dados de contato do atendimento humanizado e textos da bolha.</span>
                    <button type="submit" form="formAssistente" class="btn btn-golden shadow-sm fw-semibold"><i class="fas fa-save me-1"></i> Salvar dados do atendimento</button>
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Rosto" icon="fa-image"
                descricao="A imagem da bolha do chat e do avatar nas conversas. Sem foto, o site desenha a inicial do nome.">
                @include('partials.upload', [
                    'campo' => \App\Models\Assistente::CAMPO_ENVIO,
                    'name' => 'avatar',
                    'valor' => old('avatar', $assistente->avatar),
                ])

                <x-admin.field name="avatar_alt" label="Descrição do rosto" :value="$assistente->avatar_alt" col="col-12 mt-3" maxlength="120" :opcional="true"
                    hint="Lida por quem navega com leitor de tela." />

                @if($assistente->avatarUrl())
                    <p class="form-text mt-2 mb-0">
                        <a href="{{ $assistente->avatarUrl() }}" target="_blank" rel="noopener">
                            <i class="fas fa-up-right-from-square me-1"></i> Conferir o arquivo publicado
                        </a>
                    </p>
                @endif
            </x-admin.card>

            <x-admin.card title="Ele consegue responder?" icon="fa-plug" class="mt-3">
                @if($ligado)
                    <p class="mb-0"><x-admin.status rotulo="pronto para atender" :tom="tom_por_flag(true)" /></p>
                @else
                    <p class="mb-2"><x-admin.status rotulo="sem provedor ligado" :tom="tom_por_flag(false)" /></p>
                @endif

                @if($faltando !== [])
                    <ul class="small text-secondary mt-2 mb-0 ps-3">
                        @foreach($faltando as $aviso)
                            <li>{{ $aviso }}</li>
                        @endforeach
                    </ul>
                @endif

                <p class="form-hint mt-2 mb-0">
                    <a href="{{ route('admin.settings.index') }}"><i class="fas fa-key me-1"></i> Chaves e modelos por capacidade</a>
                </p>
            </x-admin.card>
        </div>
    </div>
</form>
@endsection
