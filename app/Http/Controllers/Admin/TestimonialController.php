<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TestimonialController extends Controller
{
    public function index(): View
    {
        return view('admin.testimonials.index', ['depoimentos' => Testimonial::orderBy('ordem')->latest()->get()]);
    }

    public function create(): View
    {
        return view('admin.testimonials.form', [
            'depoimento' => new Testimonial(['aprovado' => true, 'nota' => 5, 'ordem' => 0]),
        ]);
    }

    public function edit(Testimonial $depoimento): View
    {
        return view('admin.testimonials.form', ['depoimento' => $depoimento]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $data['aprovado'] = $request->boolean('aprovado');
        $d = Testimonial::create($data);
        Activity::log('depoimento_criado', "Depoimento de {$d->nome} adicionado.");

        return redirect()->route('admin.conteudo.depoimentos.index')->with('success', 'Depoimento adicionado.');
    }

    public function update(Request $request, Testimonial $depoimento): RedirectResponse
    {
        $data = $this->validar($request);
        $data['aprovado'] = $request->boolean('aprovado');
        $depoimento->update($data);
        Activity::log('depoimento_atualizado', "Depoimento de {$depoimento->nome} atualizado.");

        return redirect()->route('admin.conteudo.depoimentos.index')->with('success', 'Depoimento atualizado.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'cidade' => ['nullable', 'string', 'max:96'],
            'papel' => ['nullable', 'string', 'max:120'],
            'texto' => ['required', 'string', 'max:2000'],
            'nota' => ['required', 'integer', 'min:1', 'max:5'],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    public function destroy(Testimonial $depoimento): RedirectResponse
    {
        $nome = $depoimento->nome;
        $depoimento->delete();
        Activity::log('depoimento_excluido', "Depoimento de {$nome} removido.");

        return back()->with('success', 'Depoimento removido.');
    }
}
