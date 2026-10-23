<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Decide DE ONDE VEIO cada visita, clique e cadastro do site.
 *
 * Lê os sinais que a própria requisição traz — UTMs, o identificador de clique que a
 * plataforma de anúncio cola na URL, o referer e o user-agent — e devolve um canal
 * (anúncio pago, rede social, busca orgânica, busca por IA, indicação, e-mail, site
 * parceiro, direto) com a rede por trás dele. As listas que mandam nesta régua estão
 * em config/golden.php; nada de regra de origem escrita solta em controller.
 *
 * A ordem importa: o identificador de clique vence o utm_medium, que vence o utm_source,
 * que vence o referer. Um link de anúncio costuma carregar os três, e a decisão fica
 * com o sinal que tem a prova mais forte.
 */
class Atribuidor
{
    /**
     * @return array{canal: string, rede: ?string, pago: bool, motivo: string}
     */
    public static function classificar(
        ?string $utmSource,
        ?string $utmMedium,
        ?string $origemHost,
        ?string $cliqueId = null,
        ?string $userAgent = null,
    ): array {
        $meio = self::slug((string) $utmMedium);
        $fonte = self::slug((string) $utmSource);
        $host = self::slug((string) $origemHost);

        // 1. Robô, antes de tudo: indexador não é gente, e a IA buscando a pedido de um
        //    humano não é indexador — os dois precisam de destinos diferentes.
        //    UA null = ninguém olhou o cabeçalho; UA vazio = o visitante não enviou um,
        //    que é exatamente o comportamento de um script. Só o segundo é tratado como robô.
        if ($userAgent !== null && ($robô = self::robo($userAgent))) {
            if ($robô === 'ia_usuario') {
                return self::resultado(
                    'busca_ia',
                    self::redeDeUserAgent($userAgent) ?? self::redePorNome($fonte) ?? self::redePorNome($host),
                    'user-agent de IA'
                );
            }

            return self::resultado('indexador', null, 'user-agent de indexador');
        }

        // 2. Identificador de clique (gclid, fbclid, ttclid, msclkid...): só existe quando
        //    a plataforma de anúncio entregou o clique. É a prova mais forte de tráfego pago.
        if ($cliqueId) {
            $rede = config("golden.ids_clique.{$cliqueId}")
                ?? self::redePorNome($fonte)
                ?? self::redePorNome($host);

            return self::resultado('anuncio', $rede, "clique:{$cliqueId}", true);
        }

        // 3. utm_medium — é o que o gestor de tráfego escreveu no link.
        if ($meio !== '') {
            if (array_key_exists($meio, config('golden.meios_pagos'))) {
                $rede = self::redePorNome($fonte)
                    ?? self::redePorNome($host)
                    ?? config('golden.meios_pagos.'.$meio)
                    ?: ($fonte ?: null);

                return self::resultado('anuncio', $rede, 'utm_medium pago', true);
            }

            if (array_key_exists($meio, config('golden.meios_sociais'))) {
                $rede = self::redePorNome($fonte) ?? self::redePorNome($host)
                    ?? config('golden.meios_sociais.'.$meio) ?: ($fonte ?: null);

                return self::resultado('rede_social', $rede, 'utm_medium social');
            }

            if (in_array($meio, config('golden.meios_ia'), true)) {
                return self::resultado('busca_ia', self::redePorNome($fonte) ?? ($fonte ?: null), 'utm_medium de IA');
            }

            if (in_array($meio, config('golden.meios_busca'), true)) {
                return self::resultado('busca_organica', self::redePorNome($fonte) ?? self::redePorNome($host) ?? 'google', 'utm_medium orgânico');
            }

            if (in_array($meio, config('golden.meios_email'), true)) {
                return self::resultado('email', self::redePorNome($fonte), 'utm_medium de e-mail');
            }

            if (in_array($meio, config('golden.meios_indicacao'), true)) {
                return self::resultado('indicacao', self::redePorNome($fonte) ?? self::redePorNome($host) ?? ($fonte ?: null), 'utm_medium de indicação');
            }
        }

        // 4. utm_source sem utm_medium: alguém marcou o link de propósito, só não disse
        //    se pagou por ele. A rede conhecido define o canal; o restante é indicação externa.
        if ($fonte !== '') {
            $rede = self::redePorNome($fonte);

            if ($rede && ($canal = self::canalDaRede($rede))) {
                return self::resultado($canal, $rede, 'utm_source');
            }

            if (preg_match('/(ads|adwords|ad-|camp|boost)/', $fonte)) {
                return self::resultado('anuncio', $rede ?? $fonte, 'utm_source de anúncio', true);
            }

            return self::resultado('parceiro', $fonte, 'utm_source');
        }

        // 5. Referer cru — o caso mais comum do tráfego gratuito: a pessoa veio de algum
        //    lugar sem nenhuma marcação.
        if ($host === '') {
            return self::resultado('direto', null, 'sem referer');
        }

        $rede = self::redePorNome($host);

        if ($rede === null) {
            return self::resultado('parceiro', $host, 'referer externo');
        }

        return self::resultado(self::canalDaRede($rede) ?? 'parceiro', $rede, 'referer');
    }

