@extends('portal.layout')
@php use App\Services\Whmcs\Cliente as Financeiro; use App\Services\Whmcs\Veiculacao; @endphp

@section('title', 'Meus serviços — Portal do cliente')
@section('page_title', 'Meus serviços')
@php
    $quantos = count($itens);
    $abreNoFinanceiro = Veiculacao::mostraLink();
    // Sem o salto para o financeiro, a coluna "Abrir" ficaria uma fileira de vazios — ela sai,
    // e a tabela conta só o que o cliente pode ver.
    $colunas = $abreNoFinanceiro
        ? ['Serviço', 'end:Valor', 'center:Vencimento', 'center:Situação', 'end:Abrir']
        : ['Serviço', 'end:Valor', 'center:Vencimento', 'center:Situação'];
    $descricaoPagina = match (true) {
        $situacao !== 'ok' => 'Os produtos e planos que você contratou com a Golden.',
        $quantos === 0 => 'Nenhum serviço contratado no financeiro desta conta ainda.',
        $quantos === 1 => '1 serviço contratado no financeiro da Golden.',
        default => $quantos.' serviços contratados no financeiro da Golden.',
    };
@endphp
@section('page_description', $descricaoPagina)

@section('page_actions')
<a href="{{ wa_url() }}" class="btn btn-sm btn-golden" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Falar com a equipe</a>
@endsection

@section('content')
@include('portal.partials.financeiro-situacao')

@if($situacao === 'ok')
    <x-admin.table :headers="$colunas"
        empty="Nenhum serviço contratado no financeiro desta conta ainda. Se você comprou um rastreador recentemente, fale com a equipe para conferir o cadastro.">
        @foreach($itens as $servico)
        <tr>
            <td>
                <span class="fw-semibold">{{ $servico['nome'] }}</span>
                @if($servico['dominio'] !== '')
                    <div class="text-secondary small">{{ $servico['dominio'] }}</div>
                @elseif($servico['grupo'] !== '')
                    <div class="text-secondary small">{{ $servico['grupo'] }}</div>
                @endif
            </td>
            <td class="text-end">
                <span class="fw-semibold text-nowrap d-block">{{ brl($servico['valor_cents']) }}</span>
                <span class="text-secondary small">{{ $servico['ciclo'] !== '' ? Financeiro::rotuloDoCiclo($servico['ciclo']) : '—' }}</span>
            </td>
            <td class="text-center small text-nowrap">{{ $servico['proximo_vencimento'] ? $servico['proximo_vencimento']->format('d/m/Y') : '—' }}</td>
            <td class="text-center"><x-admin.status :rotulo="Financeiro::rotuloDoServico($servico['status'])" :tom="Financeiro::tomDoServico($servico['status'])" /></td>
            @if($abreNoFinanceiro)
            <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary" href="{{ $servico['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="Abrir {{ $servico['nome'] }} no financeiro"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
            </td>
            @endif
        </tr>
        @endforeach
    </x-admin.table>
@endif

<x-admin.card title="Trocar de plano ou acrescentar um rastreador" icon="fa-satellite-dish" class="mt-3">
    <p class="small text-secondary mb-2">Plano, veículo e ponto de instalação mudam com a equipe — ela ajusta o financeiro e o rastreamento no mesmo lugar, sem você precisar abrir dois atendimentos.</p>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-golden" href="{{ wa_url() }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Falar no WhatsApp</a>
        @if(loja_ativa())
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('site.loja.index') }}">Ver a loja</a>
        @endif
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.faturas') }}">Minhas faturas</a>
    </div>
</x-admin.card>
@endsection
