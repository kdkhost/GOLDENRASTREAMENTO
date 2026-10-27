<?php

namespace App\Services\Assistente;

use App\Mail\ConversaEscalada;
use App\Models\Assistente;
use App\Models\Conversa;
use App\Models\ConversaMensagem;
use App\Models\Lead;
use App\Models\Setting;
use App\Services\Atribuidor;
use App\Support\Notifica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * O atendimento em si: recebe a fala do cliente e devolve a resposta na mesma conversa.
 *
 * É um lugar só porque o atendimento acontece em três — a bolha do site, a bolha do funil e o
 * chat do chamado — e as três precisam da mesma régua: a mesma persona, a mesma busca de fonte, o
 * mesmo teto de caracteres, a mesma escalada quando não há lastro. Se cada superfície montasse a
 * sua chamada, a primeira diferença de preço entre o site e o portal estaria garantida.
 *
 * Quem já foi assumido pela equipe não recebe mais fala da máquina (`atender()` devolve nulo):
 * dois atendentes na mesma conversa é a maneira mais rápida de o cliente deixar de acreditar em
 * qualquer um dos dois.
 */
final class Atendedor
{
    /**
     * Grava a fala do cliente e responde. A resposta é devolvida para quem monta a tela; quando a
     * máquina está desligada ou a equipe já assumiu, não há resposta e só fica a fala.
     */
    public static function dizer(Conversa $conversa, string $texto, string $tipo = 'texto'): ?ConversaMensagem
    {
        ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'cliente',
            'tipo' => $tipo,
            'corpo' => mb_substr(trim($texto), 0, (int) config('assistente.resposta.caracteres_max', 900) * 3),
        ]);

        self::tocarUltimaFala($conversa);

        return self::atender($conversa);
    }

    /**
     * Responde a última fala do cliente que ainda está sem resposta. Separado de `dizer()` porque
     * a mídia (áudio, imagem, documento) chega antes por outro caminho: a fala é gravada no
     * upload, e a resposta sai quando o arquivo está no disco.
     *
     * A ordem das quatro perguntas abaixo é o atendimento inteiro, e ela não é trocável:
     *
     * **Ação em curso manda.** Quem está no meio de uma conferência de segurança respondeu a uma
     * pergunta definida; desviar essa fala para a IA faria a máquina responder "não entendi" no
     * passo mais delicado da conversa.
     *
     * **O nome vem do que a pessoa diz.** Ele é tirado da fala antes de qualquer assunto, porque é
     * ele que deixa o atendimento formal — e um nome gravado tarde ainda chega a tempo da fatura.
     * Quando a fala é só a resposta à pergunta pelo nome, a máquina reconhece a pessoa com texto
     * fixo e para ali: ninguém fez pergunta nenhuma ao dizer como se chama.
     *
     * **A ação nova é achada antes de a máquina abrir a boca.** O gatilho é léxico e determinístico:
     * uma IA decidir se "abre ou não" uma conferência de dados é a régua dependente do chute.
     *
     * **O resto é conversa**, com a fonte obrigatória e o nome no texto de ordem.
     */
    public static function atender(Conversa $conversa): ?ConversaMensagem
    {
        $ficha = Assistente::garanta();

        if (! $ficha->ativo || $conversa->respondido_por_equipe_em !== null) {
            return null;
        }

        $ultima = $conversa->ultimaMensagem();

        if (! $ultima || $ultima->autor !== 'cliente') {
            return null;
        }

        $pergunta = $ultima->textoVisivel();

        if (blank($pergunta)) {
            return null;
        }

        $acao = Acoes::emCurso($conversa);

        if ($acao !== null) {
            return self::fale($conversa, $acao->atender($conversa, $pergunta));
        }

        $nomeDito = self::guardeONome($conversa, $pergunta, $ficha);

        $pedida = Acoes::pedidaPela($pergunta);

        if ($pedida !== null) {
            return self::fale($conversa, $pedida->abrir($conversa));
        }

        // A resposta à pergunta pelo nome não é pergunta: é apresentação. Reconhecer com uma fala
        // fixa custa zero chamada de IA e não escala a conversa de quem só se apresentou — mandar
        // "Maria Aparecida" ao provedor era o jeito mais educado de dizer "chamei a equipe".
        if ($nomeDito !== null) {
            return self::fale($conversa, $ficha->textoConfirmacaoNome($nomeDito));
        }

        $pedeONome = self::vaiPedirONome($conversa);

        // As fontes são achadas uma vez e servem às duas coisas: alimentar a chamada e registrar na
        // fala o que a sustentou. Pedir duas vezes à mesma busca sairia diferente se alguém
        // editasse o conhecimento entre as duas — e a prova gravada deixaria de ser a da resposta.
        $fontes = Busca::fontes($pergunta);

        $resposta = Motor::responder(new Chamada(
            pergunta: $pergunta,
            instrucoes: self::ordemDaConversa($conversa, $ficha, $pedeONome),
            historico: self::historico($conversa, $ultima),
            fontes: $fontes,
            conversaId: $conversa->id,
        ));

        $fala = ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'assistente',
            'tipo' => 'texto',
            'corpo' => mb_substr(trim($resposta->texto), 0, (int) config('assistente.resposta.caracteres_max')),
            'fontes_json' => $resposta->respondeu() ? self::fontesParaLog($fontes) : [],
            'provedor' => $resposta->provedor,
            'modelo' => $resposta->modelo,
            'tokens_entrada' => $resposta->tokensEntrada,
            'tokens_saida' => $resposta->tokensSaida,
        ]);

        if (! $resposta->respondeu()) {
            self::chameEquipe($conversa, $resposta->motivoDeEscalada ?? 'sem_fonte', $pergunta);
        }

        self::tocarUltimaFala($conversa);

        return $fala;
    }

    /**
     * A fala da máquina que não saiu de uma IA: passo de ação ou pergunta pelo nome. Ela entra na
     * conversa sem provedor nem modelo gravados, porque a tela precisa mostrar de onde veio cada
     * frase — e esta veio do código, que é justamente o que a equipe não pode discutir.
     */
    private static function fale(Conversa $conversa, string $texto): ConversaMensagem
    {
        $fala = ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'assistente',
            'tipo' => 'texto',
            'corpo' => mb_substr(trim($texto), 0, (int) config('assistente.resposta.caracteres_max')),
        ]);

        self::tocarUltimaFala($conversa);

        return $fala;
    }

    /**
     * O nome de quem fala, aproveitado de qualquer frase. A máquina só tenta adivinhar quando a
     * última fala do assistente foi a pergunta pelo nome: aí "Maria de Jesus" é resposta, e fora
     * desse estado uma resposta solta poderia virar nome de gente por engano.
     *
     * Devolve o nome quando a fala é só ele, resposta direta à pergunta — caso em que não há
     * pergunta nenhuma a mandar ao provedor, e a máquina reconhece a pessoa com a frase da ficha.
     */
    private static function guardeONome(Conversa $conversa, string $pergunta, Assistente $ficha): ?string
    {
        if (filled($conversa->nome)) {
            return null;
        }

        $direta = self::acabouDePerguntarONome($conversa, $ficha);
        $nome = Nome::extrair($pergunta, respostaDireta: $direta);

        if ($nome === null) {
            return null;
        }

        $conversa->update(['nome' => $nome]);

        return $direta && Nome::soONome($pergunta) ? $nome : null;
    }

    /** A última fala do assistente nesta conversa foi exatamente a pergunta pelo nome? */
    private static function acabouDePerguntarONome(Conversa $conversa, Assistente $ficha): bool
    {
        $pergunta = $conversa->mensagens()
            ->where('autor', 'assistente')
            ->reorder('id', 'desc')
            ->first();

        return $pergunta !== null && $pergunta->corpo === $ficha->textoPerguntaNome();
    }

    /**
     * As falas que abrem a bolha: a saudação da casa e, logo depois, o pedido de nome. Duas falas e
     * não uma frase só porque a casa atende pelo nome — e quem espera a terceira frase para
     * perguntá-lo já perdeu as duas primeiras para um "Prezado cliente".
     *
     * Quem abre a conversa é a bolha, mas a régua é desta classe: o mesmo teto de
     * `assistente.nome.perguntas_max` que vale no meio do fio vale aqui, e é por isso que as duas
     * falam pela boca de `fale()`.
     *
     * @return array<int, ConversaMensagem>
     */
    public static function abertura(Conversa $conversa, Assistente $ficha): array
    {
        $falas = [];

        if (filled($ficha->saudacao)) {
            $falas[] = self::fale($conversa, (string) $ficha->saudacao);
        }

        if (self::vaiPedirONome($conversa)) {
            $falas[] = self::fale($conversa, $ficha->textoPerguntaNome());
        }

        return $falas;
    }

    /**
     * O pedido de nome desta rodada: ligado quando a casa manda pedir, a pessoa ainda não tem nome
     * na conversa e o teto de `assistente.nome.perguntas_max` não foi gasto. A contagem é gravada
     * aqui, e não na fala, porque quem decide parar de perguntar precisa de prova — e porque a
     * máquina que pergunta o nome no meio de uma conferência de dados é a que entrega a fatura de
     * qualquer um.
     */
    private static function vaiPedirONome(Conversa $conversa): bool
    {
        if (filled($conversa->nome) || ! Nome::pedir()) {
            return false;
        }

        if ((int) $conversa->nome_pedidos >= Nome::perguntasMax()) {
            return false;
        }

        $conversa->update(['nome_pedidos' => (int) $conversa->nome_pedidos + 1]);

        return true;
    }

    /**
     * A ordem de sistema desta conversa: a persona da ficha mais o que se sabe de quem está do
     * outro lado. É aqui que o nome entra na boca da máquina, porque o atendimento formal da casa
     * não pode depender de a IA ter lido a frase em que a pessoa se apresentou.
     */
    private static function ordemDaConversa(Conversa $conversa, Assistente $ficha, bool $pedeONome): array
    {
        $ordem = explode("\n", $ficha->instrucoes());

        if (filled($conversa->nome)) {
            $ordem[] = 'A pessoa do outro lado se chama '.$conversa->nome.'. Trate-a pelo nome quando fizer sentido, sem repeti-lo em toda frase.';
        } elseif ($pedeONome) {
            $ordem[] = 'Você ainda não sabe o nome desta pessoa: pergunte-o nesta resposta, com naturalidade e uma única vez, e siga atendendo o assunto.';
        } elseif (Nome::pedir()) {
            $ordem[] = 'Esta pessoa não quis se identificar. Atenda sem nome e não volte a pedir.';
        }

        return $ordem;
    }

    /**
     * O visitante cadastrou-se pelo formulário da casa com a bolha aberta no mesmo navegador: a
     * conversa ganha o lead e a fala de sistema que amarra as duas. É o único ponto em que o
     * atendimento anônimo vira negócio — e é aqui, e não no formulário, porque o formulário não
     * precisa saber que existe um robô de chat.
     */
    public static function fezContato(Lead $lead, Request $request): void
    {
        $conversa = Conversa::naSessao($request);

        if ($conversa === null || $conversa->lead_id === $lead->id) {
            return;
        }

        $conversa->update([
            'lead_id' => $lead->id,
            'nome' => $conversa->nome ?: Str::limit((string) $lead->name, 90, ''),
            'contato' => $conversa->contato ?: (string) ($lead->phone !== '-' ? $lead->phone : $lead->email),
        ]);

        ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'sistema',
            'tipo' => 'texto',
            'corpo' => 'O cadastro virou o lead '.$lead->id.' ('.Atribuidor::rotuloCanal((string) $lead->canal)
                .'). A equipe continua por aqui.',
        ]);

        self::tocarUltimaFala($conversa);
    }

    /**
     * As falas que o provedor enxerga antes da pergunta: as últimas, contadas de trás para frente,
     * sem a fala que está sendo respondida — ela já vai no campo `pergunta` da chamada.
     *
     * @return array<int, array{autor: string, texto: string}>
     */
    private static function historico(Conversa $conversa, ConversaMensagem $atual): array
    {
        $limite = (int) config('assistente.resposta.historico_mensagens');

        return $conversa->mensagens()
            ->where('id', '<', $atual->id)
            ->reorder('id', 'desc')
            ->take($limite)
            ->get()
            ->reverse()
            ->map(fn (ConversaMensagem $fala): array => ['autor' => $fala->autor, 'texto' => $fala->textoVisivel()])
            ->values()
            ->all();
    }

    /** As fontes achadas, do jeito que a tela de atendimento precisa lê-las para conferir. */
    private static function fontesParaLog(array $fontes): array
    {
        return array_map(
            fn (array $fonte): array => ['titulo' => $fonte['titulo'], 'procedencia' => $fonte['procedencia']],
            $fontes
        );
    }

    /**
     * A máquina parou: a conversa entra na fila da equipe e o e-mail de atendimento é avisado — o
     * mesmo endereço que recebe chamado e pedido, porque duas caixas de entrada para a mesma
     * equipe é como se perde um aviso. O envio é uma tentativa: sem SMTP no ar a conversa continua
     * marcada como pendente na caixa do painel, e o cliente já tem a resposta que a tela deu.
     *
     * É público porque a parada não vem só do `Motor`: uma ação que não conseguiu enviar precisa do
     * mesmo caminho, e o que muda entre os dois é apenas a prova de por que parou.
     */
    public static function chameEquipe(Conversa $conversa, string $motivo, string $prova): void
    {
        $conversa->escalar($motivo);

        // O sino é o único aviso que chega mesmo sem SMTP: a conversa já está esperando na caixa
        // do painel, e é ali que a equipe a encontra quando abre o sistema.
        Notifica::disparar(
            'conversa_escalada',
            'Conversa esperando a equipe: '.($conversa->nome ?: 'visitante do site'),
            $prova,
            (string) $conversa->id,
            [
                'conversa_id' => $conversa->id,
                'motivo' => $motivo,
                'visitante' => $conversa->nome,
                'canal' => $conversa->canal,
                'pagina' => $conversa->pagina,
            ],
            'conversa_escalada:'.$conversa->id,
        );

        $destino = Setting::get('contato_email', (string) config('golden.email'));

        if (! (bool) config('assistente.resposta.avisar_por_email') || $destino === '' || $destino === null) {
            return;
        }

        try {
            Mail::to($destino)->queue(new ConversaEscalada($conversa, $motivo, $prova));
        } catch (Throwable $falha) {
            report($falha);
        }
    }

    private static function tocarUltimaFala(Conversa $conversa): void
    {
        $conversa->forceFill(['ultima_fala_em' => now()])->save();
    }
}
