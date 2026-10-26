<?php

namespace App\Services\Assistente\Excecoes;

use RuntimeException;

/**
 * O provedor escolhido para a capacidade não sabe fazer aquilo.
 *
 * É o caso do documento no OpenAI: a API de chat não lê PDF, então a tela não oferece aquela
 * combinação e, se alguém a escrever direto no banco, o erro diz isso por extenso — em vez de
 * mandar um anexo que a IA responderia com um texto vazio.
 */
final class CapacidadeNaoOferecida extends RuntimeException
{
    public function __construct(string $rotulo, string $capacidade)
    {
        $nome = (string) (config('assistente.capacidades')[$capacidade]['rotulo'] ?? $capacidade);

        parent::__construct("O provedor {$rotulo} não oferece a capacidade \"{$nome}\". Escolha outro modelo em "
            .'Sistema → Configurações → Chaves de integração.');
    }
}
