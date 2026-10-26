<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ChavesDeIntegracao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A tela de Configurações dividida em abas.
 *
 * O que estes testes fecham: uma aba por tipo de configuração, nenhuma chave repetida na tela,
 * e — o mais importante — salvar uma aba não apaga o que está em outra. Era o risco real da
 * divisão: um POST da aba de acessos não leva nome do site, WhatsApp nem o interruptor de
 * medição, e o controle não pode tratar campo ausente como campo vazio.
 */
class AbasDasConfiguracoesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    private function tela(): string
    {
        return $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk()->getContent();
    }

    public function test_a_tela_mostra_uma_aba_por_tipo_de_configuracao(): void
    {
        $html = $this->tela();

        $abas = [
            'contato' => 'Contato e endereço',
            'acessos' => 'Acessos do site',
            'loja' => 'Loja virtual',
            'whatsapp' => 'WhatsApp',
            'medicao' => 'Medição',
            'chaves' => 'Chaves de integração',
        ];

        foreach ($abas as $id => $rotulo) {
            $this->assertSame(1, substr_count($html, 'data-aba="'.$id.'"'), "A aba {$id} não tem um botão, ou tem mais de um.");
            $this->assertSame(1, substr_count($html, 'id="aba-'.$id.'"'), "O painel da aba {$id} não existe, ou está repetido.");
            $this->assertStringContainsString($rotulo, $html);
        }

        // Cada aba de contato, acessos e medição salva o seu formulário.
        foreach (['contato', 'acessos', 'medicao'] as $aba) {
            $this->assertStringContainsString('name="_aba" value="'.$aba.'"', $html, "O formulário da aba {$aba} não diz de onde veio.");
        }

        // O comportamento das abas vem de um arquivo só, carregado no layout do painel.
        $this->assertStringContainsString('js/admin-abas.js', $html);
    }

    public function test_cada_grupo_de_chaves_tem_a_sua_aba_interna(): void
    {
        $html = $this->tela();

        foreach (array_keys(ChavesDeIntegracao::grupos()) as $id) {
            $this->assertStringContainsString('id="aba-chaves-'.$id.'"', $html, "O grupo {$id} não tem painel próprio.");
            $this->assertStringContainsString('data-aba="'.$id.'"', $html, "O grupo {$id} não tem botão de aba.");
        }
    }

    public function test_cada_painel_e_filho_direto_do_tab_content_e_tem_o_proprio_formulario(): void
    {
        // O Bootstrap só esconde os painéis que são filhos diretos de .tab-content — com um
        // invólucro no meio, todas as abas aparecem empilhadas. E formulário não se aninha:
        // por isso cada painel tem o seu, em vez de um form envolvendo a tela inteira.
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$this->tela());
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $paineis = $xpath->query('//*[@data-aba-painel]');

        $this->assertGreaterThan(0, $paineis->length);

        foreach ($paineis as $painel) {
            $id = $painel->getAttribute('id');

            $this->assertStringContainsString(
                'tab-content',
                $painel->parentNode->getAttribute('class'),
                "O painel {$id} não é filho direto de .tab-content."
            );
            $this->assertSame(0, $xpath->query('ancestor::form', $painel)->length, "O painel {$id} está dentro de um formulário.");
            $this->assertGreaterThan(0, $xpath->query('.//form', $painel)->length, "O painel {$id} não tem formulário próprio.");
        }
    }

    public function test_salvar_uma_aba_nao_apaga_o_que_esta_nas_outras(): void
    {
        Setting::set('site_nome', 'Golden Rastreamento');
        Setting::set('contato_whatsapp', '5519997101483');
        Setting::set('tracking_enabled', '1');

        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            '_aba' => 'acessos',
            'acesso_servidor_1_url' => 'https://servidor-um.exemplo.com.br',
            'acesso_botao_rotulo' => 'Rastreamento',
        ])->assertRedirect()->assertSessionHas('success', 'Acessos do site salvos.');

        $this->assertSame('https://servidor-um.exemplo.com.br', Setting::get('acesso_servidor_1_url'));
        $this->assertSame('Rastreamento', Setting::get('acesso_botao_rotulo'));

        $this->assertSame('Golden Rastreamento', Setting::get('site_nome'), 'A aba de acessos apagou o nome do site.');
        $this->assertSame('5519997101483', Setting::get('contato_whatsapp'), 'A aba de acessos apagou o WhatsApp.');
        $this->assertSame('1', Setting::get('tracking_enabled'), 'A aba de acessos desligou a medição.');
    }

    public function test_a_aba_de_medicao_desliga_a_coleta_sem_tocar_no_contato(): void
    {
        Setting::set('site_nome', 'Golden Rastreamento');
        Setting::set('contato_whatsapp', '5519997101483');
        Setting::set('tracking_enabled', '1');
        Setting::set('ga_measurement_id', 'G-ANTIGO');

        // O interruptor desmarcado não viaja no POST: quem grava o zero é a aba, não o campo.
        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            '_aba' => 'medicao',
            'ga_measurement_id' => 'G-NOVO',
        ])->assertRedirect()->assertSessionHas('success', 'Medição e consentimento salvos.');

        $this->assertSame('0', Setting::get('tracking_enabled'));
        $this->assertSame('G-NOVO', Setting::get('ga_measurement_id'));
        $this->assertSame('5519997101483', Setting::get('contato_whatsapp'));
    }

    public function test_o_contato_continua_obrigatorio_na_propria_aba(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            '_aba' => 'contato',
            'contato_whatsapp' => '5519997101483',
        ])->assertSessionHasErrors('site_nome');

        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            '_aba' => 'contato',
            'site_nome' => 'Golden Rastreamento',
            'contato_whatsapp' => '(19) 99710-1483',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success', 'Contato e endereço salvos.');

        $this->assertSame('19997101483', Setting::get('contato_whatsapp'), 'O WhatsApp tem de ficar só com os dígitos.');
    }

    public function test_sem_aba_declarada_o_post_continua_valendo_como_contato(): void
    {
        // Formulário antigo, sem `_aba`, não pode ser tratado como post vazio.
        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'site_nome' => 'Golden Rastreamento',
            'contato_whatsapp' => '5519997101483',
            'acesso_servidor_1_url' => 'https://servidor-um.exemplo.com.br',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('https://servidor-um.exemplo.com.br', Setting::get('acesso_servidor_1_url'));
    }
}
