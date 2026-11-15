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
        ? ['Serviço', 'end:Valor', 'center:Vencimento', 'center:Situação', 'end:Detalhes', 'end:Abrir']
        : ['Serviço', 'end:Valor', 'center:Vencimento', 'center:Situação', 'end:Detalhes'];
    $descricaoPagina = match (true) {
        $situacao !== 'ok' => 'Os produtos e planos que você contratou com a Golden.',
        $quantos === 0 => 'Nenhum serviço contratado no financeiro desta conta ainda.',
        $quantos === 1 => '1 serviço contratado no financeiro da Golden.',
        default => $quantos.' serviços contratados no financeiro da Golden.',
    };
    $ativos = collect($itens)->filter(fn (array $s): bool => mb_strtolower((string) ($s['status'] ?? '')) === 'active');
    $ativosCount = $ativos->count();
    $recorrenciaCents = (int) $ativos->sum('valor_cents');
    $proximoVencimento = $ativos->pluck('proximo_vencimento')->filter()->sort()->first()
        ?? collect($itens)->pluck('proximo_vencimento')->filter()->sort()->first();
@endphp
@section('page_description', $descricaoPagina)

@section('page_actions')
<a href="{{ route('portal.faturas') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-invoice-dollar me-1"></i> Minhas faturas</a>
<a href="{{ route('portal.chamados.criar') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-headset me-1"></i> Abrir chamado</a>
<a href="{{ wa_url() }}" class="btn btn-sm btn-golden" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Falar com a equipe</a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <x-admin.stat label="Planos e serviços" :value="$situacao === 'ok' ? $quantos : '—'" icon="fa-satellite-dish" tone="gold" pequeno />
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.stat label="Ativos no monitoramento" :value="$situacao === 'ok' ? $ativosCount : '—'" icon="fa-shield-halved" tone="green" pequeno />
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.stat label="Recorrência ativa" :value="$situacao === 'ok' && $recorrenciaCents > 0 ? brl($recorrenciaCents) : '—'" icon="fa-coins" tone="blue" pequeno />
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.stat label="Próximo vencimento" :value="$proximoVencimento ? $proximoVencimento->format('d/m/Y') : '—'" icon="fa-calendar-check" tone="teal" :href="route('portal.faturas')" pequeno />
    </div>
</div>

@include('portal.partials.financeiro-situacao')

@if($situacao === 'ok')
    <x-admin.table title="Planos e rastreadores contratados" icon="fa-satellite-dish" :headers="$colunas"
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
            <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary text-nowrap" href="{{ route('portal.servicos.show', $servico['id']) }}"
                   aria-label="Ver os detalhes de {{ $servico['nome'] }}"><i class="fas fa-circle-info me-1" aria-hidden="true"></i>Detalhes</a>
            </td>
            @if($abreNoFinanceiro)
            <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary" href="{{ $servico['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="Abrir {{ $servico['nome'] }} no financeiro"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
            </td>
            @endif
        </tr>
        @endforeach
    </x-admin.table>
@endif

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <x-admin.card title="Trocar de plano ou acrescentar um rastreador" icon="fa-satellite-dish" class="h-100">
            <p class="small text-secondary mb-3">Plano, veículo e ponto de instalação mudam com a equipe — ela ajusta o financeiro e o rastreamento no mesmo lugar, sem você precisar abrir dois atendimentos.</p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-golden" href="{{ wa_url() }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Falar no WhatsApp</a>
                @if(loja_ativa())
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('site.loja.index') }}">Ver a loja</a>
                @endif
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.faturas') }}">Minhas faturas</a>
            </div>
        </x-admin.card>
    </div>
    <div class="col-lg-6">
        <x-admin.card title="Segunda via, cadastro e suporte técnico" icon="fa-headset" class="h-100">
            <p class="small text-secondary mb-3">Precisa baixar a 2ª via em PDF, conferir o e-mail vinculado ao financeiro ou solicitar manutenção no equipamento? Acesse os atalhos diretos abaixo.</p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.dados') }}"><i class="fas fa-id-card me-1"></i> Conferir meus dados</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.chamados.criar') }}"><i class="fas fa-plus me-1"></i> Abrir chamado</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.pedidos') }}"><i class="fas fa-box-open me-1"></i> Meus pedidos</a>
            </div>
        </x-admin.card>
    </div>
</div>
@endsection
