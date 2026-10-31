<?php

namespace App\Services\GoogleAds\Excecoes;

use RuntimeException;
use Throwable;

/**
 * A escrita no Google Ads parou no meio.
 *
 * O que a tela mostra é `motivoPublico`, em português e sem jargão; o texto cru da API (que
 * vem em inglês) fica na mensagem da exceção e no log da chamada, para quem for investigar.
 * A separação existe porque um operador não decide nada com "INVALID_ARGUMENT: The request
 * contains an invalid argument", mas decide com "o Google recusou a palavra-chave".
 */
final class GoogleAdsFalhou extends RuntimeException
{
    public function __construct(
        public readonly string $motivoPublico,
        string $detalheTecnico = '',
        ?Throwable $anterior = null,
    ) {
        parent::__construct($detalheTecnico !== '' ? $detalheTecnico : $motivoPublico, 0, $anterior);
    }
}
