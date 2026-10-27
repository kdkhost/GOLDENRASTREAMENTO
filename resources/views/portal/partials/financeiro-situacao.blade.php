{{--
    O recado de uma lista que não veio. Nenhuma destas situações pode ser lida como "você não
    tem nada": área fechada pela empresa, sem credencial, sem conta localizada no financeiro ou
    financeiro fora do ar. Cada uma diz o que houve e deixa um caminho para a pessoa não ficar
    presa na tela. O título vem do mapa abaixo — uma fonte só para a frase que o teste confere.
--}}
@php
    $titulos = [
        'desligado' => 'Esta área do portal está fechada.',
        'sem_configuracao' => 'Área financeira em preparação.',
        'sem_conta' => 'Ainda não achei sua conta no financeiro.',
        'fora_do_ar' => 'O financeiro não respondeu agora.',
    ];
@endphp
@if($situacao === 'desligado')
<div class="alert alert-secondary shadow-sm d-flex gap-3">
    <i class="fas fa-lock fs-5 mt-1" aria-hidden="true"></i>
    <div class="small">
        <strong class="d-block">{{ $titulos['desligado'] }}</strong>
        A Golden mantém a consulta direta ao financeiro fechada nesta área do portal por enquanto. Nada mudou na sua conta: é a tela que está desligada, e a equipe continua atendendo pelo canal de sempre.
        <div class="mt-2 d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-golden" href="{{ wa_url() }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Falar no WhatsApp</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.inicio') }}">Voltar ao início</a>
        </div>
    </div>
</div>
@elseif($situacao === 'sem_configuracao')
<div class="alert alert-secondary shadow-sm d-flex gap-3">
    <i class="fas fa-plug-circle-xmark fs-5 mt-1" aria-hidden="true"></i>
    <div class="small">
        <strong class="d-block">{{ $titulos['sem_configuracao'] }}</strong>
        A leitura de serviços e faturas direto do financeiro está sendo ligada nesta conta. Enquanto isso, a equipe resolve tudo com você pelo atendimento.
        <div class="mt-2 d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-golden" href="{{ wa_url() }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Falar no WhatsApp</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('site.contato') }}">Canais de atendimento</a>
        </div>
    </div>
</div>
@elseif($situacao === 'sem_conta')
<div class="alert alert-warning shadow-sm d-flex gap-3">
    <i class="fas fa-magnifying-glass fs-5 mt-1" aria-hidden="true"></i>
    <div class="small">
        <strong class="d-block">{{ $titulos['sem_conta'] }}</strong>
        O e-mail de acesso deste portal pode ser diferente do cadastro no financeiro da Golden. A equipe acerta isso em minutos e, a partir daí, esta tela passa a mostrar seus serviços e faturas.
        <div class="mt-2 d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-golden" href="{{ wa_url() }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Falar com a equipe</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.dados') }}">Conferir meus dados</a>
        </div>
    </div>
</div>
@elseif($situacao === 'fora_do_ar')
<div class="alert alert-warning shadow-sm d-flex gap-3">
    <i class="fas fa-triangle-exclamation fs-5 mt-1" aria-hidden="true"></i>
    <div class="small">
        <strong class="d-block">{{ $titulos['fora_do_ar'] }}</strong>
        Não é falta de nada na sua conta: a consulta ao sistema financeiro falhou neste momento. Tente de novo em instantes — se continuar, a equipe verifica por dentro.
        <div class="mt-2 d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="{{ url()->current() }}"><i class="fas fa-rotate me-1"></i> Tentar de novo</a>
            <a class="btn btn-sm btn-golden" href="{{ wa_url() }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Avisar a equipe</a>
        </div>
    </div>
</div>
@endif
