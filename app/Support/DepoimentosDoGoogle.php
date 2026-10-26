<?php

namespace App\Support;

use App\Models\Testimonial;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Depoimentos do Google Business Profile.
 *
 * A única fonte oficial que devolve a foto de quem avaliou é a Business Profile API v4
 * (`accounts/{a}/locations/{l}/reviews`): o Places API só entrega avaliação para conta
 * Enterprise. Ela exige o recurso completo do perfil e um Bearer token de uma conta que
 * administra esse perfil. As três peças são gravadas em **Sistema → Configurações → Chaves de
 * integração → Avaliações do Google**; o `.env` é só o plano de fundo de quem ainda não passou
 * pela tela, e é o `ChavesDeIntegracao::aplicar()` que põe o banco na frente dele. Nenhuma
 * mensagem daqui manda editar arquivo de servidor.
 *
 * A importação é idempotente: cada avaliação é guardada pelo `reviewId` em `externo_id`, então
 * rodar de novo atualiza texto, nota e foto em vez de duplicar. Avaliação sem texto não é
 * depoimento (entra em `sem_texto`), e nota abaixo de `nota_minima` fica de fora — o site
 * publica depoimento, não a escala inteira. Quem desligou a aprovação de um importado no painel
 * continua no controle: atualização nunca reacende `aprovado`.
 *
 * A foto é baixada para `storage/app/public/site/depoimentos` em vez de linkar
 * `googleusercontent.com` direto: o hotlink para de responder sem aviso, e o site fica com um
 * buraco no carrossel.
 */
class DepoimentosDoGoogle
{
    private const BASE = 'https://mybusiness.googleapis.com/v4';

