@extends('portal.layout')
@php use App\Services\Whmcs\Cliente as Financeiro; use App\Services\Whmcs\Veiculacao; @endphp

@section('title', 'Minhas faturas — Portal do cliente')
@section('page_title', 'Minhas faturas')
@php
    $quantas = count($itens);
    $abreNoFinanceiro = Veiculacao::mostraLink();
    $emAbertoCents = collect($itens)
        ->filter(fn (array $fatura): bool => Veiculacao::faturaEmAberto($fatura))
        ->sum('saldo_cents');
    $descricaoPagina = match (true) {
        $situacao !== 'ok' => 'As faturas e mensalidades da sua conta, direto do financeiro da Golden.',
        $quantas === 0 => 'Nenhuma fatura emitida no financeiro desta conta ainda.',
        $quantas === 1 => '1 fatura no financeiro da Golden.',
        default => $quantas.' faturas no financeiro da Golden.',
    };
@endphp
@section('page_description', $descricaoPagina)

@section('page_actions')
<a href="{{ route('portal.servicos') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-satellite-dish me-1"></i> Meus serviços</a>
@endsection

@section('content')
@include('portal.partials.financeiro-situacao')

@if($situacao === 'ok' && $emAbertoCents > 0)
<div class="alert alert-warning shadow-sm d-flex gap-3">
    <i class="fas fa-file-invoice-dollar fs-5 mt-1" aria-hidden="true"></i>
    <div class="small">
        <strong class="d-block">{{ brl($emAbertoCents) }} em aberto.</strong>
        @if($abreNoFinanceiro)
            Abra a fatura no financeiro para pagar ou pegar a segunda via. O rastreamento continua funcionando enquanto a mensalidade estiver em dia.
        @else
            Acerte o pagamento com a equipe pelo atendimento — ela envia o Pix ou a segunda via e confirma por aqui. O rastreamento continua funcionando enquanto a mensalidade estiver em dia.
        @endif
    </div>
</div>
@endif

@if($situacao === 'ok')
    <x-admin.table :headers="['Fatura', 'center:Vencimento', 'center:Situação', 'end:Total', 'end:Pagamento']"
        empty="Nenhuma fatura emitida no financeiro desta conta ainda. A primeira aparece aqui assim que a equipe registrar a contratação.">
        @foreach($itens as $fatura)
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
            <td class="text-center small text-nowrap">{{ $fatura['vencimento'] ? $fatura['vencimento']->format('d/m/Y') : '—' }}</td>
            <td class="text-center"><x-admin.status :rotulo="Financeiro::rotuloDaFatura($fatura['status'])" :tom="Financeiro::tomDaFatura($fatura['status'])" /></td>
            <td class="text-end">
                <span class="small text-nowrap d-block">{{ brl($fatura['total_cents']) }}</span>
                @if($fatura['saldo_cents'] > 0)
                    <span class="small fw-semibold text-danger text-nowrap">{{ brl($fatura['saldo_cents']) }} em aberto</span>
                @endif
            </td>
            <td class="text-end">
                @if($fatura['url'] !== '')
                    <a class="btn btn-sm text-nowrap {{ $fatura['saldo_cents'] > 0 ? 'btn-golden' : 'btn-outline-secondary' }}" href="{{ $fatura['url'] }}" target="_blank" rel="noopener noreferrer">
                        {{ $fatura['saldo_cents'] > 0 ? 'Pagar' : 'Abrir' }}
                    </a>
                @else
                    <span class="small text-secondary">Pelo atendimento</span>
                @endif
            </td>
        </tr>
        @endforeach
    </x-admin.table>
@endif

<x-admin.card title="Precisa de ajuda com uma cobrança?" icon="fa-headset" class="mt-3">
    <p class="small text-secondary mb-2">Segunda via, Pix, boleto, data de vencimento ou uma cobrança que não reconhece: a equipe confere no financeiro e responde por aqui mesmo.</p>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-golden" href="{{ wa_url() }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Falar no WhatsApp</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.chamados.criar') }}">Abrir um chamado</a>
    </div>
</x-admin.card>
@endsection
