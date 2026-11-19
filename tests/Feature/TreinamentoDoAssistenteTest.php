<?php

namespace Tests\Feature;

use App\Models\Assistente;
use App\Models\ConhecimentoItem;
use App\Models\Conversa;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Assistente\Busca;
use App\Services\Assistente\Identificacao;
use App\Services\Assistente\Motor;
use App\Services\Assistente\SegundaViaDaFatura;
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
        $this->assertSame('humanizado', $ficha->tom);
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

        $this->assertSame('humanizado', $ficha->fresh()->tom);
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

    public function test_admin_configura_treinamento_restricoes_e_permissoes_de_acesso_da_ana(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.assistente.persona.update'), [
                'nome' => 'Ana',
                'cargo' => 'Consultora Golden',
                'tom' => 'humanizado',
                'regras' => 'Seja sempre gentil e objetiva.',
                'objetivo_atendimento' => 'Ajudar clientes com rastreadores e planos Golden.',
                'restricoes' => 'Nunca prometer desconto sem autorização.',
                'instrucoes_extras' => 'Destacar o Chip Multioperadora incluso no plano.',
                'acessos_enviado' => '1',
                'acessos_sistema' => [
                    'consultar_catalogo' => '1',
                    'consultar_faturas' => '0',
                    'consultar_cadastro' => '1',
                    'abrir_chamado_equipe' => '1',
                    'responder_apenas_com_fonte' => '1',
                    'pedir_nome' => '1',
                    'pedir_avaliacao' => '0',
                ],
                'ativo' => '1',
                'no_site' => '1',
            ])
            ->assertRedirect(route('admin.assistente.persona'));

        $ficha = Assistente::garanta()->fresh();
        $this->assertSame('Ajudar clientes com rastreadores e planos Golden.', $ficha->objetivo_atendimento);
        $this->assertSame('Nunca prometer desconto sem autorização.', $ficha->restricoes);
        $this->assertSame('Destacar o Chip Multioperadora incluso no plano.', $ficha->instrucoes_extras);
        $this->assertTrue($ficha->podeAcessar('consultar_catalogo'));
        $this->assertFalse($ficha->podeAcessar('consultar_faturas'));
        $this->assertFalse($ficha->podeAcessar('pedir_avaliacao'));

        $prompt = $ficha->instrucoes();
        $this->assertStringContainsString('Ajudar clientes com rastreadores e planos Golden.', $prompt);
        $this->assertStringContainsString('Nunca prometer desconto sem autorização.', $prompt);
        $this->assertStringContainsString('Destacar o Chip Multioperadora incluso no plano.', $prompt);
        $this->assertStringContainsString('Não prometa emissão automática de 2ª via de fatura', $prompt);

        $this->actingAs($this->admin)
            ->post(route('admin.assistente.treinar'))
            ->assertRedirect(route('admin.assistente.persona'));
    }

    public function test_permissoes_de_acesso_da_ana_controlam_catalogo_cadastro_e_regras_no_motor(): void
    {
        $this->artisan('assistente:ensinar')->assertSuccessful();

        $cliente = Customer::create([
            'nome' => 'Carlos Teste',
            'email' => 'carlos.teste@golden.com.br',
            'telefone' => '19999999999',
        ]);

        // Por padrão, tudo liberado: encontra catálogo, exige fonte e consulta cadastro
        $fontesComCatalogo = Busca::fontes('quanto custa o plano frota rastreador catálogo');
        $this->assertNotEmpty($fontesComCatalogo);
        $this->assertTrue(Motor::exigeFonte());
        $this->assertTrue((new SegundaViaDaFatura)->ligada());

        // Desliga consultar_catalogo, consultar_cadastro, consultar_faturas e responder_apenas_com_fonte
        $this->actingAs($this->admin)
            ->post(route('admin.assistente.persona.update'), [
                'nome' => 'Ana',
                'tom' => 'humanizado',
                'acessos_enviado' => '1',
                'acessos_sistema' => [
                    'consultar_catalogo' => '0',
                    'consultar_faturas' => '0',
                    'consultar_cadastro' => '0',
                    'abrir_chamado_equipe' => '1',
                    'responder_apenas_com_fonte' => '0',
                    'pedir_nome' => '1',
                    'pedir_avaliacao' => '1',
                ],
                'ativo' => '1',
                'no_site' => '1',
            ])
            ->assertRedirect(route('admin.assistente.persona'));

        $this->assertFalse(Motor::exigeFonte());
        $this->assertFalse((new SegundaViaDaFatura)->ligada());

        // Busca não deve mais retornar o item golden:catalogo
        $catalogoId = ConhecimentoItem::where('referencia', 'golden:catalogo')->value('id');
        $idsEncontrados = array_column(Busca::fontes('catálogo produtos rastreador preço', 50), 'id');
        $this->assertNotContains($catalogoId, $idsEncontrados);

        // Identificação grava e-mail na conversa mas não consulta nem vincula o cliente do CRM
        $conversa = Conversa::abrir('site');
        $achado = Identificacao::vincular($conversa, $cliente->email);
        $this->assertNull($achado);
        $this->assertNull($conversa->fresh()->customer_id);
        $this->assertSame($cliente->email, $conversa->fresh()->email);
    }
}
