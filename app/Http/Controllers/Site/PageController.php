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
            'depoimentos' => Testimonial::aprovadosParaSite(6),
        ]);
    }

    public function solucoes(): View
    {
        return view('site.solucoes', [
            'beneficios' => config('golden.beneficios'),
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
        ]);
    }

    public function comoFunciona(): View
    {
        return view('site.como-funciona', [
            'passos' => config('golden.passos'),
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
        ]);
    }

    public function privacidade(): View
    {
        return view('site.privacidade');
    }

    public function pagina(string $slug): View
    {
        $pagina = Page::paraSite($slug);
        abort_if($pagina === null, 404);

        return view('site.pagina', ['pagina' => $pagina]);
    }
}
