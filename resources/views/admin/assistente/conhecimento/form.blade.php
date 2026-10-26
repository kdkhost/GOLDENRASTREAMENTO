@extends('admin.layouts.app')
@php $editando = $item->exists; @endphp
@section('title', $editando ? 'Editar aprendizado' : 'Novo aprendizado')
@section('page_title', $editando ? 'Editar aprendizado' : 'Novo aprendizado')
@section('page_description', 'É isto que a máquina lê quando o cliente pergunta. Escreva a resposta, não a pergunta.')

@section('page_actions')
<a href="{{ route('admin.assistente.conhecimento.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <x-admin.card title="O que ele deve saber" icon="fa-book-open">
            <form method="POST" action="{{ $editando ? route('admin.assistente.conhecimento.update', $item) : route('admin.assistente.conhecimento.store') }}">
                @csrf
                <div class="row g-3">
                    <x-admin.field name="titulo" label="Assunto" :value="$item->titulo" :required="true" maxlength="180"
                        placeholder="Ex.: O que faço quando o aparelho fica sem sinal"
                        hint="É o que a busca interna acha quando o cliente pergunta." />
                    <x-admin.field name="corpo" type="textarea" label="Resposta" :value="$item->corpo" :required="true" :rows="10" maxlength="20000"
                        hint="Escreva como a equipe falaria. A máquina usa este texto como fonte — o que não estiver aqui ela não sabe." />
                    <x-admin.field name="tags" label="Assuntos" :value="$item->tags" maxlength="190" :opcional="true"
                        placeholder="bloqueio, sinal, plataforma"
                        hint="Palavras que ajudam a achar este item. Separadas por vírgula." />
                    <x-admin.field name="origem" type="select" label="Origem" :value="$item->origem" :options="$origens" col="col-12 col-md-6" :required="true"
                        hint="O que a pessoa escreve é 'administrador'; o resto é lido de fora." />
                    <x-admin.field name="url" label="Endereço da fonte" :value="$item->url" col="col-12 col-md-6" maxlength="500" :opcional="true"
                        placeholder="https://goldenrastreamento.com.br/planos"
                        hint="De onde este texto foi tirado. Fica na resposta para a equipe conferir." />
                    <x-admin.switch name="ativo" label="No ar (a máquina pode usar)" :checked="$item->ativo" col="col-12 d-flex align-items-end pb-2" />
                </div>
                <button class="btn btn-golden"><i class="fas fa-save me-1"></i> {{ $editando ? 'Salvar aprendizado' : 'Adicionar aprendizado' }}</button>
            </form>
        </x-admin.card>
    </div>

    <div class="col-lg-4">
        <x-admin.card title="Procedência" icon="fa-link" descricao="O que a equipe lê para conferir a resposta.">
            @if($editando)
                <dl class="defs mb-0">
                    <dt>Fonte</dt><dd>{{ $item->url ?: 'escrita no painel' }}</dd>
                    <dt>Chave</dt><dd>{{ $item->referencia ?: '—' }}</dd>
                    <dt>Lido em</dt><dd>{{ $item->lido_em ? $item->lido_em->format('d/m/Y H:i') : '—' }}</dd>
                    <dt>Escrito por</dt><dd>{{ $item->criadoPor?->name ?: '—' }}</dd>
                </dl>
            @else
                <p class="text-secondary mb-0">A chave da fonte é o que impede a leitura automática de reaprender o mesmo endereço duas vezes.</p>
            @endif
        </x-admin.card>

        <x-admin.card title="Como a máquina usa isto" icon="fa-wand-magic-sparkles" class="mt-3">
            <p class="small mb-0">
                O texto acima entra na ordem da IA só quando a pergunta do cliente bate no assunto.
                {{ config('assistente.resposta.itens_de_fonte') }} itens por resposta, no máximo, e
                @if((bool) config('assistente.resposta.so_com_fonte'))
                    sem fonte confiável a máquina chama a equipe em vez de responder.
                @else
                    sem fonte ela responde do próprio jeito — o interruptor <strong>Só responder o que tem fonte</strong> desliga isso na tela de chaves.
                @endif
            </p>
        </x-admin.card>
    </div>
</div>
@endsection
