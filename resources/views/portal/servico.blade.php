@extends('portal.layout')
@php use App\Services\Whmcs\Cliente as Financeiro; @endphp

@php
    $nome = (string) ($servico['nome'] ?? 'Meu serviço');
    // Os dois conjuntos vêm do mesmo lugar do financeiro e significam a mesma coisa para quem
    // lê: o que a casa anotou no serviço (veículo, placa, chip, IMEI) e o que o plano oferece.
    $inclusos = array_merge($servico['campos_extras'] ?? [], $servico['opcoes'] ?? []);
    $suspenso = mb_strtolower((string) ($servico['status'] ?? '')) === 'suspended';
@endphp

@section('title', $nome.' — Portal do cliente')
@section('page_title', $nome)
@section('page_description', $servico
    ? Financeiro::rotuloDoServico($servico['status']).($servico['grupo'] !== '' ? ' · '.$servico['grupo'] : '')
    : 'O detalhe do serviço que você contratou com a Golden.')

@section('page_actions')
<a href="{{ wa_url() }}" class="btn btn-sm btn-golden" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Falar sobre este serviço</a>
<a href="{{ route('portal.servicos') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Meus serviços</a>
@endsection

@section('content')
@include('portal.partials.financeiro-situacao')

@if($servico)
<div class="row g-3">
    <div class="col-lg-7">
        <x-admin.card title="O que está contratado" icon="fa-satellite-dish">
            <x-slot:actions>
                <x-admin.status :rotulo="Financeiro::rotuloDoServico($servico['status'])" :tom="Financeiro::tomDoServico($servico['status'])" />
            </x-slot:actions>
            <dl class="defs mb-0">
                <dt>Plano</dt><dd>{{ $servico['nome'] }}</dd>
                @if($servico['grupo'] !== '')
                    <dt>Grupo no financeiro</dt><dd>{{ $servico['grupo'] }}</dd>
                @endif
                <dt>Valor que se repete</dt>
                <dd>
                    {{ brl($servico['valor_cents']) }}
                    @if($servico['ciclo'] !== '')
                        <span class="text-secondary small">· {{ Financeiro::rotuloDoCiclo($servico['ciclo']) }}</span>
                    @endif
                </dd>
                @if($servico['primeiro_pagamento_cents'] > 0)
                    <dt>Primeiro pagamento</dt><dd>{{ brl($servico['primeiro_pagamento_cents']) }}</dd>
                @endif
                @if($servico['forma_de_pagamento'] !== '')
                    <dt>Forma de pagamento</dt><dd>{{ $servico['forma_de_pagamento'] }}</dd>
                @endif
                <dt>Contratado em</dt><dd>{{ $servico['contratado_em']?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Próximo vencimento</dt><dd>{{ $servico['proximo_vencimento']?->format('d/m/Y') ?? '—' }}</dd>
                @if($servico['atualizado_em'] !== null)
                    <dt>Última atualização</dt><dd>{{ $servico['atualizado_em']->format('d/m/Y') }}</dd>
                @endif
                @if($servico['dominio'] !== '')
                    <dt>Endereço vinculado</dt><dd>{{ $servico['dominio'] }}</dd>
                @endif
                @if($servico['pedido_numero'] !== '')
                    <dt>Pedido de origem</dt><dd>#{{ $servico['pedido_numero'] }}</dd>
                @endif
            </dl>
            @if($suspenso)
                <p class="small text-secondary mt-3 mb-0">
                    Este serviço está suspenso no financeiro. Fale com a equipe pelo WhatsApp: ela diz o que falta e reativa no mesmo atendimento.
                </p>
            @endif
        </x-admin.card>

        <x-admin.table class="mt-3" :headers="['Item incluso', 'Valor']"
            title="Itens inclusos no serviço" icon="fa-box-open"
            :descricao="($inclusos !== [] ? count($inclusos).' anotação(ões) do cadastro deste serviço.' : null)"
            empty="Nenhum item extra registrado neste serviço.">
            @foreach($inclusos as $item)
                <tr>
                    <td class="fw-semibold">{{ $item['rotulo'] }}</td>
                    <td>{{ $item['valor'] }}</td>
                </tr>
            @endforeach
        </x-admin.table>
    </div>

    <div class="col-lg-5">
        <x-admin.table :headers="['Adicional', 'center:Situação', 'center:Vence em', 'end:Valor']"
            title="Itens vinculados" icon="fa-link"
            :descricao="($adicionais !== null ? count($adicionais).' adicional(is) pendurado(s) neste serviço.' : null)"
            empty="Nenhum adicional vinculado a este serviço.">
            @foreach($adicionais ?? [] as $addon)
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $addon['nome'] }}</span>
                        @if($addon['forma_de_pagamento'] !== '')
                            <div class="text-secondary small">{{ $addon['forma_de_pagamento'] }}</div>
                        @endif
                        @if($addon['contratado_em'] !== null)
                            <div class="text-secondary small">desde {{ $addon['contratado_em']->format('d/m/Y') }}</div>
                        @endif
                    </td>
                    <td class="text-center"><x-admin.status :rotulo="Financeiro::rotuloDoServico($addon['status'])" :tom="Financeiro::tomDoServico($addon['status'])" /></td>
                    <td class="text-center small text-nowrap">{{ $addon['proximo_vencimento']?->format('d/m/Y') ?? '—' }}</td>
                    <td class="text-end">
                        <span class="fw-semibold text-nowrap d-block">{{ brl($addon['valor_cents']) }}</span>
                        <span class="text-secondary small">{{ $addon['ciclo'] !== '' ? Financeiro::rotuloDoCiclo($addon['ciclo']) : '—' }}</span>
                    </td>
                </tr>
            @endforeach
            @if($adicionais === null)
                <x-admin.financeiro-mudo :colunas="4" :oQue="'de adicionais'" />
            @endif
        </x-admin.table>

        <x-admin.card title="Mudar plano, veículo ou ponto de instalação" icon="fa-arrows-rotate" class="mt-3">
            <p class="small text-secondary mb-2">Ajuste de plano e de instalação é feito pela equipe, no financeiro e no rastreamento ao mesmo tempo — você não precisa abrir dois atendimentos.</p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-golden" href="{{ wa_url() }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Falar no WhatsApp</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.faturas') }}">Minhas faturas</a>
                @if($servico['url'] !== '')
                    <a class="btn btn-sm btn-outline-secondary" href="{{ $servico['url'] }}" target="_blank" rel="noopener noreferrer"><i class="fas fa-arrow-up-right-from-square me-1"></i> Abrir no financeiro</a>
                @endif
            </div>
        </x-admin.card>
    </div>
</div>
@endif
@endsection
