{{--
    Formulário de captura de lead — o único do site. A página de contato e o bloco de
    formulário do funil chamam esta mesma parcial; o que muda entre elas são três
    detalhes opcionais: o texto do botão ($cta_texto), a linha de confiança abaixo dele
    ($confianca) e a página de campanha que está pedindo o cadastro ($pagina_funil).
    Campos ocultos recebem UTMs e identificadores de clique via JS.

    Nome e mensagem aceitam vir da URL (`?name=&message=`) porque a bolha do assistente, numa página
    sem formulário, leva o visitante ao /contato resumindo a conversa — é este mesmo campo que
    recebe o resumo, e ninguém reescreve o que já contou.
--}}
@php
    $kits = $kits ?? config('golden.kits');
    $perfis = $perfis ?? config('golden.perfis');
@endphp
<form class="form-card" data-lead-form action="{{ route('site.leads.store') }}" method="POST" novalidate>
    @csrf

    <div class="field">
        <label for="leadName">Seu nome</label>
        <input id="leadName" name="name" type="text" maxlength="90" required placeholder="Como podemos chamar você?" value="{{ request('name') }}">
    </div>
    <div class="row-2">
        <div class="field">
            <label for="leadPhone">WhatsApp com DDD</label>
            <input id="leadPhone" name="phone" type="tel" inputmode="tel" required placeholder="(00) 00000-0000">
        </div>
        <div class="field">
            <label for="leadEmail">E-mail <span class="fine">(opcional)</span></label>
            <input id="leadEmail" name="email" type="email" maxlength="160" placeholder="voce@email.com">
        </div>
    </div>
    <div class="row-2">
        <div class="field">
            <label for="leadFleet">Veículos</label>
            <select id="leadFleet" name="fleet_size">
                <option value="1">1 veículo</option>
                <option value="5" selected>5 veículos</option>
                <option value="10">10 veículos</option>
                <option value="10+">Mais de 10</option>
            </select>
        </div>
        <div class="field">
            <label for="leadProfile">Perfil</label>
            <select id="leadProfile" name="profile">
                @foreach($perfis as $valor => $rotulo)
                    <option value="{{ $valor }}">{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="field">
        <label for="leadKit">Kit de interesse</label>
        <select id="leadKit" name="kit">
            <option value="">Ainda não sei</option>
            @foreach($kits as $kit)
                <option value="{{ $kit['titulo'] }}" @selected(request('kit') === $kit['titulo'])>{{ $kit['eyebrow'] }} — {{ $kit['titulo'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="leadMessage">Mensagem <span class="fine">(opcional)</span></label>
        <textarea id="leadMessage" name="message" rows="3" maxlength="1000" placeholder="Conte um pouco sobre a sua operação.">{{ request('message') }}</textarea>
    </div>

    {{-- Honeypot anti-spam --}}
    <div class="hp" aria-hidden="true">
        <label>Não preencha <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        <label>Não preencha <input type="text" name="company_url" tabindex="-1" autocomplete="off"></label>
    </div>

    {{-- Atribuição de campanha (preenchido via JS): as UTMs que vieram na URL de chegada,
         as duas colunas próprias de clique e um par genérico para as outras redes — o
         nome diz de quem é o clique (ttclid do TikTok, msclkid do Bing, gbraid do Google
         quando o app não usa o gclid...) e o valor é a prova dele. A lista dos nomes que
         o navegador guarda vem de config('golden.ids_clique'), a mesma régua do Atribuidor. --}}
    @foreach(['utm_source','utm_medium','utm_campaign','utm_content','utm_term','gclid','fbclid'] as $campo)
        <input type="hidden" name="{{ $campo }}" value="">
    @endforeach
    <input type="hidden" name="clique_id" value="">
    <input type="hidden" name="clique_valor" value="">

    @if(! empty($pagina_funil))
    <input type="hidden" name="funnel_page_id" value="{{ (int) $pagina_funil }}">
    @endif

    <label class="consent">
        <input type="checkbox" name="consent" value="1" required>
        <span>Autorizo o contato da Golden sobre esta solicitação e declaro ter lido a <a href="{{ route('site.privacidade') }}" target="_blank" style="text-decoration:underline">Política de Privacidade</a>.</span>
    </label>

    <button type="submit" class="btn btn-gold btn-block" data-track="contact" data-cta="form">{{ $cta_texto ?? 'Solicitar minha proposta' }} →</button>
    <p class="fine" style="text-align:center;margin-top:12px">{{ $confianca ?? 'Resposta rápida em horário comercial. Seus dados não são compartilhados.' }}</p>
</form>
