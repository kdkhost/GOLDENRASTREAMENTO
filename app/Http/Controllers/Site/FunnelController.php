<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\FunnelPage;
use Illuminate\View\View;

/**
 * A página de funil: campanha de divulgação que não entra no menu, no rodapé nem na
 * busca — só chega aqui quem recebeu o link. O conteúdo vem dos blocos publicados no
 * painel e a página inteira sai do cache, como o resto do site.
 */
class FunnelController extends Controller
{
    public function show(string $slug): View
    {
        $pagina = FunnelPage::paraSite($slug);
        abort_if($pagina === null, 404);

        return view('site.funil.page', ['pagina' => $pagina]);
    }
}
