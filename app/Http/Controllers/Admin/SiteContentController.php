<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Setting;
use App\Support\ConteudoSite;
use App\Support\Envio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * O que o CMS manda no site público, além das páginas do menu: cada fotografia
 * exposta (slots de config('golden.imagens')) e a copy de abertura de cada
 * página fixa (config('golden.paginas_site')).
 *
 * As duas telas seguem a mesma regra de precedência — o valor salvo em
 * `settings` vence e o padrão empacotado fica por baixo, então apagar o que o
 * painel gravou faz o original voltar sem republicar nada. Nenhuma chave é
 * aceita do formulário: a lista vem do config, o que barra escrita arbitrária
 * em `settings`.
 */
class SiteContentController extends Controller
{
    /** Campos de texto de cada página, com o limite de cada um. */
    private const TEXTOS = ['rotulo' => 60, 'eyebrow' => 120, 'titulo' => 190, 'subtitulo' => 500];

    public function imagens(): View
    {
        $grupos = [];

        foreach (ConteudoSite::porPagina() as $pagina => $slots) {
            $itens = [];

            foreach ($slots as $slot => $registro) {
                $itens[] = [
                    'slot' => $slot,
                    'registro' => $registro,
                    'enviada' => ConteudoSite::enviada($slot),
                    'url' => ConteudoSite::url($slot),
                    'alt' => ConteudoSite::alt($slot),
                    'sem_arquivo' => ! ConteudoSite::padraoExiste($slot),
                    'rota' => $this->rotaDaPagina($pagina),
                ];
            }

            $grupos[] = ['pagina' => $pagina, 'slots' => $itens];
        }

        return view('admin.site-content.imagens', [
            'grupos' => $grupos,
            'total' => count(ConteudoSite::slots()),
        ]);
    }

    public function salvarImagens(Request $request): RedirectResponse
    {
        $envios = (array) $request->input('imagem', []);
        $alts = (array) $request->input('alt', []);
        $voltar = (array) $request->input('voltar_ao_padrao', []);
        $erros = [];
        $gravadas = 0;

        foreach (ConteudoSite::slots() as $slot => $registro) {
            $atual = ConteudoSite::enviada($slot);
            $novo = trim((string) ($envios[$slot] ?? ''));
            $revertida = false;

            if ($novo !== '' && $novo !== $atual) {
                if (! Envio::caminhoConfere($novo, 'site_imagem')) {
                    $erros["imagem[{$slot}]"] = "A foto de {$registro['titulo']} não é um arquivo enviado por este campo.";
                    continue;
                }

                Envio::remover($atual, 'site_imagem');
                Setting::set('imagem.'.$slot, $novo);
                $gravadas++;
            } elseif (($voltar[$slot] ?? null) === '1' && $atual !== null) {
                Envio::remover($atual, 'site_imagem');
                Setting::forget('imagem.'.$slot);
                $revertida = true;
                $gravadas++;
            }

            // O texto alternativo pertence à foto: ao voltar ao original, a descrição do
            // envio vai junto e o que vale de novo é o alt registrado para o pacote.
            $alt = $revertida ? '' : trim((string) ($alts[$slot] ?? ''));

            if (mb_strlen($alt) > 190) {
                $erros["alt[{$slot}]"] = "O texto alternativo de {$registro['titulo']} passou de 190 caracteres.";
                continue;
            }

            // Em branco ou igual ao registro do pacote apaga a chave: a frase volta a
            // viver em um único lugar (config), e a tela mostra sempre o que está no ar.
            if ($alt === '' || $alt === (string) ($registro['alt'] ?? '')) {
                Setting::forget('imagem_alt.'.$slot);
            } else {
                Setting::set('imagem_alt.'.$slot, $alt);
            }
        }

        if ($erros !== []) {
            return back()->withInput()->withErrors($erros);
        }

        Activity::log('configuracao_salva', 'Imagens do site atualizadas pelo CMS.');

        return back()->with('success', match (true) {
            $gravadas === 1 => 'Imagem atualizada no site.',
            $gravadas > 1 => $gravadas.' imagens atualizadas no site.',
            default => 'Nenhuma imagem mudou.',
        });
    }

    public function textos(): View
    {
        $paginas = collect(config('golden.paginas_site', []))
            ->map(fn (array $p, string $chave) => [
                'chave' => $chave,
                'padrao' => $p,
                'nome' => $p['nome'],
                'rota' => $p['rota'],
                'valores' => collect(self::TEXTOS)
                    ->map(fn (int $max, string $campo) => ConteudoSite::pagina($chave, $campo))
                    ->all(),
            ])
            ->values()
            ->all();

        return view('admin.site-content.textos', ['paginas' => $paginas]);
    }

    public function salvarTextos(Request $request): RedirectResponse
    {
        $enviados = (array) $request->input('paginas_site', []);
        $erros = [];
        $salvos = 0;

        foreach (config('golden.paginas_site', []) as $chave => $pagina) {
            foreach (self::TEXTOS as $campo => $max) {
                $valor = trim((string) (data_get($enviados, "{$chave}.{$campo}") ?? ''));
                $padrao = (string) ($pagina[$campo] ?? '');

                if (mb_strlen($valor) > $max) {
                    $erros["paginas_site[{$chave}][{$campo}]"] = $pagina['nome'].' — '.ucfirst($campo)
                        .' passou de '.$max.' caracteres.';
                    continue;
                }

                if ($valor === $padrao) {
                    Setting::forget("paginas_site.{$chave}.{$campo}");
                } elseif ($valor !== (string) Setting::get("paginas_site.{$chave}.{$campo}")) {
                    Setting::set("paginas_site.{$chave}.{$campo}", $valor);
                    $salvos++;
                }
            }
        }

        if ($erros !== []) {
            return back()->withInput()->withErrors($erros);
        }

        Activity::log('configuracao_salva', 'Textos das páginas do site atualizados.');

        return back()->with('success', $salvos > 0 ? 'Textos salvos no site.' : 'Nenhum texto mudou.');
    }

    /**
     * A página onde o slot aparece, para o botão "ver no site": o rótulo do grupo
     * ('Home · Para quem é') é conferido contra config('golden.paginas_site'),
     * então a tela nunca aponta para uma rota inventada.
     */
    private function rotaDaPagina(string $rotulo): string
    {
        $nome = Str::before($rotulo, ' ·');

        foreach (config('golden.paginas_site', []) as $pagina) {
            if (($pagina['nome'] ?? null) === $nome) {
                return $pagina['rota'];
            }
        }

        return 'site.home';
    }
}
