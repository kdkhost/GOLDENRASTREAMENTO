<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categorias' => Category::withCount('products')->orderBy('ordem')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['slug'] = slugify_pt($data['nome']);
        $data['ordem'] = $data['ordem'] ?? 0;

        $c = Category::create($data);
        Activity::log('categoria_criada', "Categoria {$c->nome} criada.", $c);

        return back()->with('success', 'Categoria criada.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'ativa' => ['nullable', 'boolean'],
        ]);
        $data['ativa'] = $request->boolean('ativa');
        $category->update($data);
        Activity::log('categoria_atualizada', "Categoria {$category->nome} atualizada.", $category);

        return back()->with('success', 'Categoria atualizada.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $nome = $category->nome;
        $category->delete();
        Activity::log('categoria_excluida', "Categoria {$nome} excluída.");

        return back()->with('success', 'Categoria excluída.');
    }
}
