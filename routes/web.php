<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AttributionController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContractController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\FunnelController as AdminFunnelController;
use App\Http\Controllers\Admin\GoogleAdsAuthController;
use App\Http\Controllers\Admin\GoogleAdsCampanhaController;
use App\Http\Controllers\Admin\GoogleAdsController;
use App\Http\Controllers\Admin\GuiaController;
use App\Http\Controllers\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SiteContentController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalChamadoController;
use App\Http\Controllers\Portal\PortalContaController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\PortalContratoController;
use App\Http\Controllers\Portal\PortalDepoimentoController;
use App\Http\Controllers\Portal\PortalPedidoController;
use App\Http\Controllers\Site\FunnelController;
use App\Http\Controllers\Site\LeadController as SiteLeadController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\RastreioController as SiteRastreioController;
use App\Http\Controllers\Site\StoreController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site público Golden Rastreamento (multipágina)
|--------------------------------------------------------------------------
*/
Route::get('/', [PageController::class, 'home'])->name('site.home');
Route::get('/solucoes', [PageController::class, 'solucoes'])->name('site.solucoes');
Route::get('/para-quem', [PageController::class, 'segmentos'])->name('site.segmentos');
Route::get('/planos', [PageController::class, 'planos'])->name('site.planos');
Route::get('/como-funciona', [PageController::class, 'comoFunciona'])->name('site.como-funciona');
Route::get('/faq', [PageController::class, 'faq'])->name('site.faq');
Route::get('/videos', [PageController::class, 'videos'])->name('site.videos');
Route::get('/contato', [PageController::class, 'contato'])->name('site.contato');
Route::get('/privacidade', [PageController::class, 'privacidade'])->name('site.privacidade');

// Funil de vendas pago: não entra no menu, no rodapé nem na busca — só chega quem tem o link.
Route::get('/c/{slug}', [FunnelController::class, 'show'])->name('site.funil');

// Captura de leads (validação + rate limit + honeypot)
Route::post('/leads', [SiteLeadController::class, 'store'])
    ->middleware('throttle:8,1')
    ->name('site.leads.store');

// Farol do site: cada clique, kit, ligação e formulário iniciado do visitante.
Route::post('/rastreio/evento', [SiteRastreioController::class, 'evento'])
    ->middleware('throttle:120,1')
    ->name('site.rastreio.evento');

/*
|--------------------------------------------------------------------------
| Loja virtual (produtos/ERP) — pode ser desativada pelo painel
|--------------------------------------------------------------------------
*/
Route::middleware('loja')->group(function () {
    Route::get('/loja', [StoreController::class, 'index'])->name('site.loja.index');

    // Sacola: adicionar, ajustar quantidade e remover mantêm o carrinho na sessão.
    Route::get('/loja/carrinho', [StoreController::class, 'carrinho'])->name('site.loja.carrinho');
    Route::post('/loja/carrinho', [StoreController::class, 'adicionar'])
        ->middleware('throttle:40,1')->name('site.loja.carrinho.adicionar');
    Route::post('/loja/carrinho/quantidade', [StoreController::class, 'atualizar'])
        ->middleware('throttle:40,1')->name('site.loja.carrinho.atualizar');
    Route::post('/loja/carrinho/remover', [StoreController::class, 'remover'])
        ->middleware('throttle:40,1')->name('site.loja.carrinho.remover');

    // Pedido: formulário, gravação e acompanhamento por número + token.
    Route::get('/loja/checkout', [StoreController::class, 'checkout'])->name('site.loja.checkout');
    Route::post('/loja/pedido', [StoreController::class, 'registrar'])
        ->middleware('throttle:8,1')->name('site.loja.pedido.registrar');
    Route::get('/loja/pedido/{numero}/{token}', [StoreController::class, 'pedido'])
        ->where(['numero' => 'GR\d{2}-\d{4,6}', 'token' => '[A-Za-z0-9]{20,40}'])
        ->name('site.loja.pedido');

    // Produto precisa ficar por último: {slug} aceita qualquer caminho de um segmento.
    Route::get('/loja/{slug}', [StoreController::class, 'show'])->name('site.loja.produto');
});

