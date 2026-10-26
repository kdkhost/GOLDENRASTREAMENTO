<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Support\ChavesDeIntegracao;
use App\Support\DepoimentosDoGoogle;
use Illuminate\Console\Command;

/**
 * Traz as avaliações do Google Business Profile para os depoimentos do site, com a foto de
 * quem avaliou. Serve tanto ao operador (linha de comando) quanto ao botão do painel, que
 * chama o mesmo DepoimentosDoGoogle::importar().
 *
 * A leitura depende de um perfil verificado há mais de 60 dias e de um token aprovado na
 * API do Google; enquanto as três chaves não tiverem valor na tela de chaves do painel (ou,
 * como plano de fundo, no `.env`), o comando explica o que falta em vez de falhar com o erro
 * da API.
 */
class ImportarDepoimentosDoGoogle extends Command
{
    protected $signature = 'depoimentos:google
                            {--limite= : Quantas avaliações ler (o padrão é golden.google_business.limite)}
                            {--apenas-leia : Mostrar o que a API devolve sem gravar nada}';

    protected $description = 'Importar as avaliações do Google (com avatar) para os depoimentos do site';

    public function handle(): int
    {
        if (! DepoimentosDoGoogle::configurado()) {
            $this->warn('A leitura das avaliações do Google ainda não está configurada. Falta:');

            foreach (ChavesDeIntegracao::grupos()['avaliacoes']['campos'] as $campo) {
                $gravada = filled(ChavesDeIntegracao::vigente($campo['chave']));

                $this->line('  '.$campo['label'].' ('.$campo['ambiente'].') — '.($gravada ? 'com valor' : 'sem valor'));
            }

            $this->line('Preencha em '.ChavesDeIntegracao::CAMINHO_NO_PAINEL.' → Avaliações do Google.');

            return self::FAILURE;
        }

        $limite = (int) ($this->option('limite') ?: config('golden.google_business.limite', 50));

        if ($this->option('apenas-leia')) {
            $linhas = collect(DepoimentosDoGoogle::avaliacoes($limite))
                ->map(fn (array $a) => [$a['nome'], $a['nota'].'★', mb_substr($a['texto'], 0, 60), $a['avatar_remoto'] ? 'sim' : 'não'])
                ->all();

            $this->table(['Autor', 'Nota', 'Trecho', 'Foto'], $linhas);
            $this->info(count($linhas).' avaliação(es) lidas de '.DepoimentosDoGoogle::recurso().'. Nada foi gravado.');

            return self::SUCCESS;
        }

        try {
            $resumo = DepoimentosDoGoogle::importar($limite);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        Activity::log('depoimentos_google_importados',
            'Importação do Google: '.$resumo['novos'].' novo(s), '.$resumo['atualizados'].' atualizado(s) de '.$resumo['lidos'].' leitura(s).');

        $this->info(sprintf(
            '%d leitura(s): %d novo(s), %d atualizado(s), %d sem texto, %d abaixo da nota mínima.',
            $resumo['lidos'], $resumo['novos'], $resumo['atualizados'], $resumo['sem_texto'], $resumo['abaixo_da_minima']
        ));

        return self::SUCCESS;
    }
}