    /**
     * A rede que mora num host de referer ou num utm_source. Null em desconhecido.
     * IA primeiro, buscador depois, rede social por último: "chatgpt.com" e "x.com"
     * têm trechos em comum com outros dicionários e o mais específico decide.
     */
    public static function redePorNome(?string $valor): ?string
    {
        $valor = self::slug((string) $valor);
        if ($valor === '') {
            return null;
        }

        $dicionarios = [config('golden.ia_humanos'), config('golden.buscadores'), config('golden.redes_sociais')];

        foreach ($dicionarios as $dicionario) {
            foreach ($dicionario as $trecho => $rede) {
                if (self::bate($valor, (string) $trecho)) {
                    return $rede;
                }
            }
        }

        return null;
    }

    /** De qual grupo de redes esta rede faz parte — é o que separa orgânico de anúncio. */
    public static function canalDaRede(string $rede): ?string
    {
        return match (true) {
            self::pertence($rede, config('golden.ia_humanos')) => 'busca_ia',
            self::pertence($rede, config('golden.buscadores')) => 'busca_organica',
            self::pertence($rede, config('golden.redes_sociais')) => 'rede_social',
            default => null,
        };
    }

    /** @return array{dispositivo: string, navegador: string, sistema: string} */
    public static function dispositivo(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        if ($ua === '' || self::robo($ua) === 'indexador') {
            return ['dispositivo' => $ua === '' ? 'desconhecido' : 'robô', 'navegador' => 'desconhecido', 'sistema' => 'desconhecido'];
        }

        $sistema = match (true) {
            str_contains($ua, 'Windows') => 'windows',
            str_contains($ua, 'Android') => 'android',
            (bool) preg_match('/iPhone|iPad|iPod|CPU (iPhone )?OS/', $ua) => 'ios',
            str_contains($ua, 'CrOS') => 'chromeos',
            str_contains($ua, 'Mac OS X') => 'macos',
            str_contains($ua, 'Linux') => 'linux',
            default => 'desconhecido',
        };

        // O navegador embutido do app social prova que o clique aconteceu DENTRO da rede —
        // é o que salva a atribuição quando o aplicativo corta o referer.
        $navegador = self::navegadorDeApp($ua) ?? (match (true) {
            (bool) preg_match('/GSA\/|googleapp/', $ua) => 'google',
            str_contains($ua, 'SamsungBrowser') => 'samsung-internet',
            (bool) preg_match('/Edg(AIOS|e)?\//', $ua) => 'edge',
            (bool) preg_match('/OPR\/|Opera/', $ua) => 'opera',
            (bool) preg_match('/FxiOS|Firefox/', $ua) => 'firefox',
            (bool) preg_match('/CriOS|Chrome/', $ua) => 'chrome',
            str_contains($ua, 'Safari') => 'safari',
            default => 'desconhecido',
        });

        $dispositivo = match (true) {
            (bool) preg_match('/iPad|Tablet/', $ua) && ! str_contains($ua, 'Mobile') => 'tablet',
            (bool) preg_match('/Mobile|Android|iPhone|iPod|Mobi/', $ua) => 'celular',
            in_array($sistema, ['windows', 'macos', 'chromeos', 'linux'], true) => 'computador',
            default => 'desconhecido',
        };

        return ['dispositivo' => $dispositivo, 'navegador' => $navegador, 'sistema' => $sistema];
    }