/*
|--------------------------------------------------------------------------
| Portal do cliente (guard próprio: nada aqui atravessa para o painel)
|--------------------------------------------------------------------------
*/
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('entrar', [PortalAuthController::class, 'entrar'])->name('login');
    Route::post('entrar', [PortalAuthController::class, 'autenticar'])
        ->middleware('throttle:10,1')->name('login.post');

    Route::get('cadastro', [PortalAuthController::class, 'cadastro'])->name('cadastro');
    Route::post('cadastro', [PortalAuthController::class, 'registrar'])
        ->middleware('throttle:8,1')->name('cadastro.post');

    // Recuperação sem broker: o link é assinado e expira, então não há tabela de tokens.
    Route::get('esqueci-senha', [PortalAuthController::class, 'esquecer'])->name('senha.esquecer');
    Route::post('esqueci-senha', [PortalAuthController::class, 'esquecerSenha'])
        ->middleware('throttle:6,1')->name('senha.esquecer.post');
    Route::get('redefinir-senha', [PortalAuthController::class, 'redefinir'])->name('senha.redefinir');
    Route::post('redefinir-senha', [PortalAuthController::class, 'salvarSenhaNova'])
        ->middleware('throttle:6,1')->name('senha.redefinir.post');

    Route::middleware('cliente')->group(function () {
        Route::get('/', [PortalController::class, 'inicio'])->name('inicio');
        Route::post('sair', [PortalAuthController::class, 'sair'])->name('sair');

        Route::get('pedidos', [PortalPedidoController::class, 'index'])->name('pedidos');
        Route::get('pedidos/{pedido}', [PortalPedidoController::class, 'show'])
            ->whereNumber('pedido')->name('pedidos.show');

        Route::get('chamados', [PortalChamadoController::class, 'index'])->name('chamados');
        Route::get('chamados/abrir', [PortalChamadoController::class, 'criar'])->name('chamados.criar');
        Route::post('chamados', [PortalChamadoController::class, 'registrar'])
            ->middleware('throttle:12,1')->name('chamados.registrar');
        Route::get('chamados/{chamado}', [PortalChamadoController::class, 'show'])
            ->whereNumber('chamado')->name('chamados.show');
        Route::post('chamados/{chamado}/resposta', [PortalChamadoController::class, 'responder'])
            ->whereNumber('chamado')->middleware('throttle:20,1')->name('chamados.responder');

        Route::get('contratos', [PortalContratoController::class, 'index'])->name('contratos');
        Route::get('contratos/{contrato}/arquivo', [PortalContratoController::class, 'arquivo'])
            ->whereNumber('contrato')->name('contratos.arquivo');

        Route::get('dados', [PortalContaController::class, 'dados'])->name('dados');
        Route::post('dados', [PortalContaController::class, 'salvarDados'])->name('dados.salvar');
        Route::post('senha', [PortalContaController::class, 'salvarSenha'])->name('senha.salvar');

        // O cliente conta a experiência dele; a equipe aprova antes de ir ao carrossel do site.
        Route::get('depoimento', [PortalDepoimentoController::class, 'mostrar'])->name('depoimento');
        Route::post('depoimento', [PortalDepoimentoController::class, 'store'])
            ->middleware('throttle:6,1')->name('depoimento.enviar');
    });
});

/*
|--------------------------------------------------------------------------
| Páginas dinâmicas do CMS (conteúdo criado no painel) — sempre por último
|--------------------------------------------------------------------------
*/
Route::get('/p/{slug}', [PageController::class, 'pagina'])->name('site.pagina');

