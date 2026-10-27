<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Models\Visit;
use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * O padrão de CSV da plataforma: o arquivo que sai do painel tem de abrir no Excel em
 * português com uma coluna por campo, na mesma ordem toda vez. As regras moram todas em
 * App\Support\Csv — estes testes conferem a regra escrita e as duas telas que a usam,
 * porque antes cada exportação escrevia o próprio fputcsv, com vírgula e na ordem do
 * programador, e o arquivo chegava ilegível.
 */
class ArquivosCsvTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    public function test_as_colunas_sao_ordenadas_pelo_rotulo_sem_o_acento_pregar_lugar(): void
    {
        $linhas = $this->linhas($this->escrever([
            'WhatsApp' => fn () => '11',
            'E-mail' => fn () => 'a@b.co',
            'Última atividade' => fn () => 'ontem',
            'ID' => fn () => 3,
            'Análise' => fn () => 'ok',
            'Abertura' => fn () => 'hoje',
        ]));

        $this->assertSame(
            ['Abertura', 'Análise', 'E-mail', 'ID', 'Última atividade', 'WhatsApp'],
            $linhas[0],
            'O acento empurrou a coluna para o fim do alfabeto.'
        );
    }

    public function test_o_arquivo_vem_com_bom_ponto_e_virgula_e_aspas_em_toda_celula(): void
    {
        $csv = $this->escrever([
            'Nome' => fn () => 'Golden, Rastreamento',
            'Cidade' => fn () => 'São Paulo',
        ]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Sem BOM, o Excel lê acento como lixo.');
        $this->assertStringContainsString("\"Cidade\";\"Nome\"\r\n", $csv);
        $this->assertStringContainsString("\"São Paulo\";\"Golden, Rastreamento\"\r\n", $csv);
    }

    public function test_texto_com_quebra_de_linha_vira_uma_linha_so(): void
    {
        $linhas = $this->linhas($this->escrever([
            'Mensagem' => fn () => "preciso de 3 rastreadores\n\tcom instalação ",
        ]));

        $this->assertCount(2, $linhas, 'A célula multilinha dividiu o registro em duas linhas.');
        $this->assertSame('preciso de 3 rastreadores com instalação', $linhas[1][0]);
    }

    public function test_o_que_parece_formula_entra_como_texto(): void
    {
        $linhas = $this->linhas($this->escrever([
            'A' => fn () => '=1+1',
            'B' => fn () => '+55 11 99999-0000',
            'C' => fn () => '@usuario',
            'D' => fn () => -1,
            'E' => fn () => '12.345,67',
        ]));

        $this->assertSame(["'=1+1", "'+55 11 99999-0000", "'@usuario", '-1', '12.345,67'], $linhas[1]);
    }

    public function test_numero_moeda_e_data_saem_no_formato_brasileiro(): void
    {
        $this->assertSame('1.234.567,89', Csv::moeda(123456789));
        $this->assertSame('', Csv::moeda(null));
        $this->assertSame('"0"', Csv::celula(0));
        $this->assertSame('"12,50"', Csv::celula(12.5));
        $this->assertSame('"não"', Csv::celula(false));
        $this->assertSame('"05/08/2026 14:30"', Csv::celula(Csv::data('2026-08-05 14:30:00')));
        $this->assertSame('""', Csv::celula(Csv::data(null)));
    }

    public function test_o_csv_de_leads_vem_com_as_colunas_da_ficha_em_ordem(): void
    {
        $resposta = $this->actingAs($this->admin)->get(route('admin.leads.export'))->assertOk();

        $this->assertStringContainsString('golden-leads-', (string) $resposta->headers->get('Content-Disposition'));
        $this->assertSame('text/csv; charset=UTF-8', $resposta->headers->get('Content-Type'));

        $cabecalho = $this->linhas($resposta->streamedContent())[0];

        $this->assertSame([
            'Campanha', 'Criado em', 'E-mail', 'Frota', 'ID', 'Kit', 'Nome', 'Origem',
            'Perfil', 'Responsável', 'Status', 'UTM campanha', 'Valor (R$)', 'WhatsApp',
        ], $cabecalho);
        $this->confere_ordem_alfabetica($cabecalho);

        $registros = $this->registros($resposta->streamedContent());

        $this->assertCount(Lead::count(), $registros, 'Um registro do painel, uma linha no arquivo.');

        $lead = Lead::orderBy('id')->firstOrFail();
        $linha = collect($registros)->firstWhere('ID', (string) $lead->id);

        $this->assertNotNull($linha, 'O lead cadastrado não apareceu no arquivo.');
        $this->assertSame($lead->name, $linha['Nome']);
        $this->assertSame(Lead::label($lead->status), $linha['Status']);
        $this->assertSame(Csv::data($lead->created_at), $linha['Criado em']);
    }

    public function test_o_csv_de_atribuicao_vem_com_cabecalho_em_portugues(): void
    {
        $this->actingAs($this->admin)->get('/?utm_source=instagram&utm_medium=social&utm_campaign=reels-kit')->assertOk();

        $corpo = $this->actingAs($this->admin)->get(route('admin.atribuicao.export'))->assertOk()->streamedContent();
        $registros = $this->registros($corpo);
        $cabecalho = $this->linhas($corpo)[0];

        $this->assertSame([
            'Abertura', 'Canal', 'Clique', 'Cliques', 'Destino', 'Dispositivo', 'Leads', 'Motivo',
            'Navegador', 'Origem (caminho)', 'Origem (host)', 'Pago', 'Rede', 'Última atividade',
            'UTM campanha', 'UTM fonte', 'UTM meio', 'Visualizações',
        ], $cabecalho);
        $this->confere_ordem_alfabetica($cabecalho);

        $this->assertCount(Visit::count(), $registros);

        $primeiro = $registros[0];
        $this->assertSame('Rede social', $primeiro['Canal']);
        $this->assertSame('Instagram', $primeiro['Rede']);
        $this->assertSame('não', $primeiro['Pago']);
        $this->assertSame('reels-kit', $primeiro['UTM campanha']);
    }

    /** A promessa do pedido: coluna em ordem alfabética, sem o acento pregar lugar. */
    private function confere_ordem_alfabetica(array $cabecalho): void
    {
        $chaves = array_map(fn (string $rotulo): string => Str::of($rotulo)->ascii()->lower()->toString(), $cabecalho);

        $ordenadas = $chaves;
        sort($ordenadas);

        $this->assertSame($ordenadas, $chaves, 'As colunas não estão em ordem alfabética.');
    }

    /** Escreve um CSV de teste com um registro fictício (a função de coluna recebe null). */
    private function escrever(array $colunas): string
    {
        $resposta = Csv::baixar('teste', $colunas, [null]);

        ob_start();
        $resposta->sendContent();

        return (string) ob_get_clean();
    }

    /** O corpo do arquivo virado em registros: rótulo da coluna => célula já limpa. */
    private function registros(string $csv): array
    {
        $linhas = $this->linhas($csv);
        $cabecalho = array_shift($linhas);

        return array_map(fn (array $linha): array => array_combine($cabecalho, $linha), $linhas);
    }

    /** O corpo do arquivo fatiado em linhas e células, sem o BOM. */
    private function linhas(string $csv): array
    {
        $corpo = preg_split('/\r\n/', trim(substr($csv, 3), "\r\n")) ?: [];

        return array_map(
            static fn (string $linha): array => array_map(
                static fn (string $celula): string => str_replace('""', '"', trim($celula, '"')),
                explode(';', $linha)
            ),
            $corpo
        );
    }
}
