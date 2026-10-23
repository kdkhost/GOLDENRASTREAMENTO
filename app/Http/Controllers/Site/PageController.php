<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Testimonial;
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
            'depoimentos' => Testimonial::aprovados()->limit(6)->get(),
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
            'faq' => Faq::paraSite(),
            'depoimentos' => Testimonial::aprovados()->limit(3)->get(),
        ]);
    }

    public function comoFunciona(): View
    {
        return view('site.como-funciona', [
            'passos' => config('golden.passos'),
            'faq' => Faq::paraSite(),
        ]);
    }

    public function faq(): View
    {
        return view('site.faq', [
            'faq' => Faq::paraSite(),
        ]);
    }

    public function contato(): View
    {
        return view('site.contato', [
            'kits' => config('golden.kits'),
            'perfis' => config('golden.perfis'),
            'depoimentos' => Testimonial::aprovados()->limit(3)->get(),
        ]);
    }

    public function privacidade(): View
    {
        return view('site.privacidade');
    }

    public function pagina(string $slug): View
    {
        $pagina = Page::where('slug', $slug)->where('publicada', true)->firstOrFail();

        return view('site.pagina', ['pagina' => $pagina]);
    }
}
