<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $query = Coupon::query()->orderByDesc('ativo')->orderByDesc('id');

        if ($busca = trim((string) $request->input('busca'))) {
            $query->where(function ($q) use ($busca) {
                $q->where('codigo', 'like', '%'.Coupon::normalizarCodigo($busca).'%')
                    ->orWhere('descricao', 'like', "%{$busca}%");
            });
        }

        if ($tipo = $request->input('tipo')) {
            $query->where('tipo', $tipo);
        }

        if ($estado = $request->input('estado')) {
            if ($estado === 'ativos') {
                $query->where('ativo', true);
            } elseif ($estado === 'inativos') {
                $query->where('ativo', false);
            }
        }

        $kpis = [
            'total' => Coupon::count(),
            'ativos' => Coupon::where('ativo', true)->count(),
            'usos' => (int) Coupon::sum('usos'),
            'expirados' => Coupon::whereNotNull('valido_ate')->where('valido_ate', '<', now())->count(),
        ];

        return view('admin.coupons.index', [
            'cupons' => $query->paginate(itens_por_pagina())->withQueryString(),
            'kpis' => $kpis,
        ]);
    }

    public function create(): View
    {
        return view('admin.coupons.form', [
            'cupom' => new Coupon(['tipo' => 'percentual', 'ativo' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $cupom = Coupon::create($data);

        Activity::log('cupom_criado', "Cupom {$cupom->codigo} ({$cupom->rotuloDesconto()}) criado.");

        return redirect()->route('admin.catalogo.cupons.index')->with('success', "Cupom {$cupom->codigo} criado.");
    }

    public function edit(Coupon $cupom): View
    {
        return view('admin.coupons.form', ['cupom' => $cupom]);
    }

    public function update(Request $request, Coupon $cupom): RedirectResponse
    {
        $data = $this->validateData($request, $cupom);
        $cupom->update($data);

        Activity::log('cupom_atualizado', "Cupom {$cupom->codigo} atualizado.");

        return redirect()->route('admin.catalogo.cupons.index')->with('success', "Cupom {$cupom->codigo} atualizado.");
    }

    public function toggle(Coupon $cupom): RedirectResponse
    {
        $cupom->update(['ativo' => ! $cupom->ativo]);
        $estado = $cupom->ativo ? 'ativado' : 'desativado';

        Activity::log('cupom_status', "Cupom {$cupom->codigo} {$estado}.");

        return back()->with('success', "Cupom {$cupom->codigo} {$estado}.");
    }

    public function destroy(Coupon $cupom): RedirectResponse
    {
        $codigo = $cupom->codigo;
        $cupom->delete();

        Activity::log('cupom_excluido', "Cupom {$codigo} excluído.");

        return back()->with('success', "Cupom {$codigo} excluído.");
    }

    /** @return array<string, mixed> */
    private function validateData(Request $request, ?Coupon $cupom = null): array
    {
        $request->merge([
            'codigo' => Coupon::normalizarCodigo((string) $request->input('codigo')),
        ]);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'codigo')->ignore($cupom?->id)],
            'descricao' => ['nullable', 'string', 'max:190'],
            'tipo' => ['required', Rule::in(array_keys(Coupon::TIPOS))],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'valor_minimo' => ['nullable', 'numeric', 'min:0'],
            'desconto_maximo' => ['nullable', 'numeric', 'min:0'],
            'limite_usos' => ['nullable', 'integer', 'min:1'],
            'limite_por_cliente' => ['nullable', 'integer', 'min:1'],
            'valido_de' => ['nullable', 'date'],
            'valido_ate' => ['nullable', 'date', 'after_or_equal:valido_de'],
            'ativo' => ['nullable', 'boolean'],
        ], [
            'codigo.regex' => 'O código deve conter apenas letras maiúsculas, números, hífen ou sublinhado.',
            'valido_ate.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ]);

        $valor = match ($data['tipo']) {
            'percentual' => min(100, max(1, (int) round((float) ($data['valor'] ?? 10)))),
            'fixo' => max(1, (int) round(((float) ($data['valor'] ?? 0)) * 100)),
            default => 0,
        };

        return [
            'codigo' => $data['codigo'],
            'descricao' => $data['descricao'] ?: null,
            'tipo' => $data['tipo'],
            'valor' => $valor,
            'valor_minimo_cents' => isset($data['valor_minimo']) && $data['valor_minimo'] !== '' ? (int) round(((float) $data['valor_minimo']) * 100) : 0,
            'desconto_maximo_cents' => ($data['tipo'] === 'percentual' && isset($data['desconto_maximo']) && $data['desconto_maximo'] !== '' && (float) $data['desconto_maximo'] > 0)
                ? (int) round(((float) $data['desconto_maximo']) * 100)
                : null,
            'limite_usos' => $data['limite_usos'] ?? null,
            'limite_por_cliente' => $data['limite_por_cliente'] ?? null,
            'valido_de' => $data['valido_de'] ?? null,
            'valido_ate' => $data['valido_ate'] ?? null,
            'ativo' => $request->boolean('ativo', true),
        ];
    }
}
