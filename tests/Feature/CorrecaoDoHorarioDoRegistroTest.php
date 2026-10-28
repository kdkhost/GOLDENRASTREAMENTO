<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\Fuso;
use App\Support\FusoHistorico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A volta das três horas que o relógio da hospedagem adiantou no que já estava gravado.
 *
 * O fuso em si é conserto do 2.48.0; o que estes testes fecham é a régua do passe histórico, que
 * mexe em dado de produção e por isso não pode depender de chute:
 *
 * 1. **Antes da virada é certo.** Nenhuma linha do regime novo pode valer menos que a virada,
 *    então um valor mais baixo que ela só pode vir do regime velho — que gravava três horas à frente.
 * 2. **Na faixa duvidosa quem decide é a sequência de ids, não o relógio.** Ali dentro, a volta de
 *    três horas no meio da sequência é o momento em que o código novo assumiu; sem volta clara, as
 *    linhas ficam intactas e aparecem no relatório para um humano.
 * 3. **Data digitada por gente e contagem para a frente não são relógio adiantado.** Aniversário,
 *    celebração de contrato, próxima ação, `expira_em` e `limite_em` nunca saíram do fuso da casa.
 * 4. **O passe é uma vez só**, e o banco guarda a marca de quando foi dado.
 */
class CorrecaoDoHorarioDoRegistroTest extends TestCase
{
    use RefreshDatabase;

    private const VIRADA = '2026-10-27 22:46:45';

    private const FIM_DA_FAIXA = '2026-10-28 01:46:45';

    private function conversa(string $em, ?string $ultimaFala = null): int
    {
        return (int) DB::table('conversas')->insertGetId([
            'canal' => 'site',
            'status' => 'aberta',
            'ultima_fala_em' => $ultimaFala ?? $em,
            'created_at' => $em,
            'updated_at' => $em,
        ]);
    }

    private function fala(int $conversa, string $em, string $autor = 'assistente'): int
    {
        return (int) DB::table('conversas_mensagens')->insertGetId([
            'conversa_id' => $conversa,
            'autor' => $autor,
            'tipo' => 'texto',
            'corpo' => 'Fala gravada como '.$em.', escrita de verdade três horas antes.',
            'created_at' => $em,
            'updated_at' => $em,
        ]);
    }

    private function valor(int $id, string $tabela = 'conversas_mensagens', string $coluna = 'created_at'): string
    {
        return (string) DB::table($tabela)->where('id', $id)->value($coluna);
    }

    private function atividade(string $em): int
    {
        return (int) DB::table('activities')->insertGetId([
            'action' => 'login',
            'description' => 'linha da faixa duvidosa',
            'created_at' => $em,
            'updated_at' => $em,
        ]);
    }

    /** @return array{tabela: string, coluna: string, adiantadas: int, corte: ?int, na_faixa: int, sobram_na_faixa: int} */
    private function relato(array $relatorio, string $tabela, string $coluna): array
    {
        $linha = collect($relatorio['colunas'])
            ->first(fn (array $c): bool => $c['tabela'] === $tabela && $c['coluna'] === $coluna);

        $this->assertNotNull($linha, "A correção não prestou contas de {$tabela}.{$coluna}.");

        return $linha;
    }

    public function test_atrasa_o_que_o_relogio_da_hospedagem_gravou_tres_horas_a_frente(): void
    {
        // A conversa velha e suas falas caem na faixa duvidosa (foram escritas às 22:30 de verdade,
        // gravadas como 01:30). A conversa nova, de id mais alto, é o 2.48.0 já empossado: é a volta
        // dela que marca o corte e salva as três linhas anteriores.
        $velha = $this->conversa('2026-10-28 01:30:28', '2026-10-28 01:30:57');
        $fala = $this->fala($velha, '2026-10-28 01:30:43', 'cliente');
        $despedida = $this->fala($velha, '2026-10-28 01:30:57');
        $nova = $this->conversa('2026-10-27 22:50:00', '2026-10-27 22:55:00');
        $falaNova = $this->fala($nova, '2026-10-27 22:55:00', 'cliente');

        $relatorio = FusoHistorico::corrigir(virada: self::VIRADA);

        $this->assertSame('2026-10-27 22:30:43', $this->valor($fala));
        $this->assertSame('2026-10-27 22:30:57', $this->valor($despedida));

        // Os carimbos da conversa e o instante da última fala andam juntos: a tela de fila ordena
        // por um e mostra o outro, e um deles parado faria a conversa aparecer no futuro.
        $this->assertSame('2026-10-27 22:30:28', $this->valor($velha, 'conversas'));
        $this->assertSame('2026-10-27 22:30:28', $this->valor($velha, 'conversas', 'updated_at'));
        $this->assertSame('2026-10-27 22:30:57', $this->valor($velha, 'conversas', 'ultima_fala_em'));

        // O que já nasceu na hora certa não é tocado, nem o carimbo nem a fala.
        $this->assertSame('2026-10-27 22:50:00', $this->valor($nova, 'conversas'));
        $this->assertSame('2026-10-27 22:55:00', $this->valor($falaNova));

        $contaFala = $this->relato($relatorio, 'conversas_mensagens', 'created_at');
        $this->assertSame($falaNova, $contaFala['corte']);
        $this->assertSame(2, $contaFala['na_faixa']);
        $this->assertGreaterThan(6, array_sum(array_column($relatorio['colunas'], 'adiantadas'))
            + array_sum(array_column($relatorio['colunas'], 'na_faixa')));
    }

