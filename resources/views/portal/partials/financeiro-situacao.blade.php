{{--
    O recado de uma lista que não veio. Nenhuma destas situações pode ser lida como "você não
    tem nada": sem credencial, sem conta localizada no financeiro ou financeiro fora do ar.
    Cada uma diz o que houve e deixa um caminho para a pessoa não ficar presa na tela.
--}}
@if($situacao === 'sem_configuracao')
<div class="alert alert-secondary shadow-sm d-flex gap-3">
    <i class="fas fa-plug-circle-xmark fs-5 mt-1" aria-hidden="true"></i>
    <div class="small">
        <strong class="d-block">Área financeira em preparação.</strong>
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
        <strong class="d-block">Ainda não achei sua conta no financeiro.</strong>
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
        <strong class="d-block">O financeiro não respondeu agora.</strong>
        Não é falta de nada na sua conta: a consulta ao sistema financeiro falhou neste momento. Tente de novo em instantes — se continuar, a equipe verifica por dentro.
        <div class="mt-2 d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="{{ url()->current() }}"><i class="fas fa-rotate me-1"></i> Tentar de novo</a>
            <a class="btn btn-sm btn-golden" href="{{ wa_url() }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp me-1"></i> Avisar a equipe</a>
        </div>
    </div>
</div>
@endif
