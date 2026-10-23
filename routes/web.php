<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SiteContentController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Site\LeadController as SiteLeadController;
use App\Http\Controllers\Site\PageController;
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
Route::get('/contato', [PageController::class, 'contato'])->name('site.contato');
Route::get('/privacidade', [PageController::class, 'privacidade'])->name('site.privacidade');

// Captura de leads (validação + rate limit + honeypot)
Route::post('/leads', [SiteLeadController::class, 'store'])
    ->middleware('throttle:8,1')
    ->name('site.leads.store');

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
| Painel Administrativo
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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

    Route::prefix('campanhas')->name('campanhas.')->middleware('role:admin,vendedor')->group(function () {
        Route::get('/', [CampaignController::class, 'index'])->name('index');
        Route::get('/nova', [CampaignController::class, 'create'])->name('create');
        Route::post('/', [CampaignController::class, 'store'])->name('store');
        Route::get('/{campaign}/editar', [CampaignController::class, 'edit'])->name('edit');
        Route::post('/{campaign}', [CampaignController::class, 'update'])->name('update');
        Route::post('/{campaign}/toggle', [CampaignController::class, 'toggle'])->name('toggle');
        Route::delete('/{campaign}', [CampaignController::class, 'destroy'])->name('destroy')->middleware('role:admin');
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
    });
});
