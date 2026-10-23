<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        View::composer(['layouts.site', 'site.*', 'admin.layouts.app'], function ($view) {
            $whatsapp = preg_replace('/\D/', '', Setting::get('contato_whatsapp', config('golden.whatsapp')));
            $paginas = \App\Models\Page::menu()->get(['titulo', 'slug']);
            $lojaAtiva = Setting::get('loja_ativa', '1') === '1';
            $view->with('golden', [
                'marca' => config('golden.marca'),
                'whatsapp' => $whatsapp,
                'whatsapp_url' => 'https://wa.me/' . $whatsapp,
                'email' => Setting::get('contato_email', config('golden.email')),
                'telefone' => Setting::get('contato_telefone', implode(' · ', config('golden.contato.telefones'))),
                'endereco' => Setting::get('empresa_endereco', config('golden.contato.endereco')),
                'cidade_uf' => Setting::get('empresa_cidade', config('golden.contato.cidade_uf')),
                'cep' => Setting::get('empresa_cep', config('golden.contato.cep')),
                'pagamentos' => config('golden.pagamentos'),
                'parceiros' => config('golden.parceiros_m2m'),
                'loja_ativa' => $lojaAtiva,
                'nav' => $this->montarMenu($paginas, $lojaAtiva),
                'tracking' => Setting::get('tracking_enabled', '0') === '1',
                'ga_id' => Setting::get('ga_measurement_id'),
                'meta_pixel' => Setting::get('meta_pixel_id'),
            ]);
        });
    }

    /**
     * Árvore do menu do site: o esqueleto vem de config('golden.nav') e os grupos
     * dinâmicos (categorias da loja e páginas do CMS) são resolvidos aqui, para que
     * header e gaveta mobile compartilhem exatamente os mesmos itens.
     */
    private function montarMenu($paginas, bool $lojaAtiva): array
    {
        $rotaAtual = optional(request()->route())->getName() ?: '';
        $paginaAtual = (string) request()->route('slug');
        $categoriaAtual = (string) request()->query('categoria');

        // Um item só está ativo se a rota bater e a "chave" dele também: páginas do
        // CMS se distinguem pelo slug e as categorias da loja, pelo filtro enviado
        // na query string — sem isso todos os filhos do grupo acenderiam juntos.
        $bate = function (string $rota, $param = null) use ($rotaAtual, $paginaAtual, $categoriaAtual): bool {
            if ($rotaAtual !== $rota) {
                return false;
            }

            if (is_string($param)) {
                return $param === $paginaAtual;
            }

            if (is_array($param)) {
                return ($param['categoria'] ?? '') === $categoriaAtual;
            }

            return $categoriaAtual === '';
        };

        $montar = function (array $item, bool $pai = false) use ($bate, $rotaAtual): array {
            $filhos = collect($item['filhos'] ?? [])
                ->map(fn ($f) => [
                    'titulo' => $f['titulo'],
                    'url' => route($f['rota'], $f['param'] ?? []),
                    'ativo' => $bate($f['rota'], $f['param'] ?? null),
                ])
                ->all();

            $ativo = isset($item['rota'])
                ? $bate($item['rota'], $item['param'] ?? null)
                    || ($pai && ($item['prefixo'] ?? null) !== null && str_starts_with($rotaAtual, $item['prefixo']))
                : false;

            return [
                'titulo' => $item['titulo'],
                'url' => isset($item['rota']) ? route($item['rota'], $item['param'] ?? []) : null,
                'ativo' => $ativo || collect($filhos)->contains('ativo', true),
                'filhos' => $filhos,
            ];
        };

        return collect(config('golden.nav'))
            ->reject(fn ($item) => ($item['grupo'] ?? null) === 'loja' && !$lojaAtiva)
            ->map(function ($item) use ($montar, $paginas) {
                if (($item['grupo'] ?? null) === 'loja') {
                    $item['rota'] = 'site.loja.index';
                    $item['prefixo'] = 'site.loja.';
                    $item['filhos'] = collect([['titulo' => 'Todos os produtos', 'rota' => 'site.loja.index']])
                        ->merge(\App\Models\Category::ativas()->get(['nome', 'slug'])
                            ->map(fn ($c) => ['titulo' => $c->nome, 'rota' => 'site.loja.index', 'param' => ['categoria' => $c->slug]]))
                        ->values()
                        ->all();
                }

                if (($item['grupo'] ?? null) === 'institucional') {
                    $item['rota'] = null;
                    $item['filhos'] = $paginas
                        ->map(fn ($p) => ['titulo' => $p->titulo, 'rota' => 'site.pagina', 'param' => $p->slug])
                        ->concat([
                            ['titulo' => 'Dúvidas frequentes', 'rota' => 'site.faq'],
                            ['titulo' => 'Política de privacidade', 'rota' => 'site.privacidade'],
                        ])
                        ->values()
                        ->all();
                }

                return $montar($item, true);
            })
            ->values()
            ->all();
    }
}
