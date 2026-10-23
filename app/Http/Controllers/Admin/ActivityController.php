<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $atividades = Activity::query()
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%' . $request->busca . '%';
                $q->where(fn ($w) => $w->where('description', 'like', $t)
                    ->orWhere('user_name', 'like', $t)
                    ->orWhere('ip', 'like', $t));
            })
            ->when($request->filled('acao'), fn ($q) => $q->where('action', $request->acao))
            ->when($request->filled('usuario'), fn ($q) => $q->where('user_id', $request->usuario))
            ->latest()
            ->paginate(itens_por_pagina())
            ->withQueryString();

        return view('admin.activities.index', [
            'atividades' => $atividades,
            'acoes' => Activity::selectRaw('action, COUNT(*) c')->groupBy('action')->orderByDesc('c')->pluck('c', 'action'),
            'quem' => Activity::whereNotNull('user_id')->distinct()->orderBy('user_name')->pluck('user_name', 'user_id'),
        ]);
    }
}
