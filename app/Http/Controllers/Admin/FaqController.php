<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.faqs.index', [
            'faqs' => $this->query($request)->paginate(itens_por_pagina())->withQueryString(),
        ]);
    }

    /** A mesma régua filtra a listagem e pagina a lista. */
    private function query(Request $request)
    {
        return Faq::query()
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%' . $request->busca . '%';
                $q->where(fn ($w) => $w->where('pergunta', 'like', $t)->orWhere('resposta', 'like', $t));
            })
            ->when($request->filled('situacao'), fn ($q) => $q->where('ativa', $request->situacao === 'ativa'))
            ->orderBy('ordem');
    }

    public function create(): View
    {
        return view('admin.faqs.form', ['faq' => new Faq(['ativa' => true, 'ordem' => 0])]);
    }

    public function edit(Faq $faq): View
    {
        return view('admin.faqs.form', ['faq' => $faq]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $data['ativa'] = $request->boolean('ativa', true);
        Faq::create($data);
        Activity::log('faq_criada', 'FAQ adicionada.');

        return redirect()->route('admin.conteudo.faqs.index')->with('success', 'Pergunta adicionada.');
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $data = $this->validar($request);
        $data['ativa'] = $request->boolean('ativa');
        $faq->update($data);
        Activity::log('faq_atualizada', 'FAQ atualizada.');

        return redirect()->route('admin.conteudo.faqs.index')->with('success', 'Pergunta atualizada.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'pergunta' => ['required', 'string', 'max:255'],
            'resposta' => ['required', 'string', 'max:5000'],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();
        Activity::log('faq_excluida', 'FAQ removida.');

        return back()->with('success', 'Pergunta removida.');
    }
}
