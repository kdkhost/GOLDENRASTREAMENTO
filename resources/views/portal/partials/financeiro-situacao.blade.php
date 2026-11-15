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
<div class="card stat-card shadow-sm mb-3 portal-situacao-card portal-situacao-neutra">
    <div class="card-body d-flex align-items-start gap-3">
        <div class="icon tone-neutral"><i class="fas fa-lock" aria-hidden="true"></i></div>
        <div class="small flex-grow-1">
            <strong class="d-block text-body mb-1 fs-6">{{ $titulos['desligado'] }}</strong>
            <p class="text-secondary mb-2">A Golden mantém a consulta direta ao financeiro fechada nesta área do portal por enquanto. Nada mudou na sua conta: é a tela que está desligada, e a equipe continua atendendo pelo canal de sempre.</p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-golden" href="{{ wa_url() }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Falar no WhatsApp</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.inicio') }}">Voltar ao início</a>
            </div>
        </div>
    </div>
</div>
@elseif($situacao === 'sem_configuracao')
<div class="card stat-card shadow-sm mb-3 portal-situacao-card portal-situacao-neutra">
    <div class="card-body d-flex align-items-start gap-3">
        <div class="icon tone-neutral"><i class="fas fa-plug-circle-xmark" aria-hidden="true"></i></div>
        <div class="small flex-grow-1">
            <strong class="d-block text-body mb-1 fs-6">{{ $titulos['sem_configuracao'] }}</strong>
            <p class="text-secondary mb-2">A leitura de serviços e faturas direto do financeiro está sendo ligada nesta conta. Enquanto isso, a equipe resolve tudo com você pelo atendimento.</p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-golden" href="{{ wa_url() }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Falar no WhatsApp</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('site.contato') }}">Canais de atendimento</a>
            </div>
        </div>
    </div>
</div>
@elseif($situacao === 'sem_conta')
<div class="card stat-card shadow-sm mb-3 portal-situacao-card">
    <div class="card-body d-flex align-items-start gap-3">
        <div class="icon tone-gold"><i class="fas fa-magnifying-glass" aria-hidden="true"></i></div>
        <div class="small flex-grow-1">
            <strong class="d-block text-body mb-1 fs-6">{{ $titulos['sem_conta'] }}</strong>
            <p class="text-secondary mb-2">O e-mail de acesso deste portal pode ser diferente do cadastro no financeiro da Golden. A equipe acerta isso em minutos e, a partir daí, esta tela passa a mostrar seus serviços e faturas.</p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-golden" href="{{ wa_url() }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Falar com a equipe</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.dados') }}">Conferir meus dados</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.chamados.criar') }}">Abrir chamado</a>
            </div>
        </div>
    </div>
</div>
@elseif($situacao === 'fora_do_ar')
<div class="card stat-card shadow-sm mb-3 portal-situacao-card portal-situacao-alerta">
    <div class="card-body d-flex align-items-start gap-3">
        <div class="icon tone-amber"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i></div>
        <div class="small flex-grow-1">
            <strong class="d-block text-body mb-1 fs-6">{{ $titulos['fora_do_ar'] }}</strong>
            <p class="text-secondary mb-2">Não é falta de nada na sua conta: a consulta ao sistema financeiro falhou neste momento. Tente de novo em instantes — se continuar, a equipe verifica por dentro.</p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="{{ url()->current() }}"><i class="fas fa-rotate me-1"></i> Tentar de novo</a>
                <a class="btn btn-sm btn-golden" href="{{ wa_url() }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Avisar a equipe</a>
            </div>
        </div>
    </div>
</div>
@endif
