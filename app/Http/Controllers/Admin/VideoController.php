<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Video;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VideoController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.videos.index', [
            'videos' => $this->query($request)->paginate(itens_por_pagina())->withQueryString(),
            'categorias' => Video::rotulos(),
        ]);
    }

    /** A mesma régua filtra a listagem e pagina a lista. */
    private function query(Request $request)
    {
        return Video::query()
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%' . $request->busca . '%';
                $q->where(fn ($w) => $w->where('titulo', 'like', $t)->orWhere('descricao', 'like', $t));
            })
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria', $request->categoria))
            ->when($request->filled('situacao'), fn ($q) => $q->where('ativa', $request->situacao === 'ativa'))
            ->orderBy('ordem');
    }

    public function create(): View
    {
        return view('admin.videos.form', [
            'video' => new Video([
                'ativa' => true,
                'ordem' => 0,
                'categoria' => (string) array_key_first(Video::rotulos()),
            ]),
            'categorias' => Video::rotulos(),
        ]);
    }

    public function edit(Video $video): View
    {
        return view('admin.videos.form', [
            'video' => $video,
            'categorias' => Video::rotulos(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $data['ativa'] = $request->boolean('ativa', true);
        Video::create($data);
        Activity::log('video_criado', 'Tutorial em vídeo adicionado.');

        return redirect()->route('admin.conteudo.videos.index')->with('success', 'Vídeo adicionado à galeria.');
    }

    public function update(Request $request, Video $video): RedirectResponse
    {
        $data = $this->validar($request, $video);
        $data['ativa'] = $request->boolean('ativa');
        $video->update($data);
        Activity::log('video_atualizado', 'Tutorial em vídeo atualizado.');

        return redirect()->route('admin.conteudo.videos.index')->with('success', 'Vídeo atualizado.');
    }

    /**
     * O operador cola o link que copiou do YouTube, não o código sozinho: o campo aceita
     * os dois formatos e é o código de onze caracteres que vai para o banco, porque é ele
     * que segura miniatura, player e a regra de "este vídeo já está na galeria".
     * O link só é substituído quando o código é encontrado — assim, quando não é, o que a
     * pessoa colou continua na tela na hora de corrigir.
     */
    private function validar(Request $request, ?Video $video = null): array
    {
        if (($codigo = Video::codigoDe($request->input('codigo'))) !== null) {
            $request->merge(['codigo' => $codigo]);
        }

        return $request->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'codigo' => ['required', 'string', 'size:11', Rule::unique('videos', 'codigo')->ignore($video)],
            'categoria' => ['required', 'string', 'in:' . implode(',', array_keys(Video::categorias()))],
            'descricao' => ['nullable', 'string', 'max:500'],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ], [
            'codigo.required' => 'Informe o link do YouTube ou o código do vídeo.',
            'codigo.size' => 'Não encontramos o código nesse link. Cole o endereço completo do vídeo ou os 11 caracteres que vêm depois de watch?v?.',
            'codigo.unique' => 'Este vídeo já está na galeria.',
        ]);
    }

    public function destroy(Video $video): RedirectResponse
    {
        $video->delete();
        Activity::log('video_excluido', 'Tutorial em vídeo removido.');

        return back()->with('success', 'Vídeo removido da galeria.');
    }
}
