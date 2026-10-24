<?php

namespace Tests\Feature;

use FilesystemIterator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * O esqueleto do Laravel 13 não traz arquivo de tradução nenhum: enquanto lang/pt_BR não
 * existiu, toda regra sem texto virou chave crua na tela — foi assim que a entrada do
 * cliente mostrou "validation.required" para o dono do sistema.
 */
class TraducoesPtBrTest extends TestCase
{
    /** Regras que não geram frase nenhuma: só mudam o caminho do validador. */
    private const MODIFICADORES = [
        'bail', 'nullable', 'present', 'sometimes', 'excluded',
        'exclude_if', 'exclude_unless', 'exclude_with', 'exclude_without',
    ];

    public function test_o_sistema_fala_portugues_desde_o_primeiro_acesso(): void
    {
        $this->assertSame('pt_BR', config('app.locale'));

        foreach (['validation', 'auth', 'pagination', 'passwords'] as $arquivo) {
            $this->assertFileExists(base_path("lang/pt_BR/{$arquivo}.php"));
        }
    }

    public function test_as_telas_de_acesso_respondem_em_portugues(): void
    {
        foreach ([route('login.post'), route('portal.login.post'), route('portal.cadastro.post')] as $url) {
            $this->post($url, []);
            $mensagens = $this->mensagens(session('errors'));

            $this->assertNotEmpty($mensagens, "{$url} passou sem cobrar nenhum campo.");
            $texto = implode(' ', $mensagens);
            $this->assertStringNotContainsString('validation.', $texto, "{$url} devolveu chave crua de tradução.");
            $this->assertStringNotContainsString('auth.', $texto, "{$url} devolveu chave crua de tradução.");
            $this->assertStringContainsString('obrigat', $texto, "{$url} não respondeu em português: {$texto}");
        }
    }

    public function test_cada_regra_usada_no_codigo_tem_texto_em_portugues(): void
    {
        $traduzidas = array_keys(require base_path('lang/pt_BR/validation.php'));
        $daFramework = $this->regrasDaFramework();

        $faltando = [];
        foreach ($this->regrasDoCodigo() as $regra) {
            // só interessa o que a framework de fato sabe validar; o resto é nome de campo
            if (! in_array($regra, $daFramework, true) || in_array($regra, self::MODIFICADORES, true)) {
                continue;
            }

            if (! in_array($regra, $traduzidas, true)) {
                $faltando[] = $regra;
            }
        }

        $this->assertSame([], $faltando,
            'Estas regras são usadas no sistema e não têm frase em lang/pt_BR/validation.php: '.implode(', ', $faltando));
    }

    public function test_os_campos_em_portugues_tem_nome_legivel_na_mensagem(): void
    {
        $atributos = (require base_path('lang/pt_BR/validation.php'))['attributes'];
        $this->assertArrayHasKey('email', $atributos);
        $this->assertArrayHasKey('celular', $atributos);

        $mensagem = __('validation.required', ['attribute' => Str::lower(trans('validation.attributes.celular'))]);
        $this->assertStringContainsString('celular', $mensagem);
    }

    /**
     * O saco de erros chega como MessageBag ou já aberto em array, dependendo de quem o
     * gravou na sessão; o teste só quer as frases.
     *
     * @return list<string>
     */
    private function mensagens($saco): array
    {
        if ($saco instanceof MessageBag) {
            return $saco->all();
        }

        if ($saco instanceof Arrayable) {
            $saco = $saco->toArray();
        }

        return array_values(array_filter(Arr::flatten((array) $saco), 'is_string'));
    }

    /**
     * Os nomes que a framework sabe validar, lidos do próprio validador — a lista muda com
     * o Laravel, então ela não é copiada para dentro deste teste.
     *
     * @return list<string>
     */
    private function regrasDaFramework(): array
    {
        $validador = app('validator')->make([], []);
        $nomes = [];

        foreach (get_class_methods($validador) as $metodo) {
            if (str_starts_with($metodo, 'validate')) {
                $nomes[] = Str::snake(substr($metodo, strlen('validate')));
            }
        }

        return $nomes;
    }

    /**
     * Tudo que aparece como string dentro de um $request->validate([...]) ou
     * Validator::make($dados, [...]) do código — nomes de regra misturados com nomes de campo.
     *
     * @return list<string>
     */
    private function regrasDoCodigo(): array
    {
        $itens = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
        );
        $regras = [];

        foreach ($itens as $item) {
            if (! $item->isFile() || ! str_ends_with($item->getFilename(), '.php')) {
                continue;
            }

            $conteudo = (string) file_get_contents($item->getPathname());
            preg_match_all('/(?:->validate\(|Validator::make\()\s*(?:\$[a-zA-Z_]\w*\s*,\s*)?\[(.*?)\]\s*\)/s', $conteudo, $blocos);

            foreach ($blocos[1] as $bloco) {
                preg_match_all('/[\'"]([^\'"]*)[\'"]/', $bloco, $lit);

                foreach ($lit[1] as $pedaco) {
                    foreach (explode('|', $pedaco) as $regra) {
                        $nome = strtolower(trim(explode(':', $regra)[0]));

                        if ($nome !== '' && preg_match('/^[a-z0-9_]+$/', $nome)) {
                            $regras[] = $nome;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($regras));
    }
}