    public function test_atrasa_sem_piedade_o_que_e_anterior_a_virada(): void
    {
        // Valor abaixo da virada é prova basta: nenhuma linha do regime novo pode valer menos que
        // o instante em que ele assumiu. Aqui não há faixa duvidosa nem corte a procurar.
        $conversa = $this->conversa('2026-10-27 15:00:00');
        $fala = $this->fala($conversa, '2026-10-27 15:00:43', 'cliente');

        $relatorio = FusoHistorico::corrigir(virada: self::VIRADA);

        $this->assertSame('2026-10-27 12:00:43', $this->valor($fala));
        $this->assertSame('2026-10-27 12:00:00', $this->valor($conversa, 'conversas'));
        $this->assertSame(1, $this->relato($relatorio, 'conversas_mensagens', 'created_at')['adiantadas']);
        $this->assertNull($this->relato($relatorio, 'conversas_mensagens', 'created_at')['corte']);
    }

    public function test_deixa_intacta_o_que_ja_foi_gravado_na_hora_da_casa(): void
    {
        $conversa = $this->conversa('2026-10-28 09:14:00');
        $depois = $this->fala($conversa, '2026-10-28 09:15:00');

        FusoHistorico::corrigir(virada: self::VIRADA);

        $this->assertSame('2026-10-28 09:15:00', $this->valor($depois));
        $this->assertSame('2026-10-28 09:14:00', $this->valor($conversa, 'conversas'));
    }

    public function test_resolve_a_faixa_duvidosa_pela_volta_do_relogio_na_sequencia_de_ids(): void
    {
        // Quatro linhas na faixa [virada, virada+3h): as duas primeiras, de ids mais baixos, são o
        // regime velho já adiantado (foram escritas às 22:20 e 22:30 de verdade); as duas últimas
        // são o regime novo. O relógio não separa nada — a volta dele separa.
        $velhas = [
            $this->atividade('2026-10-28 01:20:00'),
            $this->atividade('2026-10-28 01:30:00'),
        ];
        $novas = [
            $this->atividade('2026-10-27 22:50:00'),
            $this->atividade('2026-10-27 23:10:00'),
        ];

        $relatorio = FusoHistorico::corrigir(virada: self::VIRADA);

        $this->assertSame('2026-10-27 22:20:00', $this->valor($velhas[0], 'activities'));
        $this->assertSame('2026-10-27 22:30:00', $this->valor($velhas[1], 'activities'));
        $this->assertSame('2026-10-27 22:50:00', $this->valor($novas[0], 'activities'));
        $this->assertSame('2026-10-27 23:10:00', $this->valor($novas[1], 'activities'));
        $this->assertSame($novas[0], $this->relato($relatorio, 'activities', 'created_at')['corte']);
    }

    public function test_sem_volta_clara_do_relogio_a_faixa_inteira_fica_para_o_humano(): void
    {
        // Uma faixa onde a sequência só sobe não tem prova de corte: pode ser tudo regime novo, ou
        // tudo velho sem sinal. Aí a correção não chuta — ela conta e sobra.
        $id = $this->atividade('2026-10-28 00:05:00');

        $relatorio = FusoHistorico::corrigir(virada: self::VIRADA);

        $linha = $this->relato($relatorio, 'activities', 'created_at');
        $this->assertNull($linha['corte']);
        $this->assertSame(0, $linha['na_faixa']);
        $this->assertSame(1, $linha['sobram_na_faixa']);
        $this->assertSame('2026-10-28 00:05:00', $this->valor($id, 'activities'));
    }

