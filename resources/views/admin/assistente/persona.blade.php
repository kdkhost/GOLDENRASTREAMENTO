@extends('admin.layouts.app')
@section('title', 'Assistente virtual')
@section('page_title', 'Assistente virtual')
@section('page_description', 'Quem atende o cliente na bolha do site, na página de funil e dentro do chamado.')

@section('page_actions')
<a href="{{ route('admin.assistente.conhecimento.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-book-open me-1"></i> O que ele sabe</a>
<a href="{{ route('admin.assistente.conversas.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-comments me-1"></i> Conversas</a>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.assistente.persona.update') }}">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="A ficha" icon="fa-robot"
                descricao="É o que o cliente lê antes de qualquer resposta: nome, cargo, saudação e o tom de fala.">
                <div class="row g-3">
                    <x-admin.field name="nome" label="Nome" :value="$assistente->nome" :required="true" col="col-12 col-md-5" maxlength="120" placeholder="Ex.: Ana" />
                    <x-admin.field name="cargo" label="Cargo" :value="$assistente->cargo" col="col-12 col-md-7" maxlength="160" :opcional="true"
                        placeholder="Assistente virtual da Golden Rastreamento" />
                    <x-admin.field name="saudacao" type="textarea" label="Saudação" :value="$assistente->saudacao" col="col-12" :rows="3" maxlength="1000" :opcional="true"
                        hint="A primeira frase da conversa, quando o cliente abre a bolha." />
                    <x-admin.field name="tom" type="select" label="Tom de fala" :value="$assistente->tom" :options="$tons" col="col-12 col-md-6" :required="true"
                        hint="Como a máquina monta as frases." />
                    <x-admin.field name="voz" type="select" label="Voz das respostas em áudio" :value="$assistente->voz" :options="$vozes" col="col-12 col-md-6" empty="A voz padrão da casa" :opcional="true"
                        hint="Só as vozes dos provedores com chave ligada. A resposta falada depende do interruptor da tela de chaves." />
                    <x-admin.field name="regras" type="textarea" label="Regras do atendimento" :value="$assistente->regras" col="col-12" :rows="7" maxlength="8000" :opcional="true"
                        hint="Uma regra por linha, escrita do jeito que você quer que ela seja lida. Vai para a máquina exatamente como foi escrita." />

                    <div class="col-12">
                        <p class="form-label mb-2">Onde ele atende</p>
                    </div>
                    <x-admin.switch name="ativo" label="Assistente ligado" :checked="$assistente->ativo" col="col-12 col-md-3" />
                    <x-admin.switch name="no_site" label="No site" :checked="$assistente->no_site" col="col-6 col-md-3" />
                    <x-admin.switch name="no_funil" label="Na página de funil" :checked="$assistente->no_funil" col="col-6 col-md-3" />
                    <x-admin.switch name="no_chamado" label="No chat do chamado" :checked="$assistente->no_chamado" col="col-12 col-md-3" />
                </div>

                <div class="mt-3">
                    <button class="btn btn-golden"><i class="fas fa-save me-1"></i> Salvar ficha</button>
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
