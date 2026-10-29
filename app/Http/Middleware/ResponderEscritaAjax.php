<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Toda gravação do painel é feita por segundo plano desde aqui: o navegador manda o formulário
 * sem recarregar a tela, e quem grava continua respondendo como sempre respondeu — um redirect de
 * volta para a tela com o recado guardado na sessão.
 *
 * Esta folha é a única que traduz esse redirect para o pedido assíncrono. O recado sai do bolso da
 * sessão e vai no corpo da resposta (por isso ele não reaparece na próxima carga real, e por isso
 * também não precisa de um segundo pedido para ser lido); o motivo da validação sai separado por
 * campo, para a tela vermelhar no lugar sem perder o que a pessoa digitou; e o destino vai junto,
 * com a marca de ser a mesma tela ou não — é o que decide se o navegador fica ou troca de página.
 *
 * Um pedido de navegador comum continua recebendo o redirect intacto: nada aqui muda a resposta de
 * quem não pediu JSON, e é por isso que nenhum dos controllers de escrita precisou ser tocado.
 */
class ResponderEscritaAjax
{
    /**
     * As chaves de sessão que o painel desenha como toastr — as mesmas cinco de
     * resources/views/partials/avisos.blade.php. Chave de sessão => método do GoldenAvisos.
     */
    private const AVISOS = [
        'success' => 'sucesso',
        'status' => 'sucesso',
        'info' => 'info',
        'warning' => 'aviso',
        'error' => 'erro',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->deveTraduzir($request, $response)) {
            return $response;
        }

        $destino = (string) $response->getTargetUrl();
        $sessao = $request->session();
        $mensagens = [];
        $falhou = false;

        foreach (self::AVISOS as $chave => $metodo) {
            $texto = $sessao->get($chave);

            if (is_string($texto) && $texto !== '') {
                $mensagens[] = ['tipo' => $metodo, 'texto' => $texto];
                $falhou = $falhou || $metodo === 'erro';
            }

            // O recado já saiu na resposta: tirado da sessão, ele não volta a aparecer numa
            // carga real — sem isto a pessoa veria o mesmo toastr duas vezes.
            $sessao->forget($chave);
        }

        $campos = $this->errosPorCampo($request);
        $falhou = $falhou || $campos !== [];

        // A sessão venceu no meio do trabalho: o redirect para a tela de entrada não é sucesso,
        // e o navegador precisa ir atrás dela de verdade.
        $paraEntrar = $this->mesmoEndereco($destino, (string) route('login', absolute: true));
        $mesmaTela = ! $paraEntrar && $this->mesmaPagina($request, $destino);

        return response()->json([
            'ok' => ! $falhou && ! $paraEntrar,
            'mensagens' => $mensagens,
            'campos' => $campos,
            'mesma_tela' => $mesmaTela,
            'redirecionar' => $mesmaTela ? null : $destino,
        ])->setStatusCode(200)->header('Cache-Control', 'no-store');
    }

    private function deveTraduzir(Request $request, Response $response): bool
    {
        return $response instanceof RedirectResponse
            && ($request->ajax() || $request->expectsJson())
            && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * Os motivos de validação que o controller devolveu em `->withErrors()`, achatados por campo.
     * O `validate()` puro já sai como 422 JSON antes de chegar aqui; isto cobre quem valida na
     * mão e manda a pessoa de volta para a mesma tela.
     */
    private function errosPorCampo(Request $request): array
    {
        $bag = $request->session()->get('errors');
        $erros = [];

        // `->withErrors()` guarda um estúdio com uma bolsa por grupo; um MessageBag solto é a
        // bolsa única. Os dois chegam aqui porque os dois são escritos no painel — e o mais
        // comum de todos é não haver bolsa nenhuma, porque a gravação deu certo.
        $bolsas = match (true) {
            $bag instanceof ViewErrorBag => $bag->getBags(),
            $bag instanceof MessageBag => [$bag],
            default => [],
        };

        foreach ($bolsas as $bolsa) {
            foreach ($bolsa->messages() as $campo => $motivos) {
                $erros[$campo] = array_merge($erros[$campo] ?? [], array_values($motivos));
            }
        }

        if ($bolsas !== []) {
            $request->session()->forget('errors');
        }

        return $erros;
    }

    /**
     * Mesma tela é o destino bater com a tela que a pessoa está vendo — não com o endereço do POST.
     * Criar uma campanha é o exemplo: o formulário mora em `/campanhas/nova`, grava em
     * `/campanhas` e volta para `/campanhas`. Comparado com a requisição, isso seria "fica onde
     * está" e a pessoa continuaria diante do formulário vazio; comparado com a tela, é troca de
     * página. O navegador diz onde está no cabeçalho que ele mesmo manda; sem ele, o referer sabe;
     * e só quando os dois calam (um pedido de teste, por exemplo) é que o endereço da requisição
     * responde.
     */
    private function mesmaPagina(Request $request, string $destino): bool
    {
        $tela = $request->headers->get('X-Golden-Tela') ?: (string) $request->headers->get('referer');

        return $this->mesmoEndereco($destino, $tela !== '' ? $tela : $request->fullUrl());
    }

    /**
     * Mesma tela é o endereço com o mesmo caminho e a mesma consulta — ignorando o fragmento, que
     * o painel usa para lembrar a aba aberta e não tem peso nenhum aqui.
     */
    private function mesmoEndereco(string $um, string $dois): bool
    {
        $a = parse_url($um);
        $b = parse_url($dois);

        return rtrim((string) ($a['path'] ?? '/'), '/') === rtrim((string) ($b['path'] ?? '/'), '/')
            && (string) ($a['query'] ?? '') === (string) ($b['query'] ?? '');
    }
}
