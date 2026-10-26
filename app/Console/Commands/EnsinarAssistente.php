<?php

namespace App\Console\Commands;

use App\Services\Assistente\Semeador;
use Illuminate\Console\Command;

/**
 * Ensina a base de fábrica ao assistente: quem ele é e o que a Golden vende.
 *
 * Seguro para rodar quantas vezes quiser. Sem opção nenhuma, só acrescenta o que falta e
 * regrava o catálogo (que vem dos produtos ativos); o que a equipe editou no painel fica
 * como está. `--atualizar` regrava os textos fixos e `--personalidade` aplica a persona —
 * as duas opções existem para quem quer voltar ao texto de fábrica de propósito.
 */
class EnsinarAssistente extends Command
{
    protected $signature = 'assistente:ensinar
                            {--atualizar : Regrava os textos fixos que já existem na base}
                            {--personalidade : Aplica nome, cargo, saudação, tom e regras da config na ficha}';

    protected $description = 'Treina o assistente com a base da Golden e, se pedido, aplica a personalidade';

    public function handle(Semeador $semeador): int
    {
        if ($this->option('personalidade')) {
            $mudou = $semeador->personalidade();
            $this->line($mudou ? 'Persona aplicada na ficha.' : 'A ficha já estava com a persona da config.');
        }

        $contagem = $semeador->conhecimento((bool) $this->option('atualizar'));

        $this->line(sprintf(
            'Conhecimento: %d criado(s), %d atualizado(s), %d já estava(m) lá.',
            $contagem['criados'],
            $contagem['atualizados'],
            $contagem['intactos'],
        ));

        $this->line($semeador->catalogo()
            ? 'Catálogo de produtos regravado com os preços vigentes.'
            : 'Nenhum produto ativo: o catálogo não foi gravado.');

        return self::SUCCESS;
    }
}
