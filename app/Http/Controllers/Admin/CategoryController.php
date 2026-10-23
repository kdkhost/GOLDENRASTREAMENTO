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
    public function index(Request $request): View
    {
        return view('admin.categories.index', [
            'categorias' => $this->query($request)->paginate(itens_por_pagina())->withQueryString(),
        ]);
    }

    /** A mesma régua filtra a listagem e pagina a lista. */
    private function query(Request $request)
    {
        return Category::withCount('products')
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%' . $request->busca . '%';
                $q->where(fn ($w) => $w->where('nome', 'like', $t)->orWhere('descricao', 'like', $t));
            })
            ->when($request->filled('situacao'), fn ($q) => $q->where('ativa', $request->situacao === 'ativa'))
            ->orderBy('ordem');
    }

    public function create(): View
    {
        return view('admin.categories.form', [
            'categoria' => new Category(['ativa' => true, 'ordem' => 0]),
        ]);
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', ['categoria' => $category]);
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

        return redirect()->route('admin.catalogo.categorias.index')->with('success', 'Categoria criada.');
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

        return redirect()->route('admin.catalogo.categorias.index')->with('success', 'Categoria atualizada.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $nome = $category->nome;
        $category->delete();
        Activity::log('categoria_excluida', "Categoria {$nome} excluída.");

        return back()->with('success', 'Categoria excluída.');
    }
}
