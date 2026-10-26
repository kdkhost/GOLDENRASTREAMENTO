<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * As duas telas de Google Ads do painel: a métrica da conta conectada e o editor do anúncio.
 *
 * São carcaças. Todo o trabalho — ler a cache, decidir se enfileira um job, filtrar, paginar,
 * desenhar o preview — é dos componentes Livewire que cada uma embute, do mesmo jeito que as
 * listagens do painel delegam aos seus controllers. A tela não chama a API: o job chama.
 */
class GoogleAdsController extends Controller
{
    public function index(): View
    {
        return view('admin.google-ads.index');
    }

    public function criativo(): View
    {
        return view('admin.google-ads.criativo');
    }
}
