<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Conversa;
use App\Models\CronTarefa;
use App\Models\Lead;
use App\Models\Notificacao;
use App\Models\Order;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Agendador;
use App\Support\Carrinho;
use App\Support\ChavesDeIntegracao;
use App\Support\MenuPainel;
use App\Support\Notifica;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Auth;
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

    /** @var array<int,array<string,mixed>>|null árvore da sidebar do portal, nesta requisição */
    private ?array $menuPortal = null;

    /** @var array<string,mixed>|null sino do painel já montado nesta requisição */
    private ?array $sino = null;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // O que o painel gravou no banco passa na frente do .env antes de qualquer leitura.
        // É aqui, e em nenhum outro lugar: os ~15 leitores do sistema seguem perguntando
        // config('google_ads.developer_token') sem saber de onde o valor veio — e por isso
        // salvar na tela vale na hora, sem config:clear nem reinício de servidor.
        ChavesDeIntegracao::aplicar();

        // Toda lista paginada do painel e do portal passa por um único view. O padrão do
        // framework é o do Tailwind: dentro do Bootstrap ele sai sem estilo e com SVG de
        // ícone sem largura, desenhado do tamanho da tela.
        Paginator::defaultView('pagination.painel');
        Paginator::defaultSimpleView('pagination.painel');

        // "É administrador" tem uma única pergunta no sistema inteiro: @can('admin').
        // As rotas continuam travadas pelo middleware de papel (a regra que vale é a da
        // rota); o gate serve para a tela não oferecer um botão que a rota negaria.
        Gate::define('admin', fn (User $usuario): bool => $usuario->isAdmin());

        View::composer(['layouts.site', 'layouts.funil', 'site.*', 'portal.*', 'admin.layouts.app'], function ($view) {
            $view->with('golden', $this->dadosDoSite());
        });

        View::composer('admin.layouts.app', function ($view) {
            // O sino primeiro: o contador de não lidas da sidebar vem do mesmo pacote, então
            // a tela não pergunta duas vezes a mesma coisa.
            $view->with('sino', $this->sinoDoPainel());
            $view->with('menu_painel', $this->menuDoPainel($this->dadosDoSite()['loja_ativa']));
        });

        // O portal tem a mesma sidebar do painel: mesmo renderizador, mesma árvore resolvida
        // por MenuPainel, só que com as rotas e as contas do cliente.
        View::composer('portal.layout', function ($view) {
            $view->with('menu_portal', $this->menuDoPortal($this->dadosDoSite()['loja_ativa']));
        });

        // As memórias acima valem para UMA requisição: o provider sobrevive entre duas
        // requisições do mesmo teste (e entre requisições em servidor embutido com
        // keep-alive), e o menu da página anterior chegaria pronto na próxima. Cada
        // rota casada limpa o estado.
        Event::listen(RouteMatched::class, function () {
            $this->site = null;
            $this->pendencias = null;
            $this->menuPainel = null;
            $this->menuPortal = null;
            $this->sino = null;
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
     * Árvore da sidebar do portal: o mesmo MenuPainel do painel administrativo, com a árvore
     * do cliente e as contas dele. Ninguém que entra no portal é administrador.
     */
    private function menuDoPortal(bool $lojaAtiva): array
    {
        return $this->menuPortal ??= MenuPainel::montar(
            $this->pendenciasDoPortal(),
            false,
            ['loja_ativa' => $lojaAtiva],
            'golden.menu_portal',
        );
    }

    /**
     * O sino do topo: as não lidas desta pessoa, no mesmo formato que a partial e o JSON
     * compartilham. Montado uma vez por requisição — o contador da sidebar lê o total daqui
     * em vez de perguntar de novo.
     */
    private function sinoDoPainel(): array
    {
        if ($this->sino !== null) {
            return $this->sino;
        }

        $links = [
            'url' => route('admin.notificacoes.sino'),
            'ver_todas' => route('admin.notificacoes.index'),
            'marcar_todas' => route('admin.notificacoes.lidas'),
            'preferencias' => route('admin.notificacoes.preferencias'),
            'intervalo_visivel' => (int) config('notificacoes.sino_intervalo_ms.visivel', 45000),
            'intervalo_escondida' => (int) config('notificacoes.sino_intervalo_ms.escondida', 300000),
        ];

        $usuario = auth()->user();

        if ($usuario === null || ! Notifica::centralPronta()) {
            return $this->sino = ['total' => 0, 'itens' => []] + $links;
        }

        $novas = Notificacao::para($usuario)->naovistas()->latest();
        $limite = max(1, (int) config('notificacoes.sino_limite', 8));

        return $this->sino = [
            'total' => (clone $novas)->count(),
            'itens' => (clone $novas)->limit($limite)->get()
                ->map(fn (Notificacao $notificacao): array => $notificacao->paraSino())
                ->all(),
        ] + $links;
    }

    /**
     * Bolhas do portal: o que está esperando no nome desta ficha, não o total da empresa.
     */
    private function pendenciasDoPortal(): array
    {
        $cliente = Auth::guard('cliente')->user();

        return [
            'pedidos_abertos' => $cliente?->orders()->reorder()->whereIn('status', Order::ABERTOS)->count() ?? 0,
            'chamados_abertos' => $cliente?->tickets()->reorder()->whereIn('status', Ticket::ABERTOS)->count() ?? 0,
        ];
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
            'chamados_abertos' => Ticket::whereIn('status', Ticket::ABERTOS)->count(),
            'conversas_aguardando' => Conversa::where('status', 'aguardando_equipe')->count(),
            'notificacoes_novas' => $this->sinoDoPainel()['total'],
            'crons_com_falha' => Agendador::agendaPronta() ? CronTarefa::ativas()->comFalha()->count() : 0,
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

        // O assistente não tem botão próprio: ele é um dos atendentes da caixa do WhatsApp. A
        // caixa desligada deixa o botão direto do WhatsApp no lugar, e aí o robô não tem porta —
        // sem porta, a página nem carrega a folha e o script dele.
        $boxAtivo = $pegar('whatsapp_box_ativo', '1') === '1';
        $canais = $this->montarCanais();

        // Header, gaveta e rodapé compartilham a mesma resposta: para onde a Área do cliente leva.
        $rotaConta = Auth::guard('cliente')->check() ? 'portal.inicio' : 'portal.login';

        // O que aponta para fora do site (portal financeiro antigo e os dois servidores de
        // rastreamento) e os textos que o visitante lê no caminho. O painel grava cada um; sem
        // nada gravado, o config conduz — inclusive nos rótulos, que nunca ficam presos no HTML.
        $textosAcesso = config('golden.acessos.textos');
        $acessos = [
            'financeiro_url' => $pegar('acesso_financeiro_url', config('golden.acessos.financeiro')),
            'servidor_1_url' => $pegar('acesso_servidor_1_url', config('golden.acessos.servidor_1')),
            'servidor_2_url' => $pegar('acesso_servidor_2_url', config('golden.acessos.servidor_2')),
            'botao' => $pegar('acesso_botao_rotulo', $textosAcesso['botao']),
            'conta_rotulo' => $pegar('acesso_conta_rotulo', $textosAcesso['conta']),
            'area_titulo' => $pegar('acesso_area_titulo', $textosAcesso['area']),
            'portal_titulo' => $pegar('acesso_portal_titulo', $textosAcesso['portal_titulo']),
            'portal_desc' => $pegar('acesso_portal_desc', $textosAcesso['portal_desc']),
            'financeiro_titulo' => $pegar('acesso_financeiro_titulo', $textosAcesso['financeiro_titulo']),
            'financeiro_desc' => $pegar('acesso_financeiro_desc', $textosAcesso['financeiro_desc']),
            'modal_titulo' => $pegar('acesso_modal_titulo', $textosAcesso['modal_titulo']),
            'modal_desc' => $pegar('acesso_modal_desc', $textosAcesso['modal_desc']),
            'servidor_1_titulo' => $pegar('acesso_servidor_1_titulo', $textosAcesso['servidor_1_titulo']),
            'servidor_2_titulo' => $pegar('acesso_servidor_2_titulo', $textosAcesso['servidor_2_titulo']),
            'servidor_1_icone' => $pegar('acesso_servidor_1_icone', $textosAcesso['servidor_1_icone']),
            'servidor_2_icone' => $pegar('acesso_servidor_2_icone', $textosAcesso['servidor_2_icone']),
            'ajuda' => $pegar('acesso_ajuda', $textosAcesso['ajuda']),
            'ajuda_link' => $pegar('acesso_ajuda_link', $textosAcesso['ajuda_link']),
        ];

        return $this->site = [
            'marca' => config('golden.marca'),
            'credito' => config('golden.credito'),
            'whatsapp' => $whatsapp,
            'whatsapp_url' => wa_url(null, $whatsapp),
            'whatsapp_box' => [
                'ativo' => $boxAtivo,
                'titulo' => $pegar('whatsapp_box_titulo', $box['titulo']),
                'subtitulo' => $pegar('whatsapp_box_subtitulo', $box['subtitulo']),
                'abertura' => $pegar('whatsapp_box_abertura', $box['abertura']),
            ],
            'canais' => $canais,
            'assistente_na_caixa' => $boxAtivo || $canais === [],
            'email' => $pegar('contato_email', config('golden.email')),
            'telefone' => $pegar('contato_telefone', implode(' · ', config('golden.contato.telefones'))),
            'endereco' => $pegar('empresa_endereco', config('golden.contato.endereco')),
            'cidade_uf' => $pegar('empresa_cidade', config('golden.contato.cidade_uf')),
            'cep' => $pegar('empresa_cep', config('golden.contato.cep')),
            'pagamentos' => config('golden.pagamentos'),
            'parceiros' => config('golden.parceiros_m2m'),
            'loja_ativa' => $lojaAtiva,
            'carrinho_itens' => $lojaAtiva ? Carrinho::daSessao()->totalItens() : 0,
            'nav' => $this->montarMenu($lojaAtiva, $rotaConta, $acessos),
            'portal_url' => route($rotaConta),
            'acessos' => $acessos,
            'tracking' => $pegar('tracking_enabled', '0') === '1',
            'ga_id' => $pegar('ga_measurement_id'),
            'meta_pixel' => $pegar('meta_pixel_id'),
        ];
    }

    /**
     * Árvore do menu do site: o esqueleto vem de config('golden.nav') e os grupos
     * dinâmicos (categorias da loja, páginas do CMS e a entrada do portal) são
     * resolvidos aqui, para que header e gaveta mobile compartilhem os mesmos itens.
     */
    private function montarMenu(bool $lojaAtiva, string $rotaConta, array $acessos): array
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
            $endereco = fn (array $f) => $f['url'] ?? (isset($f['rota']) ? route($f['rota'], $f['param'] ?? []) : null);

            $filhos = collect($item['filhos'] ?? [])
                ->map(fn ($f) => [
                    'titulo' => $f['titulo'],
                    'url' => $endereco($f),
                    'externo' => (bool) ($f['externo'] ?? false),
                    'ativo' => isset($f['rota']) ? $bate($f['rota'], $f['param'] ?? null) : false,
                ])
                ->all();

            $ativo = isset($item['rota'])
                ? $bate($item['rota'], $item['param'] ?? null)
                    || ($pai && ($item['prefixo'] ?? null) !== null && str_starts_with($rotaAtual, $item['prefixo']))
                : false;

            return [
                'titulo' => $item['titulo'],
                'url' => $endereco($item),
                'externo' => (bool) ($item['externo'] ?? false),
                'acesso' => (bool) ($item['acesso'] ?? false),
                'ativo' => $ativo || collect($filhos)->contains('ativo', true),
                'filhos' => $filhos,
            ];
        };

        return collect(config('golden.nav'))
            ->reject(fn ($item) => ($item['grupo'] ?? null) === 'loja' && ! $lojaAtiva)
            ->map(function ($item) use ($montar, $paginas, $rotaConta, $acessos) {
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
                            ['titulo' => 'Vídeos tutoriais', 'rota' => 'site.videos'],
                            ['titulo' => 'Política de privacidade', 'rota' => 'site.privacidade'],
                        ])
                        ->values()
                        ->all();
                }

                if (($item['grupo'] ?? null) === 'conta') {
                    $item['rota'] = $rotaConta;
                    // No topo o item vira ícone com submenu (ver o partial do cabeçalho); na
                    // gaveta, o mesmo item abre as duas portas em acordeão. `acesso` é o que
                    // diz ao partial que esta entrada tem tratamento próprio. O título do
                    // grupo e das portas vem dos textos editáveis em Configurações.
                    $item['acesso'] = true;
                    $item['titulo'] = $acessos['area_titulo'];
                    $item['filhos'] = [
                        ['titulo' => $acessos['portal_titulo'], 'rota' => $rotaConta],
                        ['titulo' => $acessos['financeiro_titulo'], 'url' => $acessos['financeiro_url'], 'externo' => true],
                    ];
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
