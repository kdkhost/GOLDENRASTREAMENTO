<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('site.home', [
            'beneficios' => config('golden.beneficios'),
            'segmentos' => config('golden.segmentos'),
            'kits' => config('golden.kits'),
            'passos' => config('golden.passos'),
        ]);
    }

    public function solucoes(): View
    {
        return view('site.solucoes', [
            'beneficios' => config('golden.beneficios'),
            'passos' => config('golden.passos'),
        ]);
    }

    public function segmentos(): View
    {
        return view('site.segmentos', [
            'segmentos' => config('golden.segmentos'),
        ]);
    }

    public function planos(): View
    {
        return view('site.planos', [
            'kits' => config('golden.kits'),
            'faq' => config('golden.faq'),
        ]);
    }

    public function comoFunciona(): View
    {
        return view('site.como-funciona', [
            'passos' => config('golden.passos'),
            'faq' => config('golden.faq'),
        ]);
    }

    public function faq(): View
    {
        return view('site.faq', [
            'faq' => config('golden.faq'),
        ]);
    }

    public function contato(): View
    {
        return view('site.contato', [
            'kits' => config('golden.kits'),
            'perfis' => config('golden.perfis'),
        ]);
    }

    public function privacidade(): View
    {
        return view('site.privacidade');
    }
}
