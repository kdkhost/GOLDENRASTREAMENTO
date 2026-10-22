<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Site\LeadController as SiteLeadController;
use App\Http\Controllers\Site\PageController;
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
| Autenticação do painel
|--------------------------------------------------------------------------
*/
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Painel Administrativo — Painel de Leads (AdminLTE 4)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Leads (acessível a admin e vendedor)
    Route::prefix('leads')->name('leads.')->middleware('role:admin,vendedor')->group(function () {
        Route::get('/', [AdminLeadController::class, 'index'])->name('index');
        Route::get('/exportar', [AdminLeadController::class, 'export'])->name('export');
        Route::get('/{lead}', [AdminLeadController::class, 'show'])->name('show');
        Route::post('/{lead}/status', [AdminLeadController::class, 'atualizarStatus'])->name('status');
        Route::post('/{lead}/nota', [AdminLeadController::class, 'nota'])->name('nota');
        Route::post('/{lead}', [AdminLeadController::class, 'atualizar'])->name('update');
        Route::delete('/{lead}', [AdminLeadController::class, 'destroy'])->name('destroy')->middleware('role:admin');
    });

    // Campanhas
    Route::prefix('campanhas')->name('campanhas.')->middleware('role:admin,vendedor')->group(function () {
        Route::get('/', [CampaignController::class, 'index'])->name('index');
        Route::post('/', [CampaignController::class, 'store'])->name('store');
        Route::post('/{campaign}/toggle', [CampaignController::class, 'toggle'])->name('toggle');
        Route::delete('/{campaign}', [CampaignController::class, 'destroy'])->name('destroy')->middleware('role:admin');
    });

    // Auditoria (somente admin)
    Route::get('/auditoria', [ActivityController::class, 'index'])->name('activities.index')->middleware('role:admin');

    // Usuários & configurações (somente admin)
    Route::prefix('usuarios')->name('usuarios.')->middleware('role:admin')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::post('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('configuracoes')->name('settings.')->middleware('role:admin')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->name('index');
        Route::post('/', [SettingController::class, 'update'])->name('update');
    });
});
