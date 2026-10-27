<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Assistente;
use App\Models\Conversa;
use App\Models\ConversaMensagem;
use App\Services\Assistente\Atendedor;
use App\Services\Assistente\Avaliacao;
use App\Services\Rastreio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A bolha de atendimento no site e na página de funil.
 *
 * Quatro endereços JSON e nenhuma página: o robô mora num canto da tela que já existe, então não
 * faz sentido ele ter URL própria — e o visitante que abre a bolha num funil de anúncio não pode
 * ser levado para fora dele bem na hora da conversão. O cadastro continua sendo do formulário
 * que a casa já tem (`site/partials/lead-form`): a bolha leva o visitante até ele e a conversa é
 * ligada ao lead quando ele preenche, em vez de montar um segundo formulário dentro do balão.
 *
 * A conversa vive na sessão. Isso é o que permite ao anônimo continuar a mesma conversa de página a
 * página sem login e sem cookie de third party, e é também o que impede alguém de bisbilhotar a
 * fila inteira trocando um id no endereço: sem a sessão, não há conversa para mostrar.
 *
 * Ninguém responde a um robô. O índice de origem é o mesmo do resto da casa (`Atribuidor`), e um
 * user-agent de indexador não abre conversa — o outro jeito é o painel encher de "conversas" que
 * nenhum humano leu.
 */
class AssistenteController extends Controller
{
    public function __construct(private readonly Rastreio $rastreio) {}

    /** Abre (ou continua) a conversa e devolve o que a bolha precisa pintar. */
    public function abrir(Request $request): JsonResponse
    {
        if (! $this->atende($request)) {
            return response()->json(['ativo' => false]);
        }

        $ficha = Assistente::paraABolha();
        $conversa = $this->conversa($request);
        $novas = $conversa->mensagens()->where('id', '>', (int) $request->input('de', 0))->get();

        // A abertura é a primeira fala e só existe uma vez: voltar à página não pode repetir o
        // "olá" na tela de quem já está no meio de uma conversa. Ela sai pela boca do atendedor
        // porque é ele quem tem a régua do nome — a saudação e o pedido vêm juntos daqui.
        if ($novas->isEmpty() && $conversa->mensagens()->count() === 0) {
            $novas = collect(Atendedor::abertura($conversa, $ficha));
        }

        return $this->resposta($conversa, $novas);
    }

    /** A fala do cliente entra, e a resposta do assistente volta no mesmo pedido. */
    public function fala(Request $request): JsonResponse
    {
        if (! $this->atende($request)) {
            return response()->json(['ativo' => false], 403);
        }

        $dados = $request->validate([
            'texto' => ['required', 'string', 'max:1000'],
        ], [], ['texto' => 'mensagem']);

        $conversa = $this->conversa($request);
        $antes = (int) $conversa->mensagens()->max('id');

        Atendedor::dizer($conversa, strip_tags($dados['texto']));

        // Voltam as duas falas — a do cliente e a do assistente — porque é a resposta que pinta a
        // tela, não o que o navegador achou que ia ser aceito: o texto do cliente passa pelo
        // aparador e pela régua de tamanho aqui, e o que o cliente lê é o que foi gravado.
        return $this->novasFalas($conversa, $antes);
    }

    /** A varredura da bolha aberta: é por ela que a resposta da equipe chega ao cliente. */
    public function falas(Request $request): JsonResponse
    {
        $conversa = Conversa::naSessao($request);

        if (! $conversa) {
            return response()->json(['ativo' => false]);
        }

        return $this->novasFalas($conversa, (int) $request->input('de', 0));
    }

    private function novasFalas(Conversa $conversa, int $de): JsonResponse
    {
        return $this->resposta($conversa, $conversa->mensagens()->where('id', '>', $de)->get());
    }

    /**
     * A nota dada no toque das estrelas. É o mesmo número que a nota escrita no chat grava, por
     * outro caminho: quem avaliaria o atendimento digitando "4" depois de uma frase qualquer
     * deixaria a pesquisa sem resposta, e a casa ficaria cega justamente sobre o atendimento que a
     * pessoa encerrou sem falar mais nada.
     *
     * Só vale enquanto a conversa é da sessão desta pessoa e a pesquisa está aberta: nota repetida
     * não sobrescreve a primeira, e um toque fora de hora devolve a conversa como está.
     */
    public function avaliar(Request $request): JsonResponse
    {
        $conversa = Conversa::naSessao($request);

        if (! $conversa || ! Avaliacao::pedidaSemNota($conversa)) {
            return response()->json(['ativo' => false]);
        }

        $dados = $request->validate([
            'nota' => ['required', 'integer', 'between:1,'.Avaliacao::NOTA_MAX],
        ], [], ['nota' => 'nota']);

        $antes = (int) $conversa->mensagens()->max('id');

        Avaliacao::grave($conversa, (int) $dados['nota']);
        Atendedor::agradeceAValiacao($conversa);

        return $this->resposta($conversa, $conversa->mensagens()->where('id', '>', $antes)->get());
    }

    /** O envelope dos quatro endpoints da bolha: o que pintar, em que situação a conversa está. */
    private function resposta(Conversa $conversa, iterable $falas): JsonResponse
    {
        return response()->json([
            'ativo' => true,
            'conversa' => (int) $conversa->id,
            'status' => $conversa->status,
            'avaliacao' => Avaliacao::pedidaSemNota($conversa),
            'falas' => $this->falasParaTela($falas),
        ]);
    }

    /**
     * A fala no tamanho que o navegador pinta. O texto visível é o que a tela mostra — corpo, ou
     * a transcrição quando o cliente mandou áudio — e nunca sai cru do banco para o HTML.
     *
     * @param  iterable<ConversaMensagem>  $falas
     * @return array<int, array<string, mixed>>
     */
    private function falasParaTela(iterable $falas): array
    {
        return collect($falas)->map(fn (ConversaMensagem $fala): array => [
            'id' => (int) $fala->id,
            'autor' => $fala->autor,
            'tipo' => $fala->tipo,
            'assinatura' => $fala->assinatura(),
            'texto' => $fala->textoVisivel(),
            'midia' => $fala->midiaUrl(),
            'midia_nome' => $fala->midia_nome,
            'audio' => $fala->audioUrl(),
            'em' => $fala->created_at?->format('H:i'),
        ])->values()->all();
    }

    /** O robô atende esta página, para este visitante? É a única porta dos quatro endpoints. */
    private function atende(Request $request): bool
    {
        return Assistente::atendeVisitante($this->canalDaPagina($request));
    }

    private function canalDaPagina(Request $request): string
    {
        return $request->input('canal') === 'funil' || $request->routeIs('site.funil') ? 'funil' : 'site';
    }

    /** A conversa da sessão, criando-a quando o visitante fala pela primeira vez. */
    private function conversa(Request $request): Conversa
    {
        $conversa = Conversa::naSessao($request);

        if ($conversa !== null) {
            return $conversa;
        }

        $conversa = Conversa::abrir(
            $this->canalDaPagina($request),
            Str::limit('/'.ltrim($request->input('pagina', $request->path()), '/'), 190, ''),
            $this->rastreio->visitaAtual($request)?->id,
        );

        $request->session()->put(Conversa::SESSAO, $conversa->id);

        return $conversa;
    }
}