/*
|--------------------------------------------------------------------------
| Autenticação do painel
|--------------------------------------------------------------------------
*/
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Conectar a conta Google Ads de quem está no painel
|--------------------------------------------------------------------------
|
| Fica fora do prefixo /admin porque o Google devolve no caminho exato que foi
| cadastrado na tela de OAuth (`${APP_URL}/auth/google-ads/callback`) — mudar o
| endereço é refazer o cadastro no Google. Quem faz o trabalho é o controller.
*/
Route::prefix('auth/google-ads')->name('google-ads.')->middleware(['auth'])->group(function () {
    Route::get('/redirect', [GoogleAdsAuthController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [GoogleAdsAuthController::class, 'callback'])->name('callback');
});

/*
|--------------------------------------------------------------------------
| Painel Administrativo
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // O guia de uso, aberto para qualquer papel: quem atende lead também precisa consultar
    // como o sistema funciona, e não há nada aqui que um vendedor não possa ler.
    Route::get('/guia', [GuiaController::class, 'index'])->name('guia.index');

    // Envios dos campos de arrastar-e-soltar (imagem, vídeo, documento) do painel.
    Route::post('/uploads', [UploadController::class, 'store'])->middleware('throttle:40,1')->name('uploads.store');
    Route::delete('/uploads', [UploadController::class, 'destroy'])->middleware('throttle:40,1')->name('uploads.destroy');

    // Comercial: Leads (admin + vendedor)
    Route::prefix('leads')->name('leads.')->middleware('role:admin,vendedor')->group(function () {
        Route::get('/', [AdminLeadController::class, 'index'])->name('index');
        Route::get('/exportar', [AdminLeadController::class, 'export'])->name('export');
        Route::get('/{lead}', [AdminLeadController::class, 'show'])->name('show');
        Route::post('/{lead}/status', [AdminLeadController::class, 'atualizarStatus'])->name('status');
        Route::post('/{lead}/nota', [AdminLeadController::class, 'nota'])->name('nota');
        Route::post('/{lead}/converter', [AdminLeadController::class, 'converter'])->name('converter');
        Route::post('/{lead}', [AdminLeadController::class, 'atualizar'])->name('update');
        Route::delete('/{lead}', [AdminLeadController::class, 'destroy'])->name('destroy')->middleware('role:admin');
    });

    // De onde veio cada visita, clique e cadastro do site.
    Route::prefix('atribuicao')->name('atribuicao.')->middleware('role:admin,vendedor')->group(function () {
        Route::get('/', [AttributionController::class, 'index'])->name('index');
        Route::get('/exportar', [AttributionController::class, 'export'])->name('export');
        Route::get('/{visita}', [AttributionController::class, 'show'])->name('show');
    });

    Route::prefix('campanhas')->name('campanhas.')->middleware('role:admin,vendedor')->group(function () {
        Route::get('/', [CampaignController::class, 'index'])->name('index');
        Route::get('/nova', [CampaignController::class, 'create'])->name('create');
        Route::post('/', [CampaignController::class, 'store'])->name('store');
        Route::get('/{campaign}/editar', [CampaignController::class, 'edit'])->name('edit');
        Route::post('/{campaign}', [CampaignController::class, 'update'])->name('update');
        Route::post('/{campaign}/toggle', [CampaignController::class, 'toggle'])->name('toggle');
        Route::delete('/{campaign}', [CampaignController::class, 'destroy'])->name('destroy')->middleware('role:admin');
    });

    // Google Ads: a conta que cada um conecta, a métrica dela e o criativo do anúncio.
    // A leitura é sempre da cache preenchida pela fila; aqui só a escrita passa direto ao
    // Google, e mesmo assim depois de uma operação simulada (`validate_only`).
    Route::prefix('google-ads')->name('google-ads.')->middleware('role:admin,vendedor')->group(function () {
        Route::get('/', [GoogleAdsController::class, 'index'])->name('index');
        Route::get('/criativo', [GoogleAdsController::class, 'criativo'])->name('criativo');
        Route::post('/campanhas/{campanha}/status', [GoogleAdsCampanhaController::class, 'mudarStatus'])
            ->whereNumber('campanha')->name('campanhas.status');
    });

    // Páginas de funil: a campanha de divulgação que só quem tem o link vê. Montagem por
    // blocos, pixels por página e o contador dela — nada disso existe no site fixo.
    Route::prefix('funil')->name('funil.')->middleware('role:admin')->group(function () {
        Route::get('/', [AdminFunnelController::class, 'index'])->name('index');
        Route::get('/nova', [AdminFunnelController::class, 'create'])->name('create');
        Route::post('/', [AdminFunnelController::class, 'store'])->name('store');
        // 'nova' é um segmento só: vir depois dele, {pagina} engoliria a tela de criação.
        Route::get('/{pagina}', [AdminFunnelController::class, 'show'])->name('show');
        Route::get('/{pagina}/editar', [AdminFunnelController::class, 'edit'])->name('edit');
        Route::post('/{pagina}', [AdminFunnelController::class, 'update'])->name('update');
        Route::post('/{pagina}/toggle', [AdminFunnelController::class, 'toggle'])->name('toggle');
        Route::delete('/{pagina}', [AdminFunnelController::class, 'destroy'])->name('destroy');

        Route::get('/{pagina}/blocos/novo', [AdminFunnelController::class, 'blocoCreate'])->name('blocos.create');
        Route::post('/{pagina}/blocos', [AdminFunnelController::class, 'blocoStore'])->name('blocos.store');
        Route::get('/{pagina}/blocos/{bloco}/editar', [AdminFunnelController::class, 'blocoEdit'])->name('blocos.edit');
        Route::post('/{pagina}/blocos/{bloco}', [AdminFunnelController::class, 'blocoUpdate'])->name('blocos.update');
        Route::delete('/{pagina}/blocos/{bloco}', [AdminFunnelController::class, 'blocoDestroy'])->name('blocos.destroy');
    });

    // CRM: Clientes
    Route::prefix('clientes')->name('clientes.')->middleware('role:admin,vendedor')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/novo', [CustomerController::class, 'create'])->name('create');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::get('/{cliente}', [CustomerController::class, 'show'])->name('show');
        Route::get('/{cliente}/editar', [CustomerController::class, 'edit'])->name('edit');
        Route::post('/{cliente}', [CustomerController::class, 'update'])->name('update');
        Route::post('/{cliente}/interacao', [CustomerController::class, 'interaction'])->name('interacao');
        Route::delete('/{cliente}', [CustomerController::class, 'destroy'])->name('destroy')->middleware('role:admin');
    });

    // ERP: Catálogo / Produtos / Estoque / Fornecedores
    Route::prefix('catalogo')->name('catalogo.')->middleware('role:admin')->group(function () {
        Route::prefix('categorias')->name('categorias.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::get('/nova', [CategoryController::class, 'create'])->name('create');
            Route::post('/', [CategoryController::class, 'store'])->name('store');
            Route::get('/{category}/editar', [CategoryController::class, 'edit'])->name('edit');
            Route::post('/{category}', [CategoryController::class, 'update'])->name('update');
            Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('produtos')->name('produtos.')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('/novo', [ProductController::class, 'create'])->name('create');
            Route::post('/', [ProductController::class, 'store'])->name('store');
            Route::get('/{produto}/editar', [ProductController::class, 'edit'])->name('edit');
            Route::post('/{produto}', [ProductController::class, 'update'])->name('update');
            Route::get('/{produto}/estoque', [ProductController::class, 'estoqueForm'])->name('estoque.show');
            Route::post('/{produto}/estoque', [ProductController::class, 'estoque'])->name('estoque');
            Route::delete('/{produto}', [ProductController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('fornecedores')->name('fornecedores.')->group(function () {
            Route::get('/', [SupplierController::class, 'index'])->name('index');
            Route::get('/novo', [SupplierController::class, 'create'])->name('create');
            Route::post('/', [SupplierController::class, 'store'])->name('store');
            Route::get('/{fornecedor}/editar', [SupplierController::class, 'edit'])->name('edit');
            Route::post('/{fornecedor}', [SupplierController::class, 'update'])->name('update');
            Route::delete('/{fornecedor}', [SupplierController::class, 'destroy'])->name('destroy');
        });
    });

    // Vendas / Pedidos
    Route::prefix('pedidos')->name('pedidos.')->middleware('role:admin,vendedor')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/{pedido}', [OrderController::class, 'show'])->name('show');
        Route::post('/{pedido}/status', [OrderController::class, 'atualizarStatus'])->name('status');
        Route::post('/{pedido}/documentos', [OrderController::class, 'documentos'])->name('documentos');
        Route::delete('/{pedido}', [OrderController::class, 'destroy'])->name('destroy')->middleware('role:admin');
    });

    // Atendimento: chamados abertos no portal e contratos publicados para o cliente ler.
    Route::prefix('chamados')->name('chamados.')->middleware('role:admin,vendedor')->group(function () {
        Route::get('/', [TicketController::class, 'index'])->name('index');
        Route::get('/{chamado}', [TicketController::class, 'show'])->name('show');
        Route::post('/{chamado}/resposta', [TicketController::class, 'responder'])->name('responder');
        Route::post('/{chamado}/status', [TicketController::class, 'atualizarStatus'])->name('status');
        Route::delete('/{chamado}', [TicketController::class, 'destroy'])->name('destroy')->middleware('role:admin');
    });

    Route::prefix('contratos')->name('contratos.')->middleware('role:admin')->group(function () {
        Route::get('/', [ContractController::class, 'index'])->name('index');
        Route::get('/novo', [ContractController::class, 'create'])->name('create');
        Route::post('/', [ContractController::class, 'store'])->name('store');
        Route::get('/{contrato}/editar', [ContractController::class, 'edit'])->name('edit');
        Route::post('/{contrato}', [ContractController::class, 'update'])->name('update');
        Route::delete('/{contrato}', [ContractController::class, 'destroy'])->name('destroy');
    });

    // CMS: Páginas / Imagens e textos do site / Depoimentos / FAQs
    Route::prefix('conteudo')->name('conteudo.')->middleware('role:admin')->group(function () {
        Route::prefix('paginas')->name('paginas.')->group(function () {
            Route::get('/', [AdminPageController::class, 'index'])->name('index');
            Route::get('/novo', [AdminPageController::class, 'create'])->name('create');
            Route::post('/', [AdminPageController::class, 'store'])->name('store');
            Route::get('/{pagina}/editar', [AdminPageController::class, 'edit'])->name('edit');
            Route::post('/{pagina}', [AdminPageController::class, 'update'])->name('update');
            Route::delete('/{pagina}', [AdminPageController::class, 'destroy'])->name('destroy');
        });

        // Cada fotografia exposta no site, organizada pela página onde aparece.
        Route::prefix('imagens')->name('imagens.')->group(function () {
            Route::get('/', [SiteContentController::class, 'imagens'])->name('index');
            Route::post('/', [SiteContentController::class, 'salvarImagens'])->name('update');
        });

        // Abertura (rótulo, eyebrow, título, subtítulo) das páginas fixas do site.
        Route::prefix('textos')->name('textos.')->group(function () {
            Route::get('/', [SiteContentController::class, 'textos'])->name('index');
            Route::post('/', [SiteContentController::class, 'salvarTextos'])->name('update');
        });

        Route::prefix('depoimentos')->name('depoimentos.')->group(function () {
            Route::get('/', [TestimonialController::class, 'index'])->name('index');
            Route::get('/novo', [TestimonialController::class, 'create'])->name('create');
            Route::post('/', [TestimonialController::class, 'store'])->name('store');
            // Antes das rotas por id: "importar-google" é um segmento só e cairia no {depoimento}.
            Route::post('/importar-google', [TestimonialController::class, 'importar'])->name('importar');
            Route::get('/{depoimento}/editar', [TestimonialController::class, 'edit'])->name('edit');
            Route::post('/{depoimento}', [TestimonialController::class, 'update'])->name('update');
            Route::delete('/{depoimento}', [TestimonialController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('faqs')->name('faqs.')->group(function () {
            Route::get('/', [FaqController::class, 'index'])->name('index');
            Route::get('/novo', [FaqController::class, 'create'])->name('create');
            Route::post('/', [FaqController::class, 'store'])->name('store');
            Route::get('/{faq}/editar', [FaqController::class, 'edit'])->name('edit');
            Route::post('/{faq}', [FaqController::class, 'update'])->name('update');
            Route::delete('/{faq}', [FaqController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('videos')->name('videos.')->group(function () {
            Route::get('/', [VideoController::class, 'index'])->name('index');
            Route::get('/novo', [VideoController::class, 'create'])->name('create');
            Route::post('/', [VideoController::class, 'store'])->name('store');
            Route::get('/{video}/editar', [VideoController::class, 'edit'])->name('edit');
            Route::post('/{video}', [VideoController::class, 'update'])->name('update');
            Route::delete('/{video}', [VideoController::class, 'destroy'])->name('destroy');
        });
    });

    // Auditoria, usuários e configurações (somente admin)
    Route::get('/auditoria', [ActivityController::class, 'index'])->name('activities.index')->middleware('role:admin');

    Route::prefix('usuarios')->name('usuarios.')->middleware('role:admin')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/novo', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}/editar', [UserController::class, 'edit'])->name('edit');
        Route::post('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('configuracoes')->name('settings.')->middleware('role:admin')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->name('index');
        Route::post('/', [SettingController::class, 'update'])->name('update');
        Route::post('/loja', [SettingController::class, 'loja'])->name('loja');
        Route::post('/loja-regras', [SettingController::class, 'regras'])->name('loja-regras');
        Route::post('/whatsapp', [SettingController::class, 'whatsapp'])->name('whatsapp');
        Route::post('/chaves', [SettingController::class, 'chaves'])->name('chaves');
    });
});
