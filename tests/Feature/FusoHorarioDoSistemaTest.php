<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Fuso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * O fuso horário do sistema, e o relógio que o visitante vê na bolha da Ana.
 *
 * O sintoma que abriu este arquivo: na tela do chat a hora de cada fala saía adiantada, porque a
 * hospedagem acorda em UTC e o `now()` do framework obedece ao fuso da aplicação, não ao do
 * cliente. O que estes testes fecham:
 *
 * 1. **A casa é Brasília.** Sem escolha no painel e sem `.env`, o fuso é `America/Sao_Paulo` — o
 *    servidor não decide a hora que o cliente lê.
 * 2. **O relógio da fala é o do cliente.** O instante gravado é um só; o que sai na bolha é a hora
 *    dele no fuso da casa.
 * 3. **O painel manda, o `.env` é plano de fundo** — como em toda credencial da casa. E um fuso
 *    inventado não passa nem pela tela nem pelo banco.
 */
class FusoHorarioDoSistemaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** O fuso do processo antes do teste: `date_default_timezone_set` é global e vaza para a frente. */
    private string $fusoOriginal = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $this->fusoOriginal = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->fusoOriginal);
        config(['app.timezone' => $this->fusoOriginal]);

        parent::tearDown();
    }

    public function test_o_fuso_da_casa_e_o_de_brasilia_e_nao_o_do_servidor(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'),
            'Sem nada gravado, o sistema tem que relógiar no fuso do cliente, não no da hospedagem.');
        $this->assertSame('America/Sao_Paulo', date_default_timezone_get(),
            'O PHP precisa estar no mesmo fuso que o config: é dele que `now()` e cada `format()` perguntam.');
    }

    public function test_a_hora_ao_lado_de_cada_fala_da_ana_e_a_do_fuso_da_casa(): void
    {
        // 21:00 em UTC são 18:00 em Brasília. O instante é um só; a hora que o cliente lê é a dele.
        $this->travelTo(Carbon::parse('2026-10-27 21:00:00', 'UTC'));

        $resposta = $this->postJson('/assistente/fala', ['texto' => 'Quanto custa o rastreador?'])
            ->assertOk();

        $this->assertSame('18:00', $resposta->json('falas.0.em'),
            'A bolha mostrou a hora do servidor, não a de quem escreveu.');
    }

    public function test_a_tela_de_configuracoes_oferece_o_fuso_com_lista_fechada(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.settings.index'))->assertOk()->getContent();

        $this->assertStringContainsString('name="fuso_horario"', $html);
        $this->assertStringContainsString('Fuso horário', $html);

        foreach (Fuso::opcoes() as $codigo => $rotulo) {
            $this->assertStringContainsString('<option value="'.$codigo.'"', $html, "O seletor não oferece {$codigo}.");
            $this->assertStringContainsString($rotulo, $html, "O seletor mostra {$codigo} sem rótulo em português.");
        }
    }

    public function test_o_fuso_escolhido_no_painel_manda_sobre_o_ambiente(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), [
                '_aba' => 'contato',
                'site_nome' => 'Golden Rastreamento',
                'contato_whatsapp' => '5519997101483',
                'fuso_horario' => 'America/Manaus',
            ])->assertSessionHasNoErrors();

        $this->assertSame('America/Manaus', Setting::get('fuso_horario'));

        Fuso::aplicar();

        $this->assertSame('America/Manaus', config('app.timezone'));
        $this->assertSame('America/Manaus', date_default_timezone_get());
        $this->assertSame('-04:00', Carbon::parse('2026-10-27 21:00:00', 'UTC')->setTimezone(config('app.timezone'))->format('P'));
    }

    public function test_fuso_inventado_nao_entra_pela_tela(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.settings.index'))
            ->post(route('admin.settings.update'), [
                '_aba' => 'contato',
                'site_nome' => 'Golden Rastreamento',
                'contato_whatsapp' => '5519997101483',
                'fuso_horario' => 'Marte/Olympus_Mons',
            ])
            ->assertSessionHasErrors('fuso_horario');

        $this->assertNull(Setting::get('fuso_horario'), 'Um fuso rejeitado não pode ficar gravado.');
    }

    public function test_valor_gravado_a_mao_fora_da_lista_nao_desregula_o_relogio(): void
    {
        Setting::set('fuso_horario', 'Fuso/Inventado');
        Setting::esquecerMemoria();

        Fuso::aplicar();

        $this->assertSame('America/Sao_Paulo', config('app.timezone'),
            'Código fora da lista fechada volta ao padrão da casa em vez de jogar o relógio para o fuso do servidor.');
        $this->assertSame('America/Sao_Paulo', date_default_timezone_get());
    }

    public function test_o_fuso_e_declarado_num_lugar_somente(): void
    {
        // Duas casas para a mesma regra é como o relógio volta a divergir: um canto do sistema
        // grava UTC e o outro lê Brasília.
        $quem_pode_mexer = ['app/Support/Fuso.php', 'config/app.php'];

        foreach ($this->arquivos() as $arquivo) {
            $caminho = str_replace('\\', '/', $arquivo->getPathname());
            $relativo = ltrim(str_replace('\\', '/', substr($caminho, strlen(base_path()))), '/');

            if (in_array($relativo, $quem_pode_mexer, true)) {
                continue;
            }

            $this->assertStringNotContainsString(
                'date_default_timezone_set',
                (string) file_get_contents($caminho),
                "{$relativo} mexe no fuso do PHP por conta própria."
            );
        }
    }

    /**
     * O PHP do sistema, sem o que o framework traz e sem o que o painel escreve: varrer `vendor`
     * inteiro custaria dez vezes o teste inteiro, e não é lá que alguém escreveria um fuso.
     *
     * @return iterable<\SplFileInfo>
     */
    private function arquivos(): iterable
    {
        foreach (['app', 'config', 'routes', 'resources', 'bootstrap'] as $pasta) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(base_path($pasta), FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $arquivo) {
                if ($arquivo->isFile() && str_ends_with($arquivo->getPathname(), '.php')) {
                    yield $arquivo;
                }
            }
        }
    }
}
