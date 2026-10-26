<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\GuiaPainel;
use Illuminate\View\View;

/**
 * A documentação que quem opera o painel consulta sem sair dele.
 *
 * Não existe página de detalhe nem busca no servidor: a tela é uma só, com as seções na ordem do
 * config, e o que filtra é o campo do topo — em JavaScript, no navegador. O texto mora em
 * `config/guia_painel.php` e é o mesmo que desenha o cartão "Como conectar" da tela de Google Ads
 * e os blocos das outras telas; copiar parágrafo para uma view seria ter duas versões dele.
 */
class GuiaController extends Controller
{
    public function index(): View
    {
        return view('admin.guia.index', [
            'secoes' => GuiaPainel::secoes(),
            'grupos' => GuiaPainel::porGrupo(),
        ]);
    }
}