    /**
     * O navegador embutido de um app social ou de mensagens, quando o clique aconteceu
     * dentro dele. Null em navegador comum. É também a prova de que o visitante é gente:
     * o robô de preview do mesmo app ('WhatsApp/2.23.2.1 A') se anuncia sem Mozilla.
     */
    public static function navegadorDeApp(?string $ua): ?string
    {
        $ua = (string) $ua;

        return match (true) {
            str_contains($ua, 'Instagram') => 'instagram',
            (bool) preg_match('/FB_IAB|FBAV|FBAN/', $ua) => 'facebook',
            str_contains($ua, 'WhatsApp') => 'whatsapp',
            (bool) preg_match('/BytedanceWebview|TikTok|Musically/', $ua) => 'tiktok',
            str_contains($ua, 'LinkedInApp') => 'linkedin',
            (bool) preg_match('/Twitter for|TwitterAndroid/', $ua) => 'x',
            str_contains($ua, 'Snapchat') => 'snapchat',
            str_contains($ua, 'Telegram/') => 'telegram',
            default => null,
        };
    }

    /**
     * 'indexador' = varredura automática, fica fora dos KPIs.
     * 'ia_usuario' = a IA abrindo o link que uma pessoa pediu: conta como busca por IA.
     * Null = gente.
     */
    public static function robo(?string $userAgent): ?string
    {
        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return 'indexador';
        }

        foreach (config('golden.ia_usuarios') as $assinatura) {
            if (stripos($ua, (string) $assinatura) !== false) {
                return 'ia_usuario';
            }
        }

        // 'WhatsApp' dá azar de nomear duas coisas: o navegador embutido, onde a pessoa
        // clicou, e o robô que só busca a prévia do link. O navegador sempre anuncia um
        // motor de verdade antes; perder um clique humano custaria mais que tolerar uma
        // prévia a mais, então o humano decide primeiro.
        if (self::navegadorDeApp($ua) !== null && str_contains(strtolower($ua), 'mozilla')) {
            return null;
        }

        foreach (config('golden.indexadores') as $assinatura) {
            if (stripos($ua, (string) $assinatura) !== false) {
                return 'indexador';
            }
        }

