<?php

namespace App\Services\Assistente\Contratos;

use App\Services\Assistente\Chamada;
use App\Services\Assistente\Resposta;

/**
 * O provedor sabe transformar áudio em texto.
 *
 * A `Chamada` é a mesma da conversa de propósito: quem transcreve recebe o áudio, a pergunta que
 * o cliente escreveu junto e o prazo, e devolve o texto. Assim o pipeline de mídia não precisa
 * de um segundo tipo de objeto para uma coisa que já é uma chamada.
 */
interface PodeTranscrever
{
    public function transcrever(Chamada $chamada): Resposta;
}
