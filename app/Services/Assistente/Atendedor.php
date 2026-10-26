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

        // As fontes são achadas uma vez e servem às duas coisas: alimentar a chamada e registrar na
        // fala o que a sustentou. Pedir duas vezes à mesma busca sairia diferente se alguém
        // editasse o conhecimento entre as duas — e a prova gravada deixaria de ser a da resposta.
        $fontes = Busca::fontes($pergunta);

        $resposta = Motor::responder(new Chamada(
            pergunta: $pergunta,
            instrucoes: explode("\n", $ficha->instrucoes()),
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
            self::escalar($conversa, $resposta->motivoDeEscalada ?? 'sem_fonte', $ultima);
        }

        self::tocarUltimaFala($conversa);

        return $fala;
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
     */
    private static function escalar(Conversa $conversa, string $motivo, ConversaMensagem $pergunta): void
    {
        $conversa->escalar($motivo);

        // O sino é o único aviso que chega mesmo sem SMTP: a conversa já está esperando na caixa
        // do painel, e é ali que a equipe a encontra quando abre o sistema.
        Notifica::disparar(
            'conversa_escalada',
            'Conversa esperando a equipe: '.($conversa->nome ?: 'visitante do site'),
            $pergunta->textoVisivel(),
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
            Mail::to($destino)->queue(new ConversaEscalada($conversa, $motivo, $pergunta));
        } catch (Throwable $falha) {
            report($falha);
        }
    }

    private static function tocarUltimaFala(Conversa $conversa): void
    {
        $conversa->forceFill(['ultima_fala_em' => now()])->save();
    }
}
