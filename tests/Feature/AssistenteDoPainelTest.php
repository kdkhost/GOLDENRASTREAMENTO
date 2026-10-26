<?php

namespace Tests\Feature;

use App\Models\Assistente;
use App\Models\ConhecimentoItem;
use App\Models\Conversa;
use App\Models\ConversaMensagem;
use App\Models\User;
use App\Services\Assistente\Provedores\Fabrica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * As telas do assistente: a ficha, a base que ele lê e a fila de conversas.
 *
 * O desenho que estes testes fecham é o da casa: uma ficha só, editada (nunca cadastrada); nada
 * de cor nem de lista escrita na view — os tons vêm de `golden.tons_status` e as opções de tom de
 * fala e de voz vêm do próprio modelo e da fábrica; e o que o administrador escreve em `regras`
 * chega à máquina na ordem dada, sem reescrita e com as quebras de linha que ele digitou.
 *
 * O avatar obedece a régua dos envios: o painel aceita só caminho que saiu do campo certo e que
 * está no disco, e trocar o rosto apaga o arquivo antigo.
 */
class AssistenteDoPainelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $this->actingAs($this->admin);
    }

    public function test_a_ficha_nasce_dos_padroes_e_continua_sendo_uma_sozinha(): void
    {
        $this->assertSame(0, Assistente::count());

        $this->get(route('admin.assistente.persona'))->assertOk()->assertSee('Ana');
        $this->assertSame(1, Assistente::count());

        $this->get(route('admin.assistente.persona'))->assertOk();
        $this->assertSame(1, Assistente::count(), 'A ficha do assistente foi cadastrada duas vezes.');
    }

    public function test_o_painel_salva_a_ficha_e_elas_volta_na_tela(): void
    {
        $this->post(route('admin.assistente.persona.update'), [
            'nome' => 'Beatriz',
            'cargo' => 'Atendente virtual',
            'saudacao' => 'Oi! Sou a Beatriz.',
            'tom' => 'direto',
            'regras' => "Não invente preço.\nNão peça documento pelo chat.",
            'ativo' => '1',
            'no_site' => '1',
            'no_funil' => '0',
        ])->assertRedirect(route('admin.assistente.persona'))->assertSessionHasNoErrors();

        $ficha = Assistente::sole();
        $this->assertSame('Beatriz', $ficha->nome);
        $this->assertSame('direto', $ficha->tom);
        $this->assertTrue($ficha->ativo);
        $this->assertTrue($ficha->no_site);
        $this->assertFalse($ficha->no_funil, 'Desligar o funil não chegou na ficha.');
        $this->assertFalse($ficha->no_chamado);

        $this->get(route('admin.assistente.persona'))->assertSee('Beatriz')->assertSee('Não peça documento pelo chat.');
    }

    /** A ordem que o provedor recebe traz as regras na linha nova que o administrador digitou. */
    public function test_as_regras_entra_na_ordem_com_a_quebra_de_linha_que_alguem_digitou(): void
    {
        $ficha = Assistente::garanta();
        $ficha->update(['regras' => "Primeira regra.\nSegunda regra."]);

        $instrucoes = $ficha->instrucoes();

        $this->assertStringNotContainsString('\n', $instrucoes, 'A barra de \n chegou crua na ordem da IA.');
        $this->assertStringContainsString("Regras do atendimento:\nPrimeira regra.\nSegunda regra.", $instrucoes);
    }

    public function test_tom_e_voz_fora_da_lista_sao_recusados_e_nada_e_gravado(): void
    {
        $this->post(route('admin.assistente.persona.update'), [
            'nome' => 'Sem nome',
            'tom' => 'sarcastico',
            'voz' => 'openai:voz-inexistente',
        ])->assertSessionHasErrors(['tom', 'voz']);

        $this->assertSame(0, Assistente::count());
    }

    public function test_o_avatar_so_vale_se_veio_do_campo_certo_e_esta_no_disco(): void
    {
        Storage::disk('public')->put('assistente/rosto.png', 'x');
        Storage::disk('public')->put('produtos/estranho.png', 'x');

        $this->post(route('admin.assistente.persona.update'), [
            'nome' => 'Ana',
            'tom' => 'amigavel',
            'avatar' => 'produtos/estranho.png',
        ])->assertSessionHasErrors('avatar');

        $this->assertSame(0, Assistente::count(), 'Um POST rejeitado criou a ficha do assistente no banco.');

        $this->post(route('admin.assistente.persona.update'), [
            'nome' => 'Ana',
            'tom' => 'amigavel',
            'avatar' => 'assistente/rosto.png',
        ])->assertSessionHasNoErrors();

        $this->assertSame('assistente/rosto.png', Assistente::sole()->avatar);
    }

    /** Campo ausente no POST é "não mexi no rosto"; apagar é ato explícito, com o campo vazio. */
    public function test_salvar_a_ficha_sem_tocar_no_avatar_deixa_o_rosto_no_lugar(): void
    {
        Storage::disk('public')->put('assistente/rosto.png', 'x');
        Assistente::garanta()->update(['avatar' => 'assistente/rosto.png']);

        $this->post(route('admin.assistente.persona.update'), ['nome' => 'Ana', 'tom' => 'amigavel'])
            ->assertSessionHasNoErrors();

        $this->assertSame('assistente/rosto.png', Assistente::sole()->avatar);

        // Rosto novo no campo tira o velho do disco — o mesmo acordo do produto e do depoimento.
        Storage::disk('public')->put('assistente/novo.png', 'y');

        $this->post(route('admin.assistente.persona.update'), [
            'nome' => 'Ana',
            'tom' => 'amigavel',
            'avatar' => 'assistente/novo.png',
        ])->assertSessionHasNoErrors();

        $this->assertSame('assistente/novo.png', Assistente::sole()->avatar);
        $this->assertFalse(Storage::disk('public')->exists('assistente/rosto.png'));
    }

    public function test_a_ficha_diz_quais_capacidades_ainda_estao_sem_chave(): void
    {
        config(['assistente.chaves.gemini' => null, 'assistente.chaves.openai' => null]);

        $this->get(route('admin.assistente.persona'))
            ->assertOk()
            ->assertSee('sem provedor ligado')
            ->assertSee('Conversar com o cliente: falta a chave');
    }

    public function test_a_base_de_conhecimento_entra_sai_e_mostra_a_procedencia(): void
    {
        $this->post(route('admin.assistente.conhecimento.store'), [
            'titulo' => 'Cobertura em zona rural',
            'corpo' => 'O sinal depende da concessão da operadora na região.',
            'tags' => 'cobertura, rural',
            'origem' => 'painel',
            'ativo' => '1',
        ])->assertRedirect(route('admin.assistente.conhecimento.index'))->assertSessionHasNoErrors();

        $item = ConhecimentoItem::firstOrFail();
        $this->assertSame('Escrito pelo administrador', $item->origemLabel());
        $this->assertStringContainsString('gravado no painel', $item->procedencia());

        $this->get(route('admin.assistente.conhecimento.index'))
            ->assertOk()
            ->assertSee('Cobertura em zona rural')
            ->assertSee('gravado no painel');

        $item->update(['url' => 'https://goldenrastreamento.com.br/planos', 'lido_em' => now()]);
        $this->assertStringContainsString('lido em '.now()->format('d/m/Y'), $item->procedencia());

        $this->delete(route('admin.assistente.conhecimento.destroy', $item))->assertSessionHasNoErrors();
        $this->assertSame(0, ConhecimentoItem::count());
    }

    public function test_o_painel_nao_reaprende_a_mesma_pagina_duas_vezes(): void
    {
        $dados = [
            'titulo' => 'Planos',
            'corpo' => 'Três planos.',
            'origem' => 'site',
            'url' => 'https://goldenrastreamento.com.br/planos',
            'referencia' => 'https://goldenrastreamento.com.br/planos',
            'ativo' => '1',
        ];

        $this->post(route('admin.assistente.conhecimento.store'), $dados)->assertSessionHasNoErrors();
        $this->post(route('admin.assistente.conhecimento.store'), $dados)->assertSessionHasErrors('referencia');

        $this->assertSame(1, ConhecimentoItem::count());
    }

    public function test_o_painel_recusa_origem_que_nao_existe_e_texto_vazio(): void
    {
        $this->post(route('admin.assistente.conhecimento.store'), ['titulo' => 'X', 'origem' => 'radio'])
            ->assertSessionHasErrors(['corpo', 'origem']);

        $this->assertSame(0, ConhecimentoItem::count());
    }

    public function test_a_caixa_de_entrada_mostra_quem_espera_a_equipe_e_assumir_a_conversa_desliga_a_maquina(): void
    {
        $conversa = Conversa::abrir('funil', 'https://goldenrastreamento.com.br/c/planos');
        $conversa->escalar('sem_fonte');
        ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'cliente',
            'tipo' => 'texto',
            'corpo' => 'Qual o preço do rastreador?',
        ]);

        $this->get(route('admin.assistente.conversas.index'))
            ->assertOk()
            ->assertSee('Aguardando a equipe')
            ->assertSee('Qual o preço do rastreador?')
            ->assertSee('A máquina não tinha fonte');

        $this->post(route('admin.assistente.conversas.responder', $conversa), [
            'corpo' => 'Depende do plano; me diz o tipo de veículo?',
        ])->assertSessionHasNoErrors();

        $conversa->refresh();
        $this->assertNotNull($conversa->respondido_por_equipe_em);
        $this->assertSame('aberta', $conversa->status);

        $fala = ConversaMensagem::where('conversa_id', $conversa->id)->latest('id')->firstOrFail();
        $this->assertSame('equipe', $fala->autor);
        $this->assertSame($this->admin->id, $fala->user_id);
        $this->assertSame($this->admin->name, $fala->assinatura());

        $this->get(route('admin.assistente.conversas.show', $conversa))
            ->assertOk()
            ->assertSee('Depende do plano')
            ->assertSee($this->admin->name);
    }

    public function test_a_conversa_mostra_a_fonte_que_a_maquina_usou(): void
    {
        $conversa = Conversa::abrir('site');
        ConversaMensagem::create([
            'conversa_id' => $conversa->id,
            'autor' => 'assistente',
            'tipo' => 'texto',
            'corpo' => 'Temos três planos.',
            'provedor' => 'gemini',
            'modelo' => 'gemini-2.5-flash',
            'fontes_json' => [['titulo' => 'Planos', 'procedencia' => 'Página do site — goldenrastreamento.com.br/planos']],
        ]);

        $this->get(route('admin.assistente.conversas.show', $conversa))
            ->assertOk()
            ->assertSee('Fontes da resposta')
            ->assertSee('Página do site — goldenrastreamento.com.br/planos')
            ->assertSee('Google Gemini', false);
    }

    public function test_mudar_a_situacao_da_conversa_pelo_painel(): void
    {
        $conversa = Conversa::abrir('site');

        $this->post(route('admin.assistente.conversas.status', $conversa), ['status' => 'resolvida'])
            ->assertSessionHasNoErrors();

        $this->assertSame('resolvida', $conversa->refresh()->status);
        $this->assertNotNull($conversa->resolvido_em);

        $this->post(route('admin.assistente.conversas.status', $conversa), ['status' => 'radio'])
            ->assertSessionHasErrors('status');
    }

    public function test_a_tela_oferece_so_as_vozes_que_a_fabrica_sabe_falar(): void
    {
        $primeira = (string) array_key_first(Fabrica::opcoesDeVoz());

        $this->post(route('admin.assistente.persona.update'), [
            'nome' => 'Ana',
            'tom' => 'amigavel',
            'voz' => $primeira,
        ])->assertSessionHasNoErrors();

        $this->assertSame($primeira, Assistente::sole()->voz);

        // A lista fechada da tela é a mesma da fábrica: nada de voz escrita na view.
        $html = $this->get(route('admin.assistente.persona'))->assertOk()->getContent();
        foreach (Fabrica::opcoesDeVoz() as $rotulo) {
            $this->assertStringContainsString($rotulo, $html);
        }
    }

    /** O selo da conversa veste o tom do config — a view não escolhe cor. */
    public function test_o_selo_da_conversa_veio_do_config_de_tons(): void
    {
        Conversa::abrir('site')->escalar('pediu_humano');

        $tom = tom_por_status('conversa', 'aguardando_equipe');
        $this->assertNotSame('neutral', $tom, 'O grupo de tons das conversas voltou ao fallback.');

        $html = $this->get(route('admin.assistente.conversas.index'))->assertOk()->getContent();
        $this->assertStringContainsString('badge-status tone-'.$tom, $html);
    }
}
