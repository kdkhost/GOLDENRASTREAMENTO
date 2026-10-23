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

    public function create(): View
    {
        return view('admin.suppliers.form', [
            'fornecedor' => new Supplier(['ativo' => true]),
        ]);
    }

    public function edit(Supplier $fornecedor): View
    {
        return view('admin.suppliers.form', ['fornecedor' => $fornecedor]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $f = Supplier::create($data);
        Activity::log('fornecedor_criado', "Fornecedor {$f->nome} criado.", $f);

        return redirect()->route('admin.catalogo.fornecedores.index')->with('success', 'Fornecedor cadastrado.');
    }

    public function update(Request $request, Supplier $fornecedor): RedirectResponse
    {
        $data = $this->validar($request);
        $data['ativo'] = $request->boolean('ativo');
        $fornecedor->update($data);
        Activity::log('fornecedor_atualizado', "Fornecedor {$fornecedor->nome} atualizado.", $fornecedor);

        return redirect()->route('admin.catalogo.fornecedores.index')->with('success', 'Fornecedor atualizado.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:190'],
            'cnpj' => ['nullable', 'string', 'max:24'],
            'contato' => ['nullable', 'string', 'max:120'],
            'telefone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:160'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    public function destroy(Supplier $fornecedor): RedirectResponse
    {
        $nome = $fornecedor->nome;
        $fornecedor->delete();
        Activity::log('fornecedor_excluido', "Fornecedor {$nome} removido.");

        return back()->with('success', 'Fornecedor removido.');
    }
}
