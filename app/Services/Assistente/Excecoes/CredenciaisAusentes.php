<?php

namespace App\Services\Assistente\Excecoes;

use App\Support\ChavesDeIntegracao;
use RuntimeException;

/**
 * Falha de configuração, não de atendimento: o provedor escolhido não tem chave em lugar nenhum.
 *
 * A mensagem carrega o nome da chave e o caminho da tela, porque é assim que o painel devolve o
 * problema em português em vez de um HTTP 500 — a mesma régua das credenciais do Google Ads, que
 * dizem **qual** falta em vez de quebrar.
 */
final class CredenciaisAusentes extends RuntimeException
{
    public function __construct(string $rotulo, string $ambiente)
    {
        parent::__construct(
            "O provedor {$rotulo} está escolhido e a chave dele não está gravada no banco nem no .env "
            ."({$ambiente}). Preencha em ".ChavesDeIntegracao::CAMINHO_NO_PAINEL.'.'
        );
    }
}
