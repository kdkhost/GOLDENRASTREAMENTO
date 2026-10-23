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
    public function index(): View
    {
        return view('admin.faqs.index', ['faqs' => Faq::orderBy('ordem')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pergunta' => ['required', 'string', 'max:255'],
            'resposta' => ['required', 'string', 'max:5000'],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['ativa'] = $request->boolean('ativa', true);
        Faq::create($data);
        Activity::log('faq_criada', 'FAQ adicionada.');

        return back()->with('success', 'Pergunta adicionada.');
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $data = $request->validate([
            'pergunta' => ['required', 'string', 'max:255'],
            'resposta' => ['required', 'string', 'max:5000'],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['ativa'] = $request->boolean('ativa');
        $faq->update($data);
        Activity::log('faq_atualizada', 'FAQ atualizada.');

        return back()->with('success', 'Pergunta atualizada.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();
        Activity::log('faq_excluida', 'FAQ removida.');

        return back()->with('success', 'Pergunta removida.');
    }
}
