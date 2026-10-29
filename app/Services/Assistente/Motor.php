<?php

namespace App\Services\Assistente;

use App\Models\Assistente;
use App\Models\AssistenteApiLog;
use App\Services\Assistente\Excecoes\CapacidadeNaoOferecida;
use App\Services\Assistente\Excecoes\CredenciaisAusentes;
use App\Services\Assistente\Provedores\Fabrica;
use Throwable;

/**
 * A porta de entrada do assistente — o único lugar por onde uma chamada de IA acontece.
 *
 * Duas regras moram aqui de propósito, para que nenhuma tela possa pulá-las:
 *
 * **Sem fonte, não se responde.** Com `assistente.resposta.so_com_fonte` ligado, uma pergunta de
 * conversa sem item de conhecimento achado pela `Busca` não chega ao provedor: a máquina escala
 * para a equipe. É a regra que impede o preço inventado, e ela vale para o site, para o funil e
 * para o chamado igualmente porque está na frente, não nas views. A exceção é a cortesia — "oi",
 * "bom dia" —, que não pergunta nada e por isso não tem fonte a exigir; sem ela, o cliente que só
 * cumprimentou recebia de volta "já chamei a equipe".
 *
 * **Nada de exceção no chat.** Uma chave que falta, um provedor que respondeu 429 ou um modelo que
 * mudou de nome viram `Resposta::escalada()` com o motivo gravado — o cliente lê "vou chamar
 * alguém da equipe", e a equipe lê na caixa de entrada *por que* a máquina parou. A exceção crua
 * só existe dentro desta camada; quem atende gente não pode quebrar na frente dela.
 */
final class Motor
{
    public static function responder(Chamada $chamada): Resposta
    {
        $capacidade = $chamada->capacidade ?? 'conversa';

        if ($capacidade === 'conversa' && self::exigeFonte() && $chamada->fontes === [] && ! $chamada->cortesia) {
            return Resposta::escalada('sem_fonte', Assistente::fraseDeEscalada());
        }

        return self::executar($capacidade, $chamada,
            static fn (object $provedor): Resposta => $provedor->responder($chamada),
            static fn (string $capacidade): object => Fabrica::conversante($capacidade),
        );
    }

    /** O áudio do cliente virou texto. Sem transcrição, a conversa não tem o que responder. */
    public static function transcrever(Chamada $chamada): Resposta
    {
        return self::executar('transcricao', $chamada,
            static fn (object $provedor): Resposta => $provedor->transcrever($chamada),
            static fn (string $capacidade): object => Fabrica::transcritor(),
        );
    }

    /**
     * A resposta escrita virá falada, se o painel ligou a voz e o provedor dela respondeu. Áudio é
     * acréscimo: quando ele falha, a devolução é nula e a mensagem de texto segue sozinha — um
     * cliente sem áudio continua atendido.
     */
    public static function falar(string $texto, ?string $voz = null, ?int $conversaId = null, ?int $userId = null): ?Resposta
    {
        if (! (bool) config('assistente.permissoes.audio_saida')) {
            return null;
        }

        $chamada = new Chamada(pergunta: $texto, capacidade: 'voz', conversaId: $conversaId, userId: $userId, voz: $voz);
        $resposta = self::executar('voz', $chamada,
            static fn (object $provedor): Resposta => $provedor->falar($chamada),
            static fn (string $capacidade): object => Fabrica::falante(),
        );

        return $resposta->respondeu() ? $resposta : null;
    }

    /** A frase que o cliente lê quando a máquina para, sempre a mesma, em qualquer superfície. */
    public static function fraseDeEscalada(): string
    {
        return Assistente::fraseDeEscalada();
    }

    public static function exigeFonte(): bool
    {
        return (bool) config('assistente.resposta.so_com_fonte');
    }

    /**
     * Resolve o provedor, chama e converte o que deu errado em escalada. O log de uma falha de
     * configuração é escrito aqui porque o provedor nunca chegou a existir para escrevê-lo — sem
     * esta linha, "o robô parou de responder porque alguém apagou a chave" não teria prova.
     */
    private static function executar(string $capacidade, Chamada $chamada, callable $pedir, callable $resolvedor): Resposta
    {
        try {
            return $pedir($resolvedor($capacidade));
        } catch (CredenciaisAusentes $falha) {
            self::registrarRecusa($capacidade, $falha->getMessage(), $chamada);

            return Resposta::escalada('provedor_sem_chave', self::fraseDeEscalada());
        } catch (CapacidadeNaoOferecida $falha) {
            self::registrarRecusa($capacidade, $falha->getMessage(), $chamada);

            return Resposta::escalada('midia_nao_lida', self::fraseDeEscalada());
        } catch (Throwable $falha) {
            self::registrarRecusa($capacidade, $falha->getMessage(), $chamada);

            return Resposta::escalada('provedor_com_erro', self::fraseDeEscalada());
        }
    }

    private static function registrarRecusa(string $capacidade, string $motivo, Chamada $chamada): void
    {
        $rodada = Fabrica::rodada($capacidade);

        try {
            AssistenteApiLog::create([
                'capacidade' => $capacidade,
                'provedor' => $rodada['provedor'] ?: 'nenhum',
                'modelo' => $rodada['modelo'] ?: null,
                'user_id' => $chamada->userId,
                'conversa_id' => $chamada->conversaId,
                'request_json' => ['pergunta' => $chamada->pergunta],
                'error_message' => mb_substr($motivo, 0, 1000),
            ]);
        } catch (Throwable) {
            // Sem caixa-preta a conversa segue: log não é atendimento.
        }
    }
}
