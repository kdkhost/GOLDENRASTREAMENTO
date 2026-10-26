<?php

namespace Tests\Feature;

use App\Models\Assistente;
use App\Models\ConhecimentoItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O treinamento de fábrica da assistente.
 *
 * A promessa que estes testes fecham: a base nasce da config com os números e endereços que o
 * painel controla na hora da semeadura; rodar de novo não apaga o que a equipe editou; e o
 * `--atualizar` / `--personalidade` existem para voltar ao texto de fábrica de propósito. O
 * catálogo é sempre derivado dos produtos ativos — preço de robô não pode envelhecer.
 */
class TreinamentoDoAssistenteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    public function test_o_comando_ensina_a_base_com_o_catalogo_e_a_personalidade(): void
    {
        $this->artisan('assistente:ensinar', ['--personalidade' => true])->assertSuccessful();

        $this->assertSame(
            count(config('conhecimento.itens')) + 1,
            ConhecimentoItem::where('origem', 'painel')->count(),
            'A base de fábrica não entrou inteira, ou o catálogo não foi somado.'
        );

        $produto = Product::where('ativo', true)->orderBy('ordem')->firstOrFail();
        $catalogo = ConhecimentoItem::where('referencia', 'golden:catalogo')->firstOrFail();

        $this->assertStringContainsString($produto->nome, $catalogo->corpo);

        $ficha = Assistente::garanta();
        $this->assertSame('descolado', $ficha->tom);
        $this->assertSame(config('assistente.persona.cargo'), $ficha->cargo);
    }

    public function test_rodar_de_novo_nao_apaga_o_que_a_equipe_editou(): void
    {
        $this->artisan('assistente:ensinar')->assertSuccessful();

        $item = ConhecimentoItem::where('referencia', 'golden:rastreador-j16')->firstOrFail();
        $item->update(['corpo' => 'Texto que a equipe escreveu por cima.']);

        $this->artisan('assistente:ensinar')->assertSuccessful();

        $this->assertSame(
            'Texto que a equipe escreveu por cima.',
            $item->fresh()->corpo,
            'A segunda rodada sobrescreveu um texto editado no painel.'
        );

        $this->artisan('assistente:ensinar', ['--atualizar' => true])->assertSuccessful();

        $this->assertNotSame('Texto que a equipe escreveu por cima.', $item->fresh()->corpo);
        $this->assertStringContainsString('J16', $item->fresh()->corpo);
    }

    public function test_os_textos_saem_com_o_numero_e_o_email_do_painel(): void
    {
        Setting::set('contato_whatsapp', '5531999990000');
        Setting::set('contato_email', 'atendimento@exemplo.com.br');

        $this->artisan('assistente:ensinar')->assertSuccessful();

        $quemSomos = ConhecimentoItem::where('referencia', 'golden:quem-somos')->firstOrFail();

        $this->assertStringContainsString('https://wa.me/5531999990000', $quemSomos->corpo);
        $this->assertStringContainsString('atendimento@exemplo.com.br', $quemSomos->corpo);
        $this->assertStringNotContainsString(':whatsapp', $quemSomos->corpo);
        $this->assertStringNotContainsString(':email', $quemSomos->corpo);
    }

    public function test_a_personalidade_so_troca_o_que_a_config_manda(): void
    {
        $ficha = Assistente::garanta();
        $ficha->update(['nome' => 'Bia', 'tom' => 'formal']);

        $this->artisan('assistente:ensinar')->assertSuccessful();
        $this->assertSame('formal', $ficha->fresh()->tom, 'Sem --personalidade a ficha não deveria mudar.');

        $this->artisan('assistente:ensinar', ['--personalidade' => true])->assertSuccessful();

        $this->assertSame('descolado', $ficha->fresh()->tom);
        $this->assertSame('Bia', $ficha->fresh()->nome, 'O nome escolhido pela equipe foi sobrescrito.');
    }

    public function test_o_botao_ensinar_o_basico_treina_pelo_painel(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.assistente.conhecimento.semear'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(ConhecimentoItem::where('referencia', 'golden:portal-do-cliente')->exists());
        $this->assertTrue(ConhecimentoItem::where('referencia', 'golden:catalogo')->exists());
    }
}
