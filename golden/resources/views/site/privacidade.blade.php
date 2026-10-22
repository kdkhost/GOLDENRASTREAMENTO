@extends('layouts.site')
@section('title', 'Política de Privacidade — Golden Rastreamento')
@section('meta_description', 'Política de Privacidade da Golden Rastreamento conforme a LGPD: coleta de dados, finalidades, bases legais, cookies e direitos do titular.')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <div class="breadcrumb"><a href="{{ route('site.home') }}">Início</a> · Privacidade</div>
        <h1>Política de Privacidade</h1>
        <p>Compromisso com a proteção dos seus dados pessoais, conforme a LGPD (Lei nº 13.709/2018).</p>
    </div>
</section>

<section class="sec-light">
    <div class="wrap" style="max-width:860px">
        <div class="card" style="padding:36px">
            <h3>1. Dados que coletamos</h3>
            <p>Quando você solicita uma proposta, coletamos: nome, WhatsApp, e-mail (opcional), perfil, quantidade de veículos e o kit de interesse. Também registramos dados técnicos de navegação — como identificador anônimo de visita, página de origem e parâmetros de campanha (UTMs, gclid/fbclid) — para atribuir o atendimento à campanha correta.</p>

            <h3 style="margin-top:26px">2. Finalidade e base legal</h3>
            <p>Os dados são usados exclusivamente para entrar em contato, elaborar a proposta comercial e medir a eficácia das campanhas. A base legal é o <strong>consentimento</strong> (art. 7º, I) e a <strong>execução de contrato/procedimentos preliminares</strong> (art. 7º, V).</p>

            <h3 style="margin-top:26px">3. Cookies e medição</h3>
            <p>Utilizamos cookies essenciais e, mediante sua escolha no banner de preferências, cookies de medição/analytics (Google Analytics 4, Google Ads e Meta Pixel). Você pode gerenciar ou revogar o consentimento a qualquer momento. Sem consentimento, os identificadores de marketing não são ativados.</p>

            <h3 style="margin-top:26px">4. Compartilhamento</h3>
            <p>Não vendemos seus dados. Eles podem ser acessados por ferramentas de atendimento e medição contratadas pela Golden, sempre dentro das finalidades acima. Endereços IP são registrados de forma pseudonimizada (hash).</p>

            <h3 style="margin-top:26px">5. Retenção e segurança</h3>
            <p>Adotamos medidas técnicas e administrativas de proteção (acesso restrito por login e trilha de auditoria). Os leads são mantidos enquanto úteis para o atendimento ou conforme obrigação legal, e podem ser excluídos a pedido.</p>

            <h3 style="margin-top:26px">6. Seus direitos (LGPD)</h3>
            <p>Você pode solicitar confirmação de tratamento, acesso, correção, anonimização, portabilidade e eliminação dos seus dados, além de revogar o consentimento.</p>

            <h3 style="margin-top:26px">7. Contato do encarregado</h3>
            <p>Dúvidas sobre esta política ou sobre o tratamento de dados: <a href="mailto:{{ $golden['email'] }}" style="color:var(--gold-2)">{{ $golden['email'] }}</a> ou pelo <a href="{{ $golden['whatsapp_url'] }}" target="_blank" rel="noopener" style="color:var(--gold-2)">WhatsApp</a>.</p>

            <p class="fine" style="margin-top:30px">Última atualização: {{ date('d/m/Y') }}. Este texto é um modelo e deve ser revisado pelo responsável jurídico da Golden antes da publicação.</p>
        </div>
    </div>
</section>
@endsection
