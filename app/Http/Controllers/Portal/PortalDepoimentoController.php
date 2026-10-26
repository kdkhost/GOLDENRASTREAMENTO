<?php

namespace App\Http\Controllers\Portal;

use App\Mail\DepoimentoRecebido;
use App\Models\Activity;
use App\Models\Testimonial;
use App\Support\Notifica;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * O cliente conta a experiência dele — e a casa decide se ela vai para o ar.
 *
 * A ficha é a assinatura: `customer_id` grava quem escreveu e `fonte` marca que veio do portal,
 * então a aprovação no painel acontece sabendo que o autor é cliente ativo, não um visitsante
 * qualquer. O texto nasce `aprovado = false`: depoimento publicado é decisão da equipe, e o
 * site mostra os três tipos de origem misturados no mesmo carrossel.
 */
class PortalDepoimentoController extends PortalBaseController
{
    public function mostrar(): View
    {
        $cliente = $this->cliente();

        return view('portal.depoimento', [
            'cliente' => $cliente,
            'meus' => Testimonial::where('customer_id', $cliente->id)->latest()->limit(5)->get(),
            'aguardando' => Testimonial::where('customer_id', $cliente->id)->where('aprovado', false)->exists(),
            'podeEscrever' => $cliente->status === 'ativo',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cliente = $this->cliente();

        if ($cliente->status !== 'ativo') {
            return back()->with('error', 'Só a ficha ativa na Golden pode publicar um depoimento. Fale com a equipe para reativar o seu cadastro.');
        }

        if (Testimonial::where('customer_id', $cliente->id)->where('aprovado', false)->exists()) {
            return back()->with('warning', 'Já temos um depoimento seu aguardando a aprovação da equipe — não precisa enviar de novo.');
        }

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'papel' => ['nullable', 'string', 'max:120'],
            'texto' => ['required', 'string', 'min:20', 'max:1200'],
            'nota' => ['required', 'integer', 'min:1', 'max:5'],
        ], [], ['nome' => 'nome', 'papel' => 'papel', 'texto' => 'depoimento', 'nota' => 'nota']);

        $depoimento = Testimonial::create([
            'nome' => $dados['nome'],
            'papel' => ($dados['papel'] ?? '') ?: 'Cliente Golden',
            'cidade' => $cliente->cidade ?: null,
            'texto' => $dados['texto'],
            'nota' => $dados['nota'],
            'fonte' => 'cliente',
            'customer_id' => $cliente->id,
            'aprovado' => false,
            'ordem' => 0,
        ]);

        Activity::log('portal_depoimento_enviado', "Depoimento de {$depoimento->nome} enviado pelo portal.", $depoimento, por: $cliente->nome);
        $this->avisarEquipe(new DepoimentoRecebido($depoimento));

        Notifica::disparar(
            'depoimento_novo',
            'Depoimento novo de '.$cliente->nome.' ('.\str_repeat('★', (int) $depoimento->nota).')',
            $depoimento->texto,
            (string) $depoimento->id,
            [
                'depoimento_id' => $depoimento->id,
                'nome' => $depoimento->nome,
                'cliente_id' => $cliente->id,
                'nota' => $depoimento->nota,
            ],
            'depoimento_novo:'.$depoimento->id,
        );

        return redirect()->route('portal.depoimento')
            ->with('success', 'Obrigada! Seu depoimento foi enviado para a equipe e aparece no site assim que for aprovado.');
    }
}
