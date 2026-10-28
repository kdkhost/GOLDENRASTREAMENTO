<?php

use App\Models\Setting;
use App\Support\FusoHistorico;
use Illuminate\Database\Migrations\Migration;

/**
 * Atrasam o histórico que o relógio da hospedagem gravou três horas adiantado.
 *
 * O 2.48.0 consertou o fuso da aplicação, mas só a partir dele: o que já estava no banco continua
 * com o instante errado, porque a hospedagem acorda em UTC e o MySQL vive em −3. É esta migration
 * que leva a fala da Ana de `01:30` de volta às `22:30` em que ela foi escrita.
 *
 * Ela roda uma vez só e diz isso em voz alta: a marca `fuso_correcao_do_registro_em` fica na
 * tabela de configurações, porque rodar a mesma conta duas vezes jogaria o histórico seis horas
 * para trás. O `down()` desfaz o passe — o caminho de volta está aberto enquanto houver dúvida.
 *
 * A decisão linha por linha, inclusive a da faixa em que o valor sozinho não prova nada, é de
 * `App\Support\FusoHistorico`.
 */
return new class extends Migration
{
    /** A chave que conta que este passe já foi dado. */
    private const MARCA = 'fuso_correcao_do_registro_em';

    public function up(): void
    {
        if ((string) Setting::get(self::MARCA, '') !== '') {
            echo 'Histórico já corrigido em '.Setting::get(self::MARCA).'. Nada a fazer.';

            return;
        }

        $relatorio = FusoHistorico::corrigir();

        $linhas = array_sum(array_column($relatorio['colunas'], 'adiantadas'));
        $faixa = array_sum(array_column($relatorio['colunas'], 'na_faixa'));
        $sobram = array_sum(array_column($relatorio['colunas'], 'sobram_na_faixa'));

        Setting::set(self::MARCA, date('Y-m-d H:i:s'));

        echo "Adiantadas: {$linhas} | na faixa duvidosa, resolvidas pelo corte de ids: {$faixa} | sem corte claro (intactas): {$sobram}";
    }

    public function down(): void
    {
        if ((string) Setting::get(self::MARCA, '') === '') {
            return;
        }

        FusoHistorico::corrigir(horas: -FusoHistorico::HORAS);

        Setting::set(self::MARCA, '');
    }
};
