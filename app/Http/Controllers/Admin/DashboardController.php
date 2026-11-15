<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Conversa;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $inicio = now()->subDays(29)->startOfDay();

        // Otimização de carga: consolidação e cache em memória (30s) para evitar sobrecarga de consultas síncronas no MySQL
        $metricas = Cache::remember('admin_dashboard_metrics_v2', 30, function () use ($inicio): array {
            $porStatus = Lead::query()
                ->selectRaw('status, COUNT(*) total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all();

            $total = Lead::count();
            $vendido = (int) ($porStatus['vendido'] ?? 0);
            $perdido = (int) ($porStatus['perdido'] ?? 0);
            $funil = $total - $perdido;
            $conversao = $funil > 0 ? round(($vendido / max($total, 1)) * 100, 1) : 0.0;
            $receita = (int) Lead::where('status', 'vendido')->sum('value');

            // Série dos últimos 30 dias
            $porDia = Lead::where('created_at', '>=', $inicio)
                ->selectRaw('date(created_at) dia, COUNT(*) total')
                ->groupBy('dia')
                ->pluck('total', 'dia')
                ->all();

            $labels = [];
            $serie = [];
            for ($i = 0; $i < 30; $i++) {
                $d = (clone $inicio)->addDays($i)->toDateString();
                $labels[] = (clone $inicio)->addDays($i)->format('d/m');
                $serie[] = (int) ($porDia[$d] ?? 0);
            }

            // Visão consolidada dos módulos integrados (CRM + ERP + CMS + Operação)
            $modulos = [
                'clientes' => Customer::count(),
                'clientes_ativos' => Customer::where('status', 'ativo')->count(),
                'produtos' => Product::where('ativo', true)->count(),
                'estoque_baixo' => Product::where('ativo', true)->where('controle_estoque', true)->whereColumn('estoque', '<=', 'estoque_minimo')->count(),
                'pedidos' => Order::count(),
                'pedidos_abertos' => Order::whereIn('status', Order::ABERTOS)->count(),
                'pedidos_enviados' => Order::where('status', 'enviado')->count(),
                'faturamento_loja' => (int) Order::whereNotIn('status', ['cancelado'])->sum('total_cents'),
                'loja_ativa' => Setting::get('loja_ativa', '1') === '1',
                'manutencao_ativa' => (string) Setting::get('manutencao_ativa', '0') === '1',
                'waf_ativo' => (string) Setting::get('shield_ativo', '1') === '1',
                'chamados_abertos' => Ticket::whereIn('status', Ticket::ABERTOS)->count(),
                'conversas_aguardando' => Conversa::where('status', 'aguardando_equipe')->count(),
                'paginas' => Page::where('publicada', true)->count(),
                'depoimentos' => Testimonial::where('aprovado', true)->count(),
            ];

            return [
                'porStatus' => $porStatus,
                'total' => $total,
                'vendido' => $vendido,
                'perdido' => $perdido,
                'conversao' => $conversao,
                'receita' => $receita,
                'labels' => $labels,
                'serie' => $serie,
                'modulos' => $modulos,
            ];
        });

        // Conversão por campanha (consulta leve com limite 8)
        $campanhas = Campaign::withCount('leads')
            ->withCount(['leads as leads_vendidos' => fn ($q) => $q->where('status', 'vendido')])
            ->withCount(['leads as leads_novos' => fn ($q) => $q->where('status', 'novo')])
            ->orderByDesc('leads_count')
            ->limit(8)
            ->get();

        $porStatus = (array) ($metricas['porStatus'] ?? []);

        return view('admin.dashboard', [
            'total' => $metricas['total'],
            'novos' => (int) ($porStatus['novo'] ?? 0),
            'contatado' => (int) ($porStatus['contatado'] ?? 0),
            'proposta' => (int) ($porStatus['proposta'] ?? 0),
            'vendido' => $metricas['vendido'],
            'perdido' => $metricas['perdido'],
            'conversao' => $metricas['conversao'],
            'receita' => $metricas['receita'],
            'labels' => $metricas['labels'],
            'serie' => $metricas['serie'],
            'campanhas' => $campanhas,
            'modulos' => $metricas['modulos'],
            'recentes' => Lead::with('campaign')->latest()->limit(8)->get(),
            'pedidosRecentes' => Order::latest()->limit(8)->get(),
        ]);
    }
}
