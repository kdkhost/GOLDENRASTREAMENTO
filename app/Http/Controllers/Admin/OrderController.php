<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use App\Services\Correios\CorreiosService;
use App\Services\RastreioEncomendaService;
use App\Support\Envio;
use App\Support\Notifica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $pedidos = Order::withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $t = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('numero', 'like', $t)->orWhere('nome', 'like', $t)->orWhere('telefone', 'like', $t));
            })
            ->latest()
            ->paginate(itens_por_pagina())
            ->withQueryString();

        $dadosKpi = Cache::remember('admin_orders_kpis', 30, function (): array {
            return [
                'kpis' => [
                    'total' => Order::count(),
                    'pendentes' => Order::where('status', 'pendente')->count(),
                    'entregues' => Order::where('status', 'entregue')->count(),
                ],
                'faturamento' => (int) Order::whereNotIn('status', ['cancelado', 'pendente'])->sum('total_cents'),
            ];
        });

        return view('admin.orders.index', [
            'pedidos' => $pedidos,
            'kpis' => $dadosKpi['kpis'],
            'faturamento' => $dadosKpi['faturamento'],
            'statuses' => Order::STATUS,
        ]);
    }

    public function show(Order $pedido): View
    {
        return view('admin.orders.show', [
            'pedido' => $pedido->load(['items.product', 'customer', 'user', 'histories.user']),
            'statuses' => Order::STATUS,
            'clientes' => Customer::orderBy('nome')->limit(200)->get(['id', 'nome', 'telefone']),
        ]);
    }

    /**
     * Muda status/pagamento e grava o marco na linha do tempo que o cliente vê. As datas
     * de pago_em/enviado_em são consequência do status (Order::registrarStatus).
     */
    public function atualizarStatus(Request $request, Order $pedido): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Order::STATUS))],
            'pagamento' => ['nullable', 'in:'.implode(',', array_keys(Order::PAGAMENTOS))],
            'notas' => ['nullable', 'string', 'max:5000'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ]);

        $anterior = $pedido->status;
        $pedido->registrarStatus($data['status'], $data['observacao'] ?: null, $request->user()->id);
        $pedido->update(['pagamento' => $data['pagamento'], 'notas' => $data['notas'] ?? null]);

        Activity::log('pedido_status', "Pedido {$pedido->numero}: {$anterior} → {$data['status']}.", $pedido);

        return back()->with('success', 'Pedido atualizado.');
    }

    public function destroy(Order $pedido): RedirectResponse
    {
        $numero = $pedido->numero;
        foreach ((array) $pedido->documentos as $caminho) {
            Envio::remover($caminho, 'pedido_documento');
        }
        $pedido->delete();
        Activity::log('pedido_excluido', "Pedido {$numero} excluído.");

        return redirect()->route('admin.pedidos.index')->with('success', 'Pedido excluído.');
    }

    /**
     * Documentos do pedido (comprovante, nota fiscal, proposta). Os arquivos já estão no
     * disco — entraram pelo campo de arrastar-e-soltar —, então aqui só se reconcilia a
     * lista: o que saiu deixa de existir, o que ficou não é regravado.
     */
    public function documentos(Request $request, Order $pedido): RedirectResponse
    {
        $enviados = array_values(array_unique(array_filter((array) $request->input('documentos', []))));

        if (count($enviados) > 12) {
            return back()->with('error', 'São no máximo 12 documentos por pedido.');
        }

        foreach ($enviados as $caminho) {
            if (! is_string($caminho) || ! Envio::caminhoConfere($caminho, 'pedido_documento')) {
                throw ValidationException::withMessages([
                    'documentos' => 'Um dos arquivos não foi carregado por aqui. Solte no campo de novo.',
                ]);
            }
        }

        foreach (array_diff((array) $pedido->documentos, $enviados) as $saindo) {
            Envio::remover($saindo, 'pedido_documento');
        }

        $pedido->update(['documentos' => $enviados ?: null]);
        Activity::log('pedido_documentos', "Pedido {$pedido->numero}: ".count($enviados).' documento(s) anexado(s).', $pedido);

        return back()->with('success', 'Documentos atualizados.');
    }

    /**
     * Salva a transportadora e o código de rastreio do pedido (Correios, Mercado Envios, etc.).
     * Permite mudar o status para 'enviado' e disparar e-mail de rastreamento com link direto.
     */
    public function salvarRastreio(Request $request, Order $pedido, CorreiosService $correios): RedirectResponse
    {
        $transportadorasValidas = implode(',', array_keys(Order::TRANSPORTADORAS));

        $data = $request->validate([
            'transportadora' => ['nullable', 'string', 'in:'.$transportadorasValidas],
            'codigo_rastreio' => ['nullable', 'string', 'max:60'],
            'marcar_enviado' => ['nullable', 'boolean'],
            'notificar_cliente' => ['nullable', 'boolean'],
        ]);

        $codigo = strtoupper(trim((string) ($data['codigo_rastreio'] ?? '')));
        $transportadora = $data['transportadora'] ?? null;

        $pedido->update([
            'transportadora' => $transportadora,
            'codigo_rastreio' => $codigo ?: null,
        ]);

        if ($request->boolean('marcar_enviado') && in_array($pedido->status, ['pendente', 'pago', 'separando'], true)) {
            $pedido->registrarStatus('enviado', 'Objeto despachado via '.($pedido->transportadoraLabel()).($codigo ? ' (código '.$codigo.')' : ''), $request->user()->id);
        } elseif ($codigo !== '') {
            $origemEmpresa = trim((string) Setting::get('empresa_cidade', 'Central Golden Rastreamento'));
            $destinoPedido = $pedido->cidade ? trim("{$pedido->cidade}/{$pedido->uf}") : 'Endereço do destinatário';
            $pedido->registrarMovimentacaoRastreio(
                "Etiqueta / Código vinculado ({$pedido->transportadoraLabel()})",
                $origemEmpresa,
                "Código de rastreamento {$codigo} vinculado ao pedido para entrega em {$destinoPedido}.",
                $pedido->transportadoraLabel()
            );
        }

        $notificado = false;
        if ($request->boolean('notificar_cliente') && $codigo !== '') {
            $notificado = $correios->notificarCliente($pedido);
        }

        if ($codigo !== '') {
            Notifica::disparar(
                'entrega_atualizada',
                "Pedido {$pedido->numero} despachado · {$pedido->nome}",
                "Objeto {$codigo} despachado via {$pedido->transportadoraLabel()}. Destino: {$pedido->cidade}/{$pedido->uf}.",
                (string) $pedido->id,
                [
                    'pedido_numero' => $pedido->numero,
                    'cliente' => $pedido->nome,
                    'codigo_rastreio' => $codigo,
                    'transportadora' => $pedido->transportadoraLabel(),
                    'status' => 'Objeto despachado',
                    'destino' => "{$pedido->cidade}/{$pedido->uf}",
                ],
                "entrega:{$pedido->id}:despachado"
            );
        }

        Activity::log('pedido_rastreio', "Pedido {$pedido->numero}: rastreio ".($codigo ?: 'removido')." ({$pedido->transportadoraLabel()}).", $pedido);

        $msg = 'Dados de despacho e rastreamento atualizados com sucesso.';
        if ($notificado) {
            $msg .= ' E-mail com código de rastreamento enviado ao cliente.';
        }

        return back()->with('success', $msg);
    }

    /**
     * Registra a solicitação de coleta na sede da Golden e notifica o administrador por e-mail e celular.
     */
    public function solicitarColeta(Order $pedido): RedirectResponse
    {
        $pedido->registrarStatus(
            $pedido->status,
            'Coleta de encomenda solicitada na sede da Golden Rastreamento para expedição via '.$pedido->transportadoraLabel().'.',
            auth()->id()
        );

        $origemEmpresa = trim((string) Setting::get('empresa_cidade', 'Três Pontas/MG'));
        $pedido->registrarMovimentacaoRastreio(
            'Coleta agendada na expedição Golden Rastreamento',
            $origemEmpresa,
            "Encomenda conferida e aguardando retirada pela transportadora ({$pedido->transportadoraLabel()}).",
            'Expedição Golden Rastreamento'
        );

        Notifica::disparar(
            'coleta_solicitada',
            "Nova coleta solicitada · Pedido {$pedido->numero}",
            "Coleta de encomenda agendada na sede da Golden para o pedido {$pedido->numero} ({$pedido->nome}). Transportadora: {$pedido->transportadoraLabel()}.",
            (string) $pedido->id,
            [
                'pedido_numero' => $pedido->numero,
                'cliente' => $pedido->nome,
                'transportadora' => $pedido->transportadoraLabel(),
                'local_coleta' => 'Golden Rastreamento (Matriz) - Três Pontas/MG',
                'cidade_destino' => "{$pedido->cidade}/{$pedido->uf}",
                'total' => brl((int) $pedido->total_cents),
            ],
            "coleta:{$pedido->id}:".now()->format('YmdHi')
        );

        Activity::log('coleta_solicitada', "Coleta solicitada para o pedido {$pedido->numero}.", $pedido);

        return back()->with('success', 'Coleta de encomenda solicitada com sucesso! O administrador e a expedição foram notificados.');
    }

    /**
     * Dispara (ou reenvia) o e-mail de notificação de rastreamento ao cliente.
     */
    public function notificarRastreio(Order $pedido, CorreiosService $correios): RedirectResponse
    {
        if (! $pedido->codigo_rastreio) {
            return back()->with('error', 'Cadastre o código de rastreamento antes de notificar o cliente.');
        }

        if (! $pedido->email) {
            return back()->with('error', 'O pedido não possui e-mail de cliente cadastrado.');
        }

        $ok = $correios->notificarCliente($pedido);

        if ($ok) {
            return back()->with('success', 'E-mail de rastreamento enviado com sucesso para '.$pedido->email);
        }

        return back()->with('error', 'Não foi possível enviar o e-mail de rastreamento.');
    }

    /**
     * Registra manualmente uma parada ou movimentação intermediária da transportadora no rastreamento do pedido.
     */
    public function adicionarMovimentacaoRastreio(Request $request, Order $pedido): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:140'],
            'unidade' => ['nullable', 'string', 'max:140'],
            'local' => ['required', 'string', 'max:140'],
            'detalhe' => ['nullable', 'string', 'max:500'],
            'data' => ['nullable', 'string', 'max:20'],
            'hora' => ['nullable', 'string', 'max:10'],
            'atualizar_status_pedido' => ['nullable', 'in:nenhum,separando,enviado,entregue'],
        ]);

        $pedido->registrarMovimentacaoRastreio(
            $data['status'],
            $data['local'],
            $data['detalhe'] ?? '',
            $data['unidade'] ?? $pedido->transportadoraLabel(),
            $data['data'] ?? null,
            $data['hora'] ?? null
        );

        $novoStatus = (string) ($data['atualizar_status_pedido'] ?? 'nenhum');
        if (in_array($novoStatus, ['separando', 'enviado', 'entregue'], true) && $pedido->status !== $novoStatus) {
            $pedido->registrarStatus(
                $novoStatus,
                "{$data['status']} — {$data['local']}",
                $request->user()?->id
            );
        }

        $dataHora = trim(($data['data'] ?? now()->format('d/m/Y')).' '.($data['hora'] ?? now()->format('H:i')));
        Notifica::disparar(
            'entrega_atualizada',
            "Nova parada · Pedido {$pedido->numero} ({$data['status']})",
            "Parada registrada em {$data['local']} ({$dataHora}): ".($data['detalhe'] ?? $data['status']),
            (string) $pedido->id,
            [
                'pedido_numero' => $pedido->numero,
                'codigo_rastreio' => $pedido->codigo_rastreio,
                'etapa' => $data['status'],
                'local' => $data['local'],
                'unidade' => $data['unidade'] ?? $pedido->transportadoraLabel(),
                'descricao' => $data['detalhe'] ?? '',
                'data_hora' => $dataHora,
            ],
            "parada:{$pedido->id}:".md5($data['status'].$data['local'].$dataHora)
        );

        Activity::log(
            'pedido_rastreio_movimentacao',
            "Pedido {$pedido->numero}: parada '{$data['status']}' em {$data['local']}.",
            $pedido
        );

        return back()->with('success', 'Parada / movimentação registrada com sucesso no rastreamento.');
    }

    /**
     * Remove uma parada específica do histórico de rastreio do pedido.
     */
    public function removerMovimentacaoRastreio(Order $pedido, int $indice): RedirectResponse
    {
        $historico = is_array($pedido->rastreio_historico) ? $pedido->rastreio_historico : [];
        if (array_key_exists($indice, $historico)) {
            unset($historico[$indice]);
            $pedido->rastreio_historico = array_values($historico);
            $pedido->save();

            Activity::log('pedido_rastreio_movimentacao_removida', "Pedido {$pedido->numero}: parada #{$indice} removida.", $pedido);

            return back()->with('success', 'Parada removida do histórico de rastreamento.');
        }

        return back()->with('error', 'Parada não encontrada no histórico deste pedido.');
    }

    /**
     * Consulta em tempo real o código de rastreio na transportadora ativa e retorna JSON para o painel.
     */
    public function consultarRastreio(Order $pedido, RastreioEncomendaService $rastreio): JsonResponse
    {
        $codigo = $pedido->codigo_rastreio;
        if (! $codigo) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Pedido sem código de rastreamento cadastrado.',
            ], 422);
        }

        $dados = $rastreio->rastrear($codigo, true);

        $ultimoEvento = $dados['eventos'][0] ?? null;
        if ($ultimoEvento) {
            $titulo = $ultimoEvento['status'] ?? ($ultimoEvento['titulo'] ?? 'Atualizado');
            $dataHora = trim(($ultimoEvento['data'] ?? '').' '.($ultimoEvento['hora'] ?? '')) ?: now()->format('d/m/Y H:i');
            $chave = "rastreio:{$pedido->id}:".md5($titulo.$dataHora);
            Notifica::disparar(
                'entrega_atualizada',
                "Entrega passo a passo · Pedido {$pedido->numero} ({$titulo})",
                "Objeto {$codigo}: ".($ultimoEvento['detalhe'] ?? ($ultimoEvento['descricao'] ?? '')).' em '.($ultimoEvento['local'] ?? 'Trânsito').'.',
                (string) $pedido->id,
                [
                    'pedido_numero' => $pedido->numero,
                    'codigo_rastreio' => $codigo,
                    'etapa' => $titulo,
                    'descricao' => $ultimoEvento['detalhe'] ?? ($ultimoEvento['descricao'] ?? ''),
                    'local' => $ultimoEvento['local'] ?? 'Trânsito',
                    'data_hora' => $dataHora,
                ],
                $chave
            );
        }

        return response()->json([
            'sucesso' => true,
            'pedido' => $pedido->numero,
            'transportadora' => $dados['transportadora'] ?? $pedido->transportadoraLabel(),
            'codigo' => $codigo,
            'rastreio' => $dados,
        ]);
    }
}