    /** A API devolve a nota como enumeração, não como número. */
    private const ESTRELAS = ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5];

    /** O perfil e o token: sem um dos dois a leitura não tem como acontecer. */
    public static function configurado(): bool
    {
        return static::recurso() !== null && static::token() !== '';
    }

    private static function token(): string
    {
        return trim((string) config('golden.google_business.token'));
    }

    /**
     * O que falta para a leitura rodar, na linguagem da tela de Configurações — vazio quando
     * está tudo no lugar.
     *
     * @return array<int,string>
     */
    public static function faltantes(): array
    {
        $faltam = ChavesDeIntegracao::faltantesDasAvaliacoes();

        if ($faltam === [] && static::recurso() === null) {
            $faltam[] = 'Conta do Business Profile e Local (ficha) precisam ser o número ou o caminho accounts/<id> / locations/<id>';
        }

        return $faltam;
    }

    /** A frase de recusa da importação, escrita uma vez só para a tela e para o comando. */
    public static function avisoDeConfiguracao(): string
    {
        return 'A leitura das avaliações do Google ainda não está configurada. Falta: '
            .implode('; ', static::faltantes()).'. Preencha em '
            .ChavesDeIntegracao::CAMINHO_NO_PAINEL.' → Avaliações do Google.';
    }

    /**
     * O resource name completo (`accounts/123/locations/456`) formado das chaves do painel — ou
     * do `.env`, para o que ele não tiver —, ou null quando falta alguma peça — é assim que o
     * painel avisa "não configurado" sem explodir em cada leitura.
     */
    public static function recurso(): ?string
    {
        $conta = trim((string) config('golden.google_business.account'));
        $local = trim((string) config('golden.google_business.location'));

        if ($conta === '' || $local === '') {
            return null;
        }

        $recurso = static::normalizar($conta, 'accounts').'/'.static::normalizar($local, 'locations');

        return preg_match('#^accounts/[^/]+/locations/[^/]+$#', $recurso) ? $recurso : null;
    }

    private static function normalizar(string $valor, string $prefixo): string
    {
        return str_starts_with($valor, $prefixo.'/') ? $valor : $prefixo.'/'.trim($valor, '/');
    }

    /**
     * Lê as avaliações do perfil, na ordem da API (mais recentes primeiro), até o limite.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function avaliacoes(?int $limite = null): array
    {
        if (! static::configurado()) {
            throw new RuntimeException(static::avisoDeConfiguracao());
        }

        $recurso = static::recurso();
        $token = static::token();

        $limite = max(1, $limite ?? (int) config('golden.google_business.limite', 50));
        $avaliacoes = [];
        $pagina = null;

        do {
            $resposta = Http::withToken($token)
                ->timeout(20)
                ->get(static::BASE.'/'.$recurso.'/reviews', array_filter([
                    'pageSize' => min(50, $limite),
                    'pageToken' => $pagina,
                ]));

            if ($resposta->failed()) {
                throw new RuntimeException(
                    'O Google recusou a leitura das avaliações ('.$resposta->status().'): '
                    .mb_substr((string) $resposta->json('error.message'), 0, 200)
                );
            }

            foreach ($resposta->json('reviews', []) as $avaliacao) {
                $avaliacoes[] = static::mapear($avaliacao);
            }

            $pagina = $resposta->json('nextPageToken');
        } while ($pagina && count($avaliacoes) < $limite);

        return array_slice($avaliacoes, 0, $limite);
    }

    /**
     * @param  array<string,mixed>  $avaliacao
     * @return array<string,mixed>
     */
    private static function mapear(array $avaliacao): array
    {
        $autor = $avaliacao['reviewer'] ?? [];
        return [
            'externo_id' => (string) ($avaliacao['reviewId'] ?? ''),
            'nome' => trim((string) ($autor['displayName'] ?? '')) ?: 'Cliente no Google',
            'texto' => trim((string) ($avaliacao['comment'] ?? '')),
            'nota' => self::ESTRELAS[(string) ($avaliacao['starRating'] ?? '')] ?? 5,
            'avatar_remoto' => trim((string) ($autor['profilePhotoUrl'] ?? '')) ?: null,
            'avaliado_em' => $avaliacao['createTime'] ?? null,
        ];
    }

    /**
     * Importa (ou reimporta) as avaliações e devolve a contabilidade do que aconteceu.
     *
     * @return array{lidos:int, novos:int, atualizados:int, sem_texto:int, abaixo_da_minima:int}
     */
    public static function importar(?int $limite = null): array
    {
        $minima = (int) config('golden.google_business.nota_minima', 4);
        $resumo = ['lidos' => 0, 'novos' => 0, 'atualizados' => 0, 'sem_texto' => 0, 'abaixo_da_minima' => 0];

        foreach (static::avaliacoes($limite) as $avaliacao) {
            $resumo['lidos']++;

            if ($avaliacao['externo_id'] === '' || $avaliacao['texto'] === '') {
                $resumo['sem_texto']++;
                continue;
            }

            if ($avaliacao['nota'] < $minima) {
                $resumo['abaixo_da_minima']++;
                continue;
            }

            $depoimento = Testimonial::where('externo_id', $avaliacao['externo_id'])->first();

            $dados = [
                'nome' => $avaliacao['nome'],
                'texto' => $avaliacao['texto'],
                'nota' => $avaliacao['nota'],
                'fonte' => 'google',
                'papel' => $depoimento?->papel ?: 'Avaliação no Google',
                'avatar' => static::foto($avaliacao['avatar_remoto']),
            ];

            if ($depoimento) {
                // Um importado que o operador desligou no painel continua desligado.
                unset($dados['aprovado']);
                $depoimento->update($dados);
                $resumo['atualizados']++;
                continue;
            }

            Testimonial::create($dados + ['externo_id' => $avaliacao['externo_id'], 'aprovado' => true]);
            $resumo['novos']++;
        }

        return $resumo;
    }

    /**
     * Guarda a foto no disco público e devolve o caminho; se o Google não entregar imagem
     * (URL morta, conteúdo que não é figura), o link remoto é aproveitado como tal — o
     * carrossel aceita os dois formatos e a letra inicial só entra quando não há nada.
     */
    public static function foto(?string $url): ?string
    {
        if (! $url || ! str_starts_with($url, 'https://')) {
            return null;
        }

        try {
            $resposta = Http::timeout(15)->get($url);
        } catch (\Throwable) {
            return $url;
        }

        $tipo = (string) $resposta->header('Content-Type');

        if ($resposta->failed() || ! str_starts_with($tipo, 'image/')) {
            return $url;
        }

        $extensao = match (true) {
            str_contains($tipo, 'png') => 'png',
            str_contains($tipo, 'webp') => 'webp',
            default => 'jpg',
        };
        $caminho = 'site/depoimentos/'.substr(md5($url), 0, 20).'.'.$extensao;

        if (! Storage::disk('public')->exists($caminho)) {
            Storage::disk('public')->put($caminho, $resposta->body());
        }

        return $caminho;
    }
}
