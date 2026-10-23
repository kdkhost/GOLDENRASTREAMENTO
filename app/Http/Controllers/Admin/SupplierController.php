<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        return view('admin.suppliers.index', ['fornecedores' => Supplier::orderBy('nome')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:190'],
            'cnpj' => ['nullable', 'string', 'max:24'],
            'contato' => ['nullable', 'string', 'max:120'],
            'telefone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:160'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);
        $f = Supplier::create($data);
        Activity::log('fornecedor_criado', "Fornecedor {$f->nome} criado.", $f);

        return back()->with('success', 'Fornecedor cadastrado.');
    }

    public function update(Request $request, Supplier $fornecedor): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:190'],
            'cnpj' => ['nullable', 'string', 'max:24'],
            'contato' => ['nullable', 'string', 'max:120'],
            'telefone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:160'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'ativo' => ['nullable', 'boolean'],
        ]);
        $data['ativo'] = $request->boolean('ativo');
        $fornecedor->update($data);
        Activity::log('fornecedor_atualizado', "Fornecedor {$fornecedor->nome} atualizado.", $fornecedor);

        return back()->with('success', 'Fornecedor atualizado.');
    }

    public function destroy(Supplier $fornecedor): RedirectResponse
    {
        $nome = $fornecedor->nome;
        $fornecedor->delete();
        Activity::log('fornecedor_excluido', "Fornecedor {$nome} removido.");

        return back()->with('success', 'Fornecedor removido.');
    }
}
