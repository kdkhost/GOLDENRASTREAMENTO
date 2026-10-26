<?php

namespace App\Services\Assistente\Contratos;

use App\Services\Assistente\Chamada;
use App\Services\Assistente\Resposta;

/**
 * O provedor sabe conversar — com texto e, quando ele mesmo diz que sim, com mídia junto.
 *
 * Um provedor que não implementa esta interface não aparece na lista de capacidades da tela de
 * chaves: o painel não oferece o que a máquina não sabe fazer, em vez de oferecer e falhar na
 * primeira mensagem.
 */
interface PodeConversar
{
    public function responder(Chamada $chamada): Resposta;
}
