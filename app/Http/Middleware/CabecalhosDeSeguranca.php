<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * O carimbo de segurança que toda resposta do site e do painel leva.
 *
 * Antes desta classe o sistema não mandava um único cabeçalho de proteção. Isso não é detalhe de
 * auditoria: sem `X-Frame-Options`/`frame-ancestors` qualquer página de terceiros embute o painel
 * num quadro transparente e faz o administrador clicar por ela (clickjacking); sem `nosniff` um
 * arquivo enviado por um cliente é lido como script se o navegador quiser; sem `Referrer-Policy`
 * o endereço completo de uma ficha de cliente — com o Id — vaza para o Google e para a rede social
 * no próximo clique; e sem CSP um trecho de código colado em campo de texto, ou uma falha que
 * ainda não sabemos, passa a ler a sessão do painel no navegador de quem está logado.
 *
 * A Content-Security-Policy é desenhada para o que este sistema realmente usa, não para o mínimo
 * teórico: os scripts da casa, os três CDNs que carregam o tema, o Google Tag/Facebook Pixel que
 * o funil deixa o administrador colar, e o YouTube que toca dentro das páginas. Ela é Reporting
 * Only? Não — está ativa. O que ela **não** faz é proibir script em linha: o tema tem blocos
 * `<script>` embutidos em dezenove views, e travá-los hoje quebraria o site inteiro. O caminho para
 * lá é extrair esses blocos para arquivos, não afrouxar o resto.
 */
class CabecalhosDeSeguranca
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ($this->cabecalhos($request) as $nome => $valor) {
            $response->headers->set($nome, $valor);
        }

        return $response;
    }

    /** @return array<string, string> */
    private function cabecalhos(Request $request): array
    {
        $origens = $this->origens();

        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Content-Security-Policy' => implode('; ', array_filter([
                "default-src 'self'",
                // `'unsafe-inline'` é o que ainda falta: o tema escreve `<script>` dentro das views.
                "script-src 'self' 'unsafe-inline' {$origens['script']}",
                "style-src 'self' 'unsafe-inline' {$origens['estilo']}",
                "font-src 'self' data: {$origens['fonte']}",
                // `data:` cobre avatar em linha-base64; `i.ytimg.com`, a capa do vídeo do YouTube.
                "img-src 'self' data: blob: {$origens['imagem']}",
                "connect-src 'self' {$origens['conexao']}",
                "frame-src 'self' {$origens['quadro']}",
                // Escrever em outra casa está fora de cogitação: todo formulário aponta para cá.
                "form-action 'self'",
                "base-uri 'self'",
                'object-src \'none\'',
                'frame-ancestors \'self\'',
                $request->secure() ? 'upgrade-insecure-requests' : null,
            ])),
            // Só em conexão cifrada: HSTS em http seria um recado que o navegador joga fora.
            ...($request->secure()
                ? ['Strict-Transport-Security' => 'max-age=31536000; includeSubDomains']
                : []),
        ];
    }

    /**
     * As casas externas que o sistema carrega, numa lista só.
     *
     * É aqui, e em nenhum outro lugar, que se decide o que a política aceita. Cada linha tem um
     * motivo concreto: sem o CDN o painel não tem o tema; sem o Tag e o Pixel as páginas de funil
     * perdem o rastreamento que justifica o investimento em anúncio; sem o YouTube o vídeo do
     * tutorial para de tocar.
     *
     * @return array<string, string>
     */
    private function origens(): array
    {
        $cdn = 'https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com';
        $tags = 'https://www.googletagmanager.com https://www.google-analytics.com '
            .'https://analytics.tiktok.com https://connect.facebook.net https://www.facebook.com';
        $google = 'https://fonts.googleapis.com https://fonts.gstatic.com https://ssl.google.com';

        return [
            'script' => $cdn.' '.$tags.' '.$google.' https://www.youtube.com https://www.youtube-nocookie.com',
            'estilo' => $cdn.' '.$google,
            'fonte' => $google.' https://cdn.jsdelivr.net',
            'imagem' => 'https://i.ytimg.com https://img.youtube.com',
            'conexao' => $tags.' https://region1.google-analytics.com',
            'quadro' => 'https://www.youtube.com https://www.youtube-nocookie.com https://connect.facebook.net',
        ];
    }
}
