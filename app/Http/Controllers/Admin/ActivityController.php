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
            ->with('user')
            ->when($request->filled('acao'), fn ($q) => $q->where('action', $request->acao))
            ->when($request->filled('usuario'), fn ($q) => $q->where('user_id', $request->usuario))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.activities.index', [
            'atividades' => $atividades,
            'filtros' => $request->only(['acao', 'usuario']),
            'acoes' => Activity::selectRaw('action, COUNT(*) c')->groupBy('action')->orderByDesc('c')->pluck('c', 'action'),
        ]);
    }
}