        return null;
    }

    /** O parâmetro de clique de anúncio presente na URL, quando houver: ['gclid', 'abc123']. */
    public static function clique(array $query): ?array
    {
        foreach (config('golden.ids_clique') as $parametro => $rede) {
            $valor = $query[$parametro] ?? null;
            if (is_string($valor) && $valor !== '') {
                return [$parametro, Str::limit($valor, 120, '')];
            }
        }

        return null;
    }

    /**
     * Os sinais brutos de uma requisição, prontos para classificar(). O referer do
     * próprio site é descartado: navegação interna não é origem de nada.
     *
     * @return array<string, ?string>
     */
    public static function sinais(Request $request): array
    {
        $clique = self::clique($request->query());
        $referer = $request->header('referer');
        $host = self::hostDe($referer);

        return [
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'utm_content' => $request->query('utm_content'),
            'utm_term' => $request->query('utm_term'),
            'clique_id' => $clique[0] ?? null,
            'clique_valor' => $clique[1] ?? null,
            'referer' => $referer ? Str::limit($referer, 255, '') : null,
            'origem_host' => self::eCasa($host, $request) ? null : ($host ? Str::limit($host, 120, '') : null),
            'origem_path' => self::eCasa($host, $request) ? null : self::caminhoDe($referer),
            'user_agent' => (string) $request->userAgent(),
        ];
    }

    /** Host minúsculo, sem www, de um referer completo. */
    public static function hostDe(?string $referer): ?string
    {
        $referer = trim((string) $referer);
        if ($referer === '') {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        return $host ? self::slug((string) $host) : null;
    }

    /** O caminho que o referer apontava — a resposta para "de onde exatamente veio". */
    public static function caminhoDe(?string $referer): ?string
    {
        $caminho = $referer ? parse_url($referer, PHP_URL_PATH) : null;

        return $caminho ? Str::limit((string) $caminho, 190, '') : null;
    }

    /** O host é a nossa própria casa (ou não existe)? então não é origem. */
    public static function eCasa(?string $host, Request $request): bool
    {
        return $host === null || $host === self::slug($request->getHost());
    }

    public static function rotuloCanal(?string $canal): string
    {
        return config('golden.canais.'.$canal.'.rotulo') ?? 'Não identificado';
    }

    /** @return array<string, string> o canal e seu rótulo, na ordem do config */
    public static function canaisParaFiltro(): array
    {
        return collect(config('golden.canais'))->map(fn (array $canal): string => $canal['rotulo'])->all();
    }

    public static function iconeCanal(?string $canal): string
    {
        return config('golden.canais.'.$canal.'.icone') ?? 'fa-circle-question';
    }

    public static function rotuloRede(?string $rede): string
    {
        if (! $rede) {
            return '—';
        }

        return config('golden.redes_rotulos.'.$rede) ?? ucfirst(str_replace('_', ' ', $rede));
    }

    /** Visita que entra nos KPIs (indexador fica de fora de todo número do painel). */
    public static function eValida(?string $canal): bool
    {
        return (bool) config("golden.canais.{$canal}.valido");
    }

    public static function ePago(?string $canal): bool
    {
        return (bool) config("golden.canais.{$canal}.pago");
    }

    /** @return array{canal: string, rede: ?string, pago: bool, motivo: string} */
    private static function resultado(string $canal, ?string $rede, string $motivo, ?bool $pago = null): array
    {
        // O classificador aceita o utm_source cru como rede quando não há nome conhecido
        // ('rede' é varchar(40) no MySQL, e um URL de parceiro colado no utm_source passa
        // por aqui). SQLite corta e ri; MySQL devolve 'Data too long' no meio do tráfego.
        return [
            'canal' => $canal,
            'rede' => $rede ? Str::limit($rede, 40, '') : null,
            'pago' => $pago ?? self::ePago($canal),
            'motivo' => Str::limit($motivo, 40, ''),
        ];
    }

    private static function redeDeUserAgent(?string $ua): ?string
    {
        $ua = strtolower((string) $ua);

        return match (true) {
            str_contains($ua, 'chatgpt') => 'chatgpt',
            str_contains($ua, 'claude') => 'claude',
            str_contains($ua, 'perplexity') => 'perplexity',
            str_contains($ua, 'gemini') => 'gemini',
            str_contains($ua, 'copilot') => 'copilot',
            default => null,
        };
    }

    /** A rede pertence aos valores deste dicionário de config? */
    private static function pertence(string $rede, array $dicionario): bool
    {
        return in_array($rede, $dicionario, true);
    }

    /**
     * Casa o nome da rede com o trecho do dicionário. Trecho com ponto ("x.com", "wa.me")
     * precisa bater como host inteiro ou sufixo — senão "max.com" viraria X.
     */
    private static function bate(string $valor, string $trecho): bool
    {
        if ($trecho === '' || ! str_contains($trecho, '.')) {
            return $trecho !== '' && str_contains($valor, $trecho);
        }

        return $valor === $trecho || str_ends_with($valor, '.'.$trecho) || str_contains($valor, $trecho.'.');
    }

    /** Minúsculo, sem www, sem espaços — o formato canônico de rede/host no banco. */
    private static function slug(string $valor): string
    {
        return trim(preg_replace('/^www\./', '', strtolower(trim($valor))) ?? '');
    }
}
