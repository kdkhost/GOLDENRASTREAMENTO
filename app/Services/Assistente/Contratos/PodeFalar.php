<?php

namespace App\Services\Assistente\Contratos;

use App\Services\Assistente\Chamada;
use App\Services\Assistente\Resposta;

/**
 * O provedor sabe falar: devolve áudio a partir de um texto, na voz que o painel escolheu.
 *
 * A resposta é `Resposta::deAudio()`, com os bytes crus e o tipo do arquivo. Quem grava no disco
 * e quem amarra o arquivo à mensagem é a camada de atendimento, porque só ela conhece a conversa
 * — o provedor conhece a API.
 */
interface PodeFalar
{
    public function falar(Chamada $chamada): Resposta;
}
