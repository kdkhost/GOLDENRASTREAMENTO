<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Testimonial;
use App\Models\Video;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
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
            'equipamento' => config('golden.equipamento_info'),
            'faq' => Faq::paraSite(),
            'depoimentos' => Testimonial::aprovadosParaSite(6),
        ]);
    }

    public function solucoes(): View
    {
        return view('site.solucoes', [
            'beneficios' => config('golden.beneficios'),
            'equipamento' => config('golden.equipamento_info'),
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

    /**
     * A galeria inteira vem do cache e o filtro é feito sobre o array: trocar de categoria
     * não consulta o banco, e a contagem de cada chip sai da mesma passada.
     */
    public function videos(Request $request): View
    {
        $todos = Video::paraSite();
        $atual = (string) $request->query('categoria');

        $contagem = [];
        foreach ($todos as $video) {
            $contagem[$video['categoria']] = ($contagem[$video['categoria']] ?? 0) + 1;
        }

        $da_categoria = $atual === '' ? $todos : array_values(array_filter($todos, fn (array $v) => $v['categoria'] === $atual));

        // A galeria vem do cache do modelo, então a fatia é feita em memória: paginar
        // consultando o banco a cada página jogaria fora o cache que faz a página abrir
        // sem nenhum SELECT.
        $por = (int) config('golden.videos_por_pagina', 12);
        $pagina = max(1, (int) $request->query('page', 1));
        $total = count($da_categoria);
        $ultimoPag = max(1, (int) ceil($total / $por));
        $pagina = min($pagina, $ultimoPag);

        $videos = new LengthAwarePaginator(
            array_slice($da_categoria, ($pagina - 1) * $por, $por),
            $total,
            $por,
            $pagina,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        $videos->appends($request->query());

        return view('site.videos', [
            'videos' => $videos,
            'todos' => count($todos),
            'contagem' => $contagem,
            'atual' => $atual,
            'categorias' => Video::categorias(),
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
