<?php

namespace App\Services\Assistente\Excecoes;

use RuntimeException;

/**
 * A chamada saiu e não veio como devia: HTTP de erro, tempo estourado ou resposta sem texto.
 *
 * Guarda o texto do provedor porque é ele que resolve discussão — cota, modelo que mudou de nome,
 * chave de outro projeto. O `Motor` transforma esta exceção em escalada para a equipe, nunca em
 * tela de erro para o cliente: quem está no chat não tem o que fazer com um HTTP 429.
 */
final class ChamadaFalhou extends RuntimeException
{
    public function __construct(string $mensagem, public readonly ?array $resposta = null, public readonly ?int $status = null)
    {
        parent::__construct($mensagem);
    }
}
