{{--
    Favicon do produto, pelo CMS: as mesmas tags e a mesma imagem no site público,
    no painel e na tela de acesso. O arquivo enviado em Admin → Conteúdo → Imagens
    do site (slot 'favicon') entra por cima do que vem no pacote; enquanto não há
    envio, o .ico da raiz também é anunciado, para quem o pede direto no navegador.
--}}
@php $icone = site_img('favicon'); @endphp
@if($icone)
    <link rel="icon" href="{{ $icone }}">
    <link rel="apple-touch-icon" href="{{ $icone }}">
@endif
@if(! site_img_enviada('favicon'))
    <link rel="icon" href="{{ asset('favicon.ico') }}">
@endif
