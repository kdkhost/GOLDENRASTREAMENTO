<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $porStatus = Lead::query()
            ->selectRaw('status, COUNT(*) total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = Lead::count();
        $vendido = (int) ($porStatus['vendido'] ?? 0);
        $perdido = (int) ($porStatus['perdido'] ?? 0);
        $funil = $total - $perdido;
        $conversao = $funil > 0 ? round(($vendido / max($total, 1)) * 100, 1) : 0.0;
        $receita = (int) Lead::where('status', 'vendido')->sum('value');

        // Série dos últimos 30 dias
        $inicio = now()->subDays(29)->startOfDay();
        $porDia = Lead::where('created_at', '>=', $inicio)
            ->selectRaw('date(created_at) dia, COUNT(*) total')
            ->groupBy('dia')->pluck('total', 'dia');
        $labels = [];
        $serie = [];
        for ($i = 0; $i < 30; $i++) {
            $d = (clone $inicio)->addDays($i)->toDateString();
            $labels[] = (clone $inicio)->addDays($i)->format('d/m');
            $serie[] = (int) ($porDia[$d] ?? 0);
        }

        // Conversão por campanha
        $campanhas = Campaign::withCount('leads')
            ->withCount(['leads as leads_vendidos' => fn ($q) => $q->where('status', 'vendido')])
            ->withCount(['leads as leads_novos' => fn ($q) => $q->where('status', 'novo')])
            ->orderByDesc('leads_count')
            ->limit(8)
            ->get();

        // Visão consolidada dos módulos integrados (CRM + ERP + CMS)
        $modulos = [
            'clientes' => Customer::count(),
            'clientes_ativos' => Customer::where('status', 'ativo')->count(),
            'produtos' => Product::where('ativo', true)->count(),
            'estoque_baixo' => Product::where('ativo', true)->where('controle_estoque', true)->whereColumn('estoque', '<=', 'estoque_minimo')->count(),
            'pedidos' => Order::count(),
            'pedidos_abertos' => Order::whereIn('status', Order::ABERTOS)->count(),
            'faturamento_loja' => (int) Order::whereNotIn('status', ['cancelado'])->sum('total_cents'),
            'loja_ativa' => Setting::get('loja_ativa', '1') === '1',
            'paginas' => Page::where('publicada', true)->count(),
            'depoimentos' => Testimonial::where('aprovado', true)->count(),
        ];

        return view('admin.dashboard', [
            'total' => $total,
            'novos' => (int) ($porStatus['novo'] ?? 0),
            'contatado' => (int) ($porStatus['contatado'] ?? 0),
            'proposta' => (int) ($porStatus['proposta'] ?? 0),
            'vendido' => $vendido,
            'perdido' => $perdido,
            'conversao' => $conversao,
            'receita' => $receita,
            'labels' => $labels,
            'serie' => $serie,
            'campanhas' => $campanhas,
            'modulos' => $modulos,
            'recentes' => Lead::with('campaign')->latest()->limit(8)->get(),
        ]);
    }
}