    public function test_a_lista_de_alvos_e_so_de_instante_escrito_pelo_sistema(): void
    {
        $alvos = array_map(fn (array $par): string => $par[0].'.'.$par[1], FusoHistorico::alvos());

        // Data digitada por gente ou contagem para a frente: nunca saíram do fuso da casa.
        foreach (FusoHistorico::INTACTAVEIS as [$tabela, $coluna]) {
            $this->assertNotContains($tabela.'.'.$coluna, $alvos, "{$tabela}.{$coluna} é preenchida por gente, não pelo relógio do servidor.");
        }

        // Carimbo de fila é inteiro e relógio de framework ninguém lê na tela.
        $this->assertNotContains('jobs.created_at', $alvos);
        foreach (FusoHistorico::FORA_DA_CORRECAO as $tabela) {
            $this->assertNotContains($tabela.'.created_at', $alvos);
        }

        $this->assertContains('conversas_mensagens.created_at', $alvos);
        $this->assertContains('visits.aberta_em', $alvos);
        $this->assertContains('leads.primeiro_toque_em', $alvos);
    }

    public function test_a_correcao_nao_deixa_rastro_de_atividade_de_hoje(): void
    {
        $conversa = $this->conversa('2026-10-27 15:00:00');
        $fala = $this->fala($conversa, '2026-10-27 15:00:43', 'cliente');

        FusoHistorico::corrigir(virada: self::VIRADA);

        // Salvar pelo modelo recarimbaria `updated_at` com a hora de agora e faria o histórico
        // parecer atividade do dia. Os dois carimbos andam juntos, três horas mais cedo.
        $this->assertSame($this->valor($fala), $this->valor($fala, 'conversas_mensagens', 'updated_at'));
        $this->assertSame('2026-10-27 12:00:43', $this->valor($fala, 'conversas_mensagens', 'updated_at'));
        $this->assertStringStartsWith('2026-10-27', $this->valor($conversa, 'conversas', 'updated_at'));
    }

    public function test_o_passe_roda_uma_unica_vez_e_deixa_a_marca_no_banco(): void
    {
        // A migration já rodou no RefreshDatabase, com o banco vazio, e marcou o que fez.
        $marca = (string) Setting::get('fuso_correcao_do_registro_em', '');
        $this->assertNotSame('', $marca);

        $conversa = $this->conversa('2026-10-27 15:00:00');
        $fala = $this->fala($conversa, '2026-10-27 15:00:43', 'cliente');

        // Tira o carimbo da migration de propósito: sem a marca no banco, um `migrate` desavisado
        // jogaria o histórico três horas para trás pela segunda vez.
        DB::table('migrations')
            ->where('migration', '2026_10_28_000001_atrasam_o_historico_para_a_hora_do_cliente')
            ->delete();

        Artisan::call('migrate');

        $this->assertSame($marca, Setting::get('fuso_correcao_do_registro_em'));
        $this->assertSame('2026-10-27 15:00:43', $this->valor($fala));
        $this->assertSame('2026-10-27 15:00:00', $this->valor($conversa, 'conversas'));
    }

    public function test_o_desfazer_devolve_as_tres_horas(): void
    {
        $conversa = $this->conversa('2026-10-27 15:00:00');
        $fala = $this->fala($conversa, '2026-10-27 15:00:43', 'cliente');

        FusoHistorico::corrigir(virada: self::VIRADA);
        $this->assertSame('2026-10-27 12:00:43', $this->valor($fala));

        FusoHistorico::corrigir(virada: self::VIRADA, horas: -FusoHistorico::HORAS);
        $this->assertSame('2026-10-27 15:00:43', $this->valor($fala));
        $this->assertSame('2026-10-27 15:00:00', $this->valor($conversa, 'conversas'));
    }

    public function test_a_faixa_duvidosa_tem_tres_horas_e_a_virada_e_a_hora_de_brasilia(): void
    {
        $this->assertSame(self::FIM_DA_FAIXA, date('Y-m-d H:i:s', (int) strtotime(self::VIRADA) + FusoHistorico::HORAS * 3600));
        $this->assertSame('America/Sao_Paulo', Fuso::vigente());
        $this->assertSame(self::VIRADA, FusoHistorico::corrigir(simular: true)['virada']);
    }
}
