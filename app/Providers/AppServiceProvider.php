<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Carrinho;
use App\Support\MenuPainel;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<string, mixed>|null pacote do site já montado nesta requisição */
    private ?array $site = null;

    /** @var array<string, int>|null contadores do menu do painel, nesta requisição */
    private ?array $pendencias = null;

    /** @var array<int,array<string,mixed>>|null árvore da sidebar do painel, nesta requisição */
    private ?array $menuPainel = null;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // "É administrador" tem uma única pergunta no sistema inteiro: @can('admin').
        // As rotas continuam travadas pelo middleware de papel (a regra que vale é a da
        // rota); o gate serve para a tela não oferecer um botão que a rota negaria.
        Gate::define('admin', fn (User $usuario): bool => $usuario->isAdmin());

        View::composer(['layouts.site', 'site.*', 'admin.layouts.app'], function ($view) {
            $view->with('golden', $this->dadosDoSite());
        });

        View::composer('admin.layouts.app', function ($view) {
            $view->with('menu_painel', $this->menuDoPainel($this->dadosDoSite()['loja_ativa']));
        });

        // As memórias acima valem para UMA requisição: o provider sobrevive entre duas
        // requisições do mesmo teste (e entre requisições em servidor embutido com
        // keep-alive), e o menu da página anterior chegaria pronto na próxima. Cada
        // rota casada limpa o estado.
        Event::listen(RouteMatched::class, function () {
            $this->site = null;
            $this->pendencias = null;
            $this->menuPainel = null;
        });
    }

    /**
     * Árvore da sidebar, memoizada como o resto: a view do layout é composta uma vez por
     * requisição, mas o custo de montar de novo seria pago em cada include da sidebar.
     */
    private function menuDoPainel(bool $lojaAtiva): array
    {
        return $this->menuPainel ??= MenuPainel::montar(
            $this->pendenciasDoPainel(),
            (bool) optional(auth()->user())->can('admin'),
            ['loja_ativa' => $lojaAtiva],
        );
    }

    /**
     * Contadores que acendem as bolhas do menu. Fica fora da view para o layout não
     * rodar consulta em cada partial incluído.
     */
    private function pendenciasDoPainel(): array
    {
        return $this->pendencias ??= [
            'leads_novos' => Lead::where('status', 'novo')->count(),
            'pedidos_abertos' => Order::whereIn('status', Order::ABERTOS)->count(),
        ];
    }

    /**
     * O pacote de variáveis compartilhadas por todo o site. O composer dispara em cada
     * view filha (layout, home, partials), então o resultado fica memoizado na
     * instância — uma montagem por requisição em vez de cinco.
     */
    private function dadosDoSite(): array
    {
        if ($this->site !== null) {
            return $this->site;
        }

        $valores = Setting::all_values();
        $pegar = fn (string $chave, $padrao = null) => ($valores[$chave] ?? '') !== '' ? $valores[$chave] : $padrao;

        $whatsapp = preg_replace('/\D/', '', $pegar('contato_whatsapp', config('golden.whatsapp')));
        $lojaAtiva = $pegar('loja_ativa', '1') === '1';
        $box = config('golden.whatsapp_box');

        return $this->site = [
            'marca' => config('golden.marca'),
            'whatsapp' => $whatsapp,
            'whatsapp_url' => wa_url(null, $whatsapp),
            'whatsapp_box' => [
                'ativo' => $pegar('whatsapp_box_ativo', '1') === '1',
                'titulo' => $pegar('whatsapp_box_titulo', $box['titulo']),
                'subtitulo' => $pegar('whatsapp_box_subtitulo', $box['subtitulo']),
                'abertura' => $pegar('whatsapp_box_abertura', $box['abertura']),
            ],
            'canais' => $this->montarCanais(),
            'email' => $pegar('contato_email', config('golden.email')),
            'telefone' => $pegar('contato_telefone', implode(' · ', config('golden.contato.telefones'))),
            'endereco' => $pegar('empresa_endereco', config('golden.contato.endereco')),
            'cidade_uf' => $pegar('empresa_cidade', config('golden.contato.cidade_uf')),
            'cep' => $pegar('empresa_cep', config('golden.contato.cep')),
            'pagamentos' => config('golden.pagamentos'),
            'parceiros' => config('golden.parceiros_m2m'),
            'loja_ativa' => $lojaAtiva,
            'carrinho_itens' => $lojaAtiva ? Carrinho::daSessao()->totalItens() : 0,
            'nav' => $this->montarMenu($lojaAtiva),
            'tracking' => $pegar('tracking_enabled', '0') === '1',
            'ga_id' => $pegar('ga_measurement_id'),
            'meta_pixel' => $pegar('meta_pixel_id'),
        ];
    }

    /**
     * Árvore do menu do site: o esqueleto vem de config('golden.nav') e os grupos
     * dinâmicos (categorias da loja e páginas do CMS) são resolvidos aqui, para que
     * header e gaveta mobile compartilhem exatamente os mesmos itens.
     */
    private function montarMenu(bool $lojaAtiva): array
    {
        $rotaAtual = optional(request()->route())->getName() ?: '';
        $paginaAtual = (string) request()->route('slug');
        $categoriaAtual = (string) request()->query('categoria');
        $paginas = collect(Page::menuParaSite());

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
            ->reject(fn ($item) => ($item['grupo'] ?? null) === 'loja' && ! $lojaAtiva)
            ->map(function ($item) use ($montar, $paginas) {
                if (($item['grupo'] ?? null) === 'loja') {
                    $item['rota'] = 'site.loja.index';
                    $item['prefixo'] = 'site.loja.';
                    $item['filhos'] = collect([['titulo' => 'Todos os produtos', 'rota' => 'site.loja.index']])
                        ->merge(collect(Category::ativasParaSite())
                            ->map(fn ($c) => ['titulo' => $c['nome'], 'rota' => 'site.loja.index', 'param' => ['categoria' => $c['slug']]]))
                        ->values()
                        ->all();
                }

                if (($item['grupo'] ?? null) === 'institucional') {
                    $item['rota'] = null;
                    $item['filhos'] = $paginas
                        ->map(fn ($p) => ['titulo' => $p['titulo'], 'rota' => 'site.pagina', 'param' => $p['slug']])
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

    /**
     * Canais da caixa de WhatsApp: o roteiro vem de config('golden.whatsapp_box') e o
     * painel pode desligar a caixa, trocar título/subtítulo e apontar um canal para
     * outro número (whatsapp_canal_<id>_numero).
     */
    private function montarCanais(): array
    {
        $canais = [];

        foreach (config('golden.whatsapp_box.canais') as $canal) {
            $id = $canal['id'];

            if (Setting::get('whatsapp_canal_'.$id.'_ativo', '1') !== '1') {
                continue;
            }

            $canais[] = [
                'id' => $id,
                'icone' => $canal['icone'],
                'nome' => Setting::get('whatsapp_canal_'.$id.'_nome', $canal['nome']),
                'desc' => Setting::get('whatsapp_canal_'.$id.'_desc', $canal['desc']),
                'url' => wa_url($canal['msg'], Setting::get('whatsapp_canal_'.$id.'_numero')),
            ];
        }

        return $canais;
    }
}
