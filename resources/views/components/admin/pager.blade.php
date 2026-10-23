@props(['paginas'])
{{-- Rodapé de paginação de qualquer lista do painel (tabela ou linha do tempo): o mesmo
     cromado do cartão de tabela, e só aparece quando há para onde ir. --}}
@if($paginas->hasPages())
    <div {{ $attributes->merge(['class' => 'card-footer bg-body-tertiary']) }}>{{ $paginas->links() }}</div>
@endif
