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
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\TestimonialController;
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
    Route::get('/loja/{slug}', [StoreController::class, 'show'])->name('site.loja.produto');
    Route::post('/loja/checkout', [StoreController::class, 'checkout'])
        ->middleware('throttle:10,1')
        ->name('site.loja.checkout');
    Route::get('/loja/pedido/{numero}', [StoreController::class, 'confirmacao'])->name('site.loja.confirmacao');
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
        Route::post('/', [CampaignController::class, 'store'])->name('store');
        Route::post('/{campaign}/toggle', [CampaignController::class, 'toggle'])->name('toggle');
        Route::delete('/{campaign}', [CampaignController::class, 'destroy'])->name('destroy')->middleware('role:admin');
    });

    // CRM: Clientes
    Route::prefix('clientes')->name('clientes.')->middleware('role:admin,vendedor')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::get('/{cliente}', [CustomerController::class, 'show'])->name('show');
        Route::post('/{cliente}', [CustomerController::class, 'update'])->name('update');
        Route::post('/{cliente}/interacao', [CustomerController::class, 'interaction'])->name('interacao');
        Route::delete('/{cliente}', [CustomerController::class, 'destroy'])->name('destroy')->middleware('role:admin');
    });

    // ERP: Catálogo / Produtos / Estoque / Fornecedores
    Route::prefix('catalogo')->name('catalogo.')->middleware('role:admin')->group(function () {
        Route::prefix('categorias')->name('categorias.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::post('/', [CategoryController::class, 'store'])->name('store');
            Route::post('/{category}', [CategoryController::class, 'update'])->name('update');
            Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('produtos')->name('produtos.')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('/novo', [ProductController::class, 'create'])->name('create');
            Route::post('/', [ProductController::class, 'store'])->name('store');
            Route::get('/{produto}/editar', [ProductController::class, 'edit'])->name('edit');
            Route::post('/{produto}', [ProductController::class, 'update'])->name('update');
            Route::post('/{produto}/estoque', [ProductController::class, 'estoque'])->name('estoque');
            Route::delete('/{produto}', [ProductController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('fornecedores')->name('fornecedores.')->group(function () {
            Route::get('/', [SupplierController::class, 'index'])->name('index');
            Route::post('/', [SupplierController::class, 'store'])->name('store');
            Route::post('/{fornecedor}', [SupplierController::class, 'update'])->name('update');
            Route::delete('/{fornecedor}', [SupplierController::class, 'destroy'])->name('destroy');
        });
    });

    // Vendas / Pedidos
    Route::prefix('pedidos')->name('pedidos.')->middleware('role:admin,vendedor')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/{pedido}', [OrderController::class, 'show'])->name('show');
        Route::post('/{pedido}/status', [OrderController::class, 'atualizarStatus'])->name('status');
        Route::delete('/{pedido}', [OrderController::class, 'destroy'])->name('destroy')->middleware('role:admin');
    });

    // CMS: Páginas / Depoimentos / FAQs
    Route::prefix('conteudo')->name('conteudo.')->middleware('role:admin')->group(function () {
        Route::prefix('paginas')->name('paginas.')->group(function () {
            Route::get('/', [AdminPageController::class, 'index'])->name('index');
            Route::get('/novo', [AdminPageController::class, 'create'])->name('create');
            Route::post('/', [AdminPageController::class, 'store'])->name('store');
            Route::get('/{pagina}/editar', [AdminPageController::class, 'edit'])->name('edit');
            Route::post('/{pagina}', [AdminPageController::class, 'update'])->name('update');
            Route::delete('/{pagina}', [AdminPageController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('depoimentos')->name('depoimentos.')->group(function () {
            Route::get('/', [TestimonialController::class, 'index'])->name('index');
            Route::post('/', [TestimonialController::class, 'store'])->name('store');
            Route::post('/{depoimento}', [TestimonialController::class, 'update'])->name('update');
            Route::delete('/{depoimento}', [TestimonialController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('faqs')->name('faqs.')->group(function () {
            Route::get('/', [FaqController::class, 'index'])->name('index');
            Route::post('/', [FaqController::class, 'store'])->name('store');
            Route::post('/{faq}', [FaqController::class, 'update'])->name('update');
            Route::delete('/{faq}', [FaqController::class, 'destroy'])->name('destroy');
        });
    });

    // Auditoria, usuários e configurações (somente admin)
    Route::get('/auditoria', [ActivityController::class, 'index'])->name('activities.index')->middleware('role:admin');

    Route::prefix('usuarios')->name('usuarios.')->middleware('role:admin')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::post('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('configuracoes')->name('settings.')->middleware('role:admin')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->name('index');
        Route::post('/', [SettingController::class, 'update'])->name('update');
        Route::post('/loja', [SettingController::class, 'loja'])->name('loja');
    });
});
