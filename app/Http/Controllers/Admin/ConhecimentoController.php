<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ConhecimentoItem;
use App\Services\Assistente\Semeador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * O que o assistente sabe — a base que a resposta lê antes de abrir a boca.
 *
 * Cada item tem procedência porque resposta sem fonte é chute: a tela mostra de onde veio e
 * quando foi lido, e é isso que a equipe confere quando o cliente reclama do que a máquina
 * disse. O administrador escreve aqui (`painel`); as origens `internet` e `site` são preenchidas
 * pelo leitor automático, que nunca inventa linha sem endereço de origem.
 */
class ConhecimentoController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.assistente.conhecimento.index', [
            'itens' => $this->query($request)->paginate(itens_por_pagina())->withQueryString(),
            'origens' => ConhecimentoItem::origens(),
        ]);
    }

    /** A mesma régua filtra a listagem e pagina a lista. */
    private function query(Request $request)
    {
        return ConhecimentoItem::query()
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%' . $request->busca . '%';
                $q->where(fn ($w) => $w->where('titulo', 'like', $t)
                    ->orWhere('corpo', 'like', $t)
                    ->orWhere('tags', 'like', $t));
            })
            ->when($request->filled('origem'), fn ($q) => $q->where('origem', $request->origem))
            ->when($request->filled('situacao'), fn ($q) => $q->where('ativo', $request->situacao === 'ativa'))
            ->latest('updated_at');
    }

    public function create(): View
    {
        return view('admin.assistente.conhecimento.form', [
            'item' => new ConhecimentoItem([
                'ativo' => true,
                'origem' => 'painel',
            ]),
            'origens' => ConhecimentoItem::origens(),
        ]);
    }

    public function edit(ConhecimentoItem $item): View
    {
        return view('admin.assistente.conhecimento.form', [
            'item' => $item,
            'origens' => ConhecimentoItem::origens(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $data['ativo'] = $request->boolean('ativo', true);
        $data['criado_por'] = $request->user()->id;
        ConhecimentoItem::create($data);
        Activity::log('conhecimento_criado', 'Um item entrou na base do assistente.');

        return redirect()->route('admin.assistente.conhecimento.index')->with('success', 'Aprendizado gravado.');
    }

    public function update(Request $request, ConhecimentoItem $item): RedirectResponse
    {
        $data = $this->validar($request, $item);
        $data['ativo'] = $request->boolean('ativo');
        $item->update($data);
        Activity::log('conhecimento_atualizado', 'Um item da base do assistente foi corrigido.');

        return redirect()->route('admin.assistente.conhecimento.index')->with('success', 'Aprendizado atualizado.');
    }

    public function destroy(ConhecimentoItem $item): RedirectResponse
    {
        $item->delete();
        Activity::log('conhecimento_excluido', 'Um item saiu da base do assistente.');

        return back()->with('success', 'O assistente esqueceu este assunto.');
    }

    /**
     * O treinamento de fábrica, na mão de quem administra: acrescenta o que falta (produtos,
     * rastreadores, instalação, plataforma, portal e financeiro) e regrava o catálogo com os
     * preços de hoje. Nunca sobrescreve um item que a equipe editou — o que já existe fica.
     */
    public function semear(Semeador $semeador): RedirectResponse
    {
        $contagem = $semeador->conhecimento();
        $catalogo = $semeador->catalogo();

        Activity::log('conhecimento_semeado', 'A base de fábrica do assistente foi aplicada.');

        $resumo = $contagem['criados'] === 0
            ? 'A base já estava completa'
            : $contagem['criados'].' assunto(s) novo(s) na base';

        return back()->with('success', $resumo.($catalogo ? ' e catálogo atualizado com os preços da loja.' : '.'));
    }

    /**
     * A procedência é a única chave que impede o leitor automático de reaprender a mesma página a
     * cada volta: `referencia` é o endereço lido, e quem escreve à mão fica sem ela — o índice
     * único aceita vários nulos em MySQL, exatamente por isso.
     */
    private function validar(Request $request, ?ConhecimentoItem $item = null): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:180'],
            'corpo' => ['required', 'string', 'max:20000'],
            'tags' => ['nullable', 'string', 'max:190'],
            'origem' => ['required', 'string', 'in:' . implode(',', array_keys(ConhecimentoItem::origens()))],
            'url' => ['nullable', 'string', 'max:500', 'url'],
            'referencia' => ['nullable', 'string', 'max:190', Rule::unique('conhecimento_itens', 'referencia')->ignore($item)],
        ], [
            'corpo.required' => 'Escreva o que o assistente deve responder — sem texto, o item não ensina nada.',
            'url.url' => 'A fonte precisa ser um endereço completo, começando por https://',
            'referencia.unique' => 'Esta página já está na base. Corrija o item que existe em vez de repetir o assunto.',
        ], [
            'titulo' => 'título',
            'corpo' => 'conteúdo',
            'tags' => 'assuntos',
            'origem' => 'origem',
            'url' => 'endereço da fonte',
            'referencia' => 'chave da fonte',
        ]);
    }
}
