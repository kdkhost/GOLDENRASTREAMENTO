<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Page;
use App\Support\Envio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.index', ['paginas' => Page::orderBy('ordem_menu')->orderBy('titulo')->get()]);
    }

    public function create(): View
    {
        return view('admin.pages.form', ['pagina' => new Page(['publicada' => true, 'exibir_menu' => false])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['slug'] = $request->filled('slug') ? slugify_pt($request->slug) : slugify_pt($data['titulo']);
        $p = Page::create($data);
        Activity::log('pagina_criada', "Página {$p->titulo} criada.", $p);

        return redirect()->route('admin.conteudo.paginas.index')->with('success', 'Página criada.');
    }

    public function edit(Page $pagina): View
    {
        return view('admin.pages.form', ['pagina' => $pagina]);
    }

    public function update(Request $request, Page $pagina): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['slug'] = $request->filled('slug') ? slugify_pt($request->slug) : $pagina->slug;
        $anterior = $pagina->imagem;
        $pagina->update($data);

        if ($anterior && $anterior !== $data['imagem']) {
            Envio::remover($anterior, 'site_imagem');
        }

        Activity::log('pagina_atualizada', "Página {$pagina->titulo} atualizada.", $pagina);

        return redirect()->route('admin.conteudo.paginas.index')->with('success', 'Página atualizada.');
    }

    public function destroy(Page $pagina): RedirectResponse
    {
        $titulo = $pagina->titulo;
        Envio::remover($pagina->imagem, 'site_imagem');
        $pagina->delete();
        Activity::log('pagina_excluida', "Página {$titulo} excluída.");

        return back()->with('success', 'Página excluída.');
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190'],
            'eyebrow' => ['nullable', 'string', 'max:120'],
            'intro' => ['nullable', 'string', 'max:2000'],
            'corpo' => ['nullable', 'string'],
            'imagem' => ['nullable', 'string', 'max:255'],
            'imagem_alt' => ['nullable', 'string', 'max:190'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'ordem_menu' => ['nullable', 'integer', 'min:0'],
        ]);

        // O dropzone manda só o caminho: ele precisa apontar para um arquivo que
        // este campo gravou em storage/site, e nada além disso.
        $capa = $data['imagem'] ?? null;

        if ($capa !== null && ! Envio::caminhoConfere($capa, 'site_imagem')) {
            throw ValidationException::withMessages([
                'imagem' => 'Este arquivo não foi carregado por aqui. Solte a foto de novo no campo.',
            ]);
        }

        $data['exibir_menu'] = $request->boolean('exibir_menu');
        $data['publicada'] = $request->boolean('publicada');
        $data['ordem_menu'] = $request->integer('ordem_menu', 0);

        return $data;
    }
}
