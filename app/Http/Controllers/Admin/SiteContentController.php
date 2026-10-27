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

    /** O selo de cada slot: a palavra vai na tela, a cor vem de config('golden.tons_status.slot_imagem'). */
    private const ESTADOS = [
        'enviada' => 'enviada pelo painel',
        'pacote' => 'foto do pacote',
        'ausente' => 'sem foto no ar',
    ];

    /**
     * As três áreas em que a tela divide as fotografias do site. A regra é o campo `pagina`
     * do próprio slot — o vocabulário do site, não uma lista de arquivos: o que se repete em
     * tudo é identidade, o que começa com "Página inicial" é a home do site, e o resto é o cartaz
     * de uma página interna.
     */
    private const AREAS = [
        'identidade' => [
            'titulo' => 'Identidade',
            'icone' => 'fa-badge',
            'descricao' => 'O que vale em todo o site de uma vez: a marca, o símbolo da aba do navegador e o cartão que as redes mostram ao compartilhar um link.',
        ],
        'home' => [
            'titulo' => 'Página inicial',
            'icone' => 'fa-house',
            'descricao' => 'As fotografias da página inicial, na ordem em que aparecem para quem entra no site e rola a tela.',
        ],
        'cartazes' => [
            'titulo' => 'Cartazes das páginas',
            'icone' => 'fa-file-lines',
            'descricao' => 'A foto de topo de cada página do site. Sem envio, a abertura fica com o degradê padrão da própria página.',
        ],
    ];

    public function imagens(): View
    {
        $porArea = array_fill_keys(array_keys(self::AREAS), []);
        $estados = ['enviada' => 0, 'pacote' => 0, 'ausente' => 0];

        foreach (ConteudoSite::slots() as $slot => $registro) {
            $enviada = ConteudoSite::enviada($slot);
            $url = ConteudoSite::url($slot);
            $estado = $enviada ? 'enviada' : ($url ? 'pacote' : 'ausente');
            $estados[$estado]++;

            $pagina = (string) $registro['pagina'];
            $area = self::areaDaPagina($pagina);

            $porArea[$area][] = [
                'slot' => $slot,
                'registro' => $registro,
                // Onde a foto aparece, na voz do site: a seção da página inicial, ou o nome da
                // página quando todos os cards da área têm o mesmo título (os cartazes).
                'onde' => Str::contains($pagina, ' · ')
                    ? Str::after($pagina, ' · ')
                    : ($area === 'cartazes' ? $pagina : null),
                'enviada' => $enviada,
                'url' => $url,
                'alt' => ConteudoSite::alt($slot),
                'sem_arquivo' => ! ConteudoSite::padraoExiste($slot),
                'rota' => $this->rotaDaPagina($pagina),
                'estado' => $estado,
                'estado_rotulo' => self::ESTADOS[$estado],
            ];
        }

        $abas = collect(self::AREAS)
            ->map(fn (array $area, string $id) => $area + ['id' => $id, 'itens' => $porArea[$id]])
            ->reject(fn (array $area) => $area['itens'] === [])
            ->values()
            ->all();

        return view('admin.site-content.imagens', [
            'abas' => $abas,
            'total' => count(ConteudoSite::slots()),
            'no_painel' => $estados['enviada'],
            'no_pacote' => $estados['pacote'],
            'sem_foto' => $estados['ausente'],
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
                    $erros["imagem.{$slot}"] = "A foto de {$registro['titulo']} não é um arquivo enviado por este campo.";
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
                $erros["alt.{$slot}"] = "O texto alternativo de {$registro['titulo']} passou de 190 caracteres.";
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
                    $erros["paginas_site.{$chave}.{$campo}"] = $pagina['nome'].' — '.ucfirst($campo)
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

    /** A aba da tela em que o slot aparece, lida do mesmo rótulo de página do registro. */
    private static function areaDaPagina(string $rotulo): string
    {
        return match (true) {
            $rotulo === 'Todo o site' => 'identidade',
            Str::before($rotulo, ' ·') === 'Página inicial' => 'home',
            default => 'cartazes',
        };
    }

    /**
     * A página onde o slot aparece, para o botão "ver no site": o rótulo do grupo
     * ('Página inicial · Para quem é') é conferido contra config('golden.paginas_site'),
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
