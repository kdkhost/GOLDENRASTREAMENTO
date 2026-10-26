<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A caixa multicanal do WhatsApp, item por item.
 *
 * O que estes testes fecham: nenhum texto da caixa mora no HTML. Título, subtítulo, selo,
 * dica do botão, rodapé, mensagem de abertura e — canal por canal — nome, descrição, ícone,
 * mensagem pronta e número saem do banco quando o painel os escreve, e voltam ao padrão da
 * casa quando o campo fica em branco. O assistente virtual não entra nesta lista porque a
 * ficha dele é a de Atendimento → Assistente virtual.
 */
class CaixaDoWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    /** Cada item que aparece na caixa tem o seu campo na tela de Configurações. */
    public function test_o_painel_oferece_um_campo_para_cada_item_da_caixa(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk()->getContent();

        foreach ([
            'whatsapp_box_titulo', 'whatsapp_box_subtitulo', 'whatsapp_box_online',
            'whatsapp_box_flutuante', 'whatsapp_box_rodape', 'whatsapp_box_abertura',
        ] as $campo) {
            $this->assertStringContainsString('name="'.$campo.'"', $html, 'Falta o campo '.$campo.'.');
        }

        foreach (config('golden.whatsapp_box.canais') as $canal) {
            foreach (['nome', 'desc', 'icone', 'numero', 'msg', 'ativo'] as $campo) {
                $nomeDoCampo = 'whatsapp_canal_'.$canal['id'].'_'.$campo;
                $this->assertStringContainsString('name="'.$nomeDoCampo.'"', $html, 'Falta o campo '.$nomeDoCampo.'.');
            }
        }
    }

    /** O que o painel grava é exatamente o que o visitante lê. */
    public function test_os_textos_gravados_no_painel_aparecem_na_caixa(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.whatsapp'), $this->formulario([
                'whatsapp_box_titulo' => 'Central de testes',
                'whatsapp_box_subtitulo' => 'Escolha com quem falar',
                'whatsapp_box_online' => 'Aberto agora',
                'whatsapp_box_flutuante' => 'Fale com a Golden',
                'whatsapp_box_rodape' => 'Outro caminho com a Golden →',
                'whatsapp_box_abertura' => 'Vim pelo site de testes.',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Central de testes', $html);
        $this->assertStringContainsString('Escolha com quem falar', $html);
        $this->assertStringContainsString('Aberto agora', $html);
        $this->assertStringContainsString('Fale com a Golden', $html);
        $this->assertStringContainsString('Outro caminho com a Golden →', $html);
        $this->assertStringContainsString(urlencode('Vim pelo site de testes.'), $html);

        $this->assertStringNotContainsString('Central de Atendimento Golden', $html);
        $this->assertStringNotContainsString('Outro assunto com a Golden', $html);
    }

    /** Nome, descrição, ícone, número e mensagem de cada canal também são de painel. */
    public function test_cada_canal_editado_chega_ao_site_com_o_numero_e_a_mensagem_dele(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.whatsapp'), $this->formulario([
                'whatsapp_canal_emergencia_nome' => 'Emergência VIP',
                'whatsapp_canal_emergencia_desc' => 'Plantão 24 horas, todos os dias',
                'whatsapp_canal_emergencia_icone' => 'fa-screwdriver-wrench',
                'whatsapp_canal_emergencia_numero' => '(35) 99999-0000',
                'whatsapp_canal_emergencia_msg' => 'Preciso do plantão agora.',
            ]))
            ->assertRedirect();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Emergência VIP', $html);
        $this->assertStringContainsString('Plantão 24 horas, todos os dias', $html);
        $this->assertStringContainsString('fa-screwdriver-wrench', $html);
        $this->assertStringContainsString('https://wa.me/35999990000?text='.urlencode('Preciso do plantão agora.'), $html);

        $this->assertStringNotContainsString('Emergência &amp; Central 24h', $html);
        $this->assertStringNotContainsString('Roubo, furto, sinistro ou bloqueio imediato', $html);
    }

    /** Campo em branco devolve aquele item ao padrão da casa, sem apagar o resto. */
    public function test_campo_em_branco_volta_ao_padrao_da_casa(): void
    {
        $canal = config('golden.whatsapp_box.canais')[1];

        $this->actingAs($this->admin)
            ->post(route('admin.settings.whatsapp'), $this->formulario([
                'whatsapp_box_titulo' => 'Título que fica',
                'whatsapp_canal_'.$canal['id'].'_nome' => '',
                'whatsapp_canal_'.$canal['id'].'_desc' => '',
                'whatsapp_canal_'.$canal['id'].'_icone' => '',
                'whatsapp_canal_'.$canal['id'].'_msg' => '',
            ]))
            ->assertRedirect();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Título que fica', $html);
        $this->assertStringContainsString(e($canal['nome']), $html);
        $this->assertStringContainsString(e($canal['desc']), $html);
        $this->assertStringContainsString('fa-tags', $html);
        $this->assertStringContainsString(urlencode($canal['msg']), $html);
    }

    /** Ícone fora da lista fechada é recusado pelo validador, não desenhado quebrado. */
    public function test_icone_fora_da_lista_e_recusado(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.whatsapp'), $this->formulario([
                'whatsapp_canal_comercial_icone' => 'fa-inventado',
            ]))
            ->assertSessionHasErrors('whatsapp_canal_comercial_icone');

        $this->assertSame('', (string) Setting::get('whatsapp_canal_comercial_icone', ''));
    }

    /** O formulário completo, como o navegador manda: interruptores ligados e todos os campos. */
    private function formulario(array $trocar = []): array
    {
        $dados = [
            'whatsapp_box_ativo' => '1',
            'whatsapp_box_titulo' => '',
            'whatsapp_box_subtitulo' => '',
            'whatsapp_box_online' => '',
            'whatsapp_box_flutuante' => '',
            'whatsapp_box_rodape' => '',
            'whatsapp_box_abertura' => '',
        ];

        foreach (config('golden.whatsapp_box.canais') as $canal) {
            $chave = 'whatsapp_canal_'.$canal['id'];

            $dados[$chave.'_ativo'] = '1';
            $dados[$chave.'_nome'] = '';
            $dados[$chave.'_desc'] = '';
            $dados[$chave.'_icone'] = '';
            $dados[$chave.'_numero'] = '';
            $dados[$chave.'_msg'] = '';
        }

        return array_merge($dados, $trocar);
    }
}
