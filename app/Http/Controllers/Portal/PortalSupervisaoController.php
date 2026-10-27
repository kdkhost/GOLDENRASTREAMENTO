<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Customer;
use App\Services\Whmcs\Veiculacao;
use App\Support\Supervisao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Entrar e sair da conta de um cliente pelo painel, em supervisão.
 *
 * É um botão da ficha (Admin → Clientes) e uma saída do portal, e os dois vivem nesta classe para
 * que a regra da janela tenha uma casa só: quem abre, quem fecha, o que fica registrado e o que
 * faz um e outro. Não há aqui consulta ao WHMCS nem tela de leitura — a leitura é o portal do
 * cliente, tela por tela, com os filtros de `Veiculacao` que a pessoa já obedece. Um segundo jogo
 * de telas seria uma segunda verdade, e a promessa deste recurso é exatamente não ter duas.
 *
 * Quem faz o trabalho de travar a escrita não é o controller: é `AcessoSupervisionado`, em cada
 * requisição. Nada neste arquivo esconde botão ou depende de a tela ter lembrado de desabilitar
 * um formulário.
 */
class PortalSupervisaoController extends Controller
{
    /** Abre a janela: o painel entra no portal na pele da ficha, e sai dela em somente leitura. */
    public function entrar(Customer $cliente): RedirectResponse
    {
        abort_unless(
            Veiculacao::supervisao(),
            403,
            'A supervisão está desligada em Sistema → Configurações → Financeiro (WHMCS).'
        );

        if (! $cliente->temConta()) {
            return back()->with('error', "A ficha de {$cliente->nome} ainda não tem conta no portal — sem conta, não há o que supervisionar.");
        }

        if ($cliente->status !== 'ativo') {
            return back()->with('error', "A conta de {$cliente->nome} está ".Customer::label($cliente->status).': reative a ficha antes de abrir o portal dela.');
        }

        // O administrador é lido antes do login do cliente e pelo guard `web`, nunca por
        // `$request->user()`: uma vez autenticada a ficha, a framework passa a responder pelo
        // guard do portal, e a janela registraria no próprio cliente quem entrou nela.
        $admin = Auth::guard('web')->user();

        Auth::guard('cliente')->login($cliente);
        Supervisao::abrir($admin, $cliente);

        Activity::log(
            'portal_supervisao_aberta',
            "Abriu o portal de {$cliente->nome} em modo somente leitura.",
            $cliente,
            ['minutos' => Veiculacao::minutosDeSupervisao()]
        );

        return redirect()->route('portal.inicio')->with(
            'info',
            'Você está na conta de '.$cliente->nome.' — somente leitura, '.Veiculacao::minutosDeSupervisao().' minutos de janela.'
        );
    }

    /**
     * Fecha a janela. O painel continua aberto onde estava: sair da conta do cliente não é sair
     * do painel, e ninguém perde a ficha no meio de um atendimento.
     */
    public function sair(): RedirectResponse
    {
        $janela = Supervisao::dados();

        if ($janela === null) {
            return redirect()->route('portal.inicio')->with('info', 'Esta já é a sua própria conta.');
        }

        $cliente = Customer::find($janela['cliente_id']);

        Activity::log(
            'portal_supervisao_encerrada',
            $cliente === null
                ? 'Encerrou a supervisão da conta do cliente.'
                : "Encerrou a supervisão do portal de {$cliente->nome}.",
            $cliente
        );

        Auth::guard('cliente')->logout();
        Supervisao::limpar();

        $destino = $cliente === null
            ? route('admin.clientes.index')
            : route('admin.clientes.show', $cliente);

        return redirect()->to($destino)->with('success', 'Você voltou ao painel. Nada foi gravado na conta do cliente.');
    }
}
