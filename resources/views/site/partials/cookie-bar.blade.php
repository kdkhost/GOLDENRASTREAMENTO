{{-- Aviso de privacidade do site: a mesma barra na carcaça normal e na página de funil,
     porque o consentimento é do visitante, não do layout. --}}
<div class="cookie" id="cookieBar" role="dialog" aria-live="polite">
    <p><strong>Privacidade.</strong> Usamos cookies essenciais e, com o seu consentimento, cookies de medição para entender o desempenho das campanhas, conforme a <a href="{{ route('site.privacidade') }}" style="text-decoration:underline">Política de Privacidade</a> (LGPD).</p>
    <div class="btns">
        <button class="btn btn-gold btn-sm" id="cookieAccept">Aceitar todos</button>
        <button class="btn btn-ghost btn-sm" id="cookieReject">Somente essenciais</button>
    </div>
</div>
