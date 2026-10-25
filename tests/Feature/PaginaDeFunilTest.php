<?php

namespace Tests\Feature;

use App\Models\FunnelBlock;
use App\Models\FunnelPage;
use App\Models\Lead;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A página de campanha: funil de divulgação que só existe para quem recebe o link.
 *
 * Este teste cobre os três contratos da funcionalidade — a página pública (endereço,
 * cabeçalho de SEO e invisibilidade para a busca), o cadastro (a página e o clique do
 * anúncio que o gerou) e o painel (montar por blocos e ler o contador).
 */
class PaginaDeFunilTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private FunnelPage $campanha;
    private string $cookie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        $this->campanha = FunnelPage::where('slug', 'frota-j16')->firstOrFail();
        $this->cookie = config('golden.rastreio.cookie');
        $this->withCredentials();
    }

    public function test_a_campanha_publica_abre_com_seo_completo(): void
    {
        $this->get('/c/frota-j16')
            ->assertOk()
            ->assertSee('Sua frota no mapa, em uma tela só', false)
            ->assertSee('Receba a proposta para a sua frota', false)
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('<link rel="canonical" href="'.$this->campanha->url().'">', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('name="funnel_page_id" value="'.$this->campanha->id.'"', false);
    }

    public function test_a_campanha_nao_aparece_em_nenhum_lugar_do_site(): void
    {
        $link = '/c/frota-j16';

        foreach (['/', '/planos', '/contato', '/p/sobre'] as $url) {
            $this->get($url)->assertOk()->assertDontSee($link, false);
        }
    }

    public function test_rascunho_e_pagina_pausada_nao_tem_endereco(): void
    {
        $rascunho = FunnelPage::create(['nome' => 'Campanha em montagem', 'slug' => 'em-montagem', 'status' => 'rascunho']);
        $this->get('/c/em-montagem')->assertNotFound();

        $rascunho->update(['status' => 'publicada']);
        $this->get('/c/em-montagem')->assertOk();

        $rascunho->update(['status' => 'pausada']);
        $this->get('/c/em-montagem')->assertNotFound();
    }

    /** Trocar o endereço não apaga o histórico: os dois caminhos seguem servindo. */
    public function test_o_endereco_antigo_continua_no_ar_dependendo_do_nome(): void
    {
        $this->campanha->update(['slug' => 'frota-j16-dez-26']);

        $this->get('/c/frota-j16-dez-26')->assertOk();
        $this->get('/c/frota-j16')->assertOk();
        $this->assertSame(['/c/frota-j16', '/c/frota-j16-dez-26'], $this->campanha->fresh()->caminhos());
    }

    public function test_o_cadastro_pela_campanha_guarda_a_pagina_e_o_clique_do_anuncio(): void
    {
        $this->get('/c/frota-j16?ttclid=ABC123&utm_source=tiktok&utm_medium=cpc')->assertOk();
        $id = Visit::firstOrFail()->visitor_id;

        $this->withCookies([$this->cookie => $id])->postJson('/leads', [
            'name' => 'Carlos Frota',
            'phone' => '35999990000',
            'kit' => 'Frota Profissional — 10 veículos',
            'fleet_size' => '10',
            'consent' => '1',
            'funnel_page_id' => $this->campanha->id,
        ])->assertStatus(201);

        $lead = $this->leadCapturado();

        $this->assertSame($this->campanha->id, $lead->funnel_page_id);
        $this->assertSame('ttclid', $lead->clique_id, 'O nome do parâmetro é a prova do clique.');
        $this->assertSame('ABC123', $lead->clique_valor);
        $this->assertSame('anuncio', $lead->canal);
        $this->assertSame('tiktok', $lead->rede);
        $this->assertTrue($lead->pago);
        $this->assertSame('/c/frota-j16', $lead->visit->landing_path);
    }

    /** Sem o par de clique na URL, o painel ainda precisa saber qual chamada trouxe o lead. */
    public function test_o_clique_da_url_alimenta_o_par_generico(): void
    {
        $this->get('/c/frota-j16?gclid=XYZ789')->assertOk();
        $id = Visit::firstOrFail()->visitor_id;

        // O formulário não repete o gclid: a prova do clique fica na visita registrada
        // e é dela que o cadastro herda — assim como herda a campanha pelo endereço.
        $this->withCookies([$this->cookie => $id])->postJson('/leads', [
            'name' => 'Ana da Busca',
            'phone' => '35999887766',
            'consent' => '1',
        ])->assertStatus(201);

        $lead = $this->leadCapturado();
        $this->assertSame('gclid', $lead->clique_id);
        $this->assertSame('XYZ789', $lead->clique_valor);
        $this->assertSame($this->campanha->id, $lead->funnel_page_id);
    }

    public function test_um_rascunho_do_painel_nunca_recebe_cadastro(): void
    {
        $rascunho = FunnelPage::create(['nome' => 'Modelo guardado', 'slug' => 'modelo-guardado', 'status' => 'rascunho']);

        $this->postJson('/leads', [
            'name' => 'Sem campanha',
            'phone' => '35988887777',
            'consent' => '1',
            'funnel_page_id' => $rascunho->id,
        ])->assertStatus(201);

        $this->assertNull($this->leadCapturado()->funnel_page_id);
    }

    public function test_o_contador_da_campanha_separa_gente_de_robo(): void
    {
        $this->get('/c/frota-j16')->assertOk();
        $this->get('/c/frota-j16')->assertOk();
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->get('/c/frota-j16')->assertOk();

        $this->assertSame(3, Visit::where('landing_path', '/c/frota-j16')->count());
        $this->assertSame(2, Visit::pessoas()->where('landing_path', '/c/frota-j16')->count());

        $this->actingAs($this->admin)
            ->get(route('admin.funil.show', $this->campanha))
            ->assertOk()
            ->assertSee('Visitas no período')
            ->assertSee('De onde veio o tráfego desta página')
            ->assertSee('Clique por clique');
    }

    public function test_a_listagem_do_painel_mostra_o_que_cada_campanha_produziu(): void
    {
        $this->get('/c/frota-j16')->assertOk();

        $this->actingAs($this->admin)->get(route('admin.funil.index'))->assertOk()
            ->assertSee($this->campanha->nome)
            ->assertSee('/c/frota-j16', false);
    }

    public function test_a_tecla_do_painel_coloca_e_tira_a_campanha_do_ar(): void
    {
        $esta = $this->campanha->fresh();
        $this->assertSame('publicada', $esta->status);

        $this->actingAs($this->admin)->post(route('admin.funil.toggle', $esta))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('pausada', $esta->fresh()->status);
        $this->get('/c/frota-j16')->assertNotFound();

        $this->actingAs($this->admin)->post(route('admin.funil.toggle', $esta))
            ->assertSessionHas('success');
        $this->assertSame('publicada', $esta->fresh()->status);
        $this->get('/c/frota-j16')->assertOk();

        $this->assertDatabaseHas('activities', ['action' => 'funil_pausada']);
        $this->assertDatabaseHas('activities', ['action' => 'funil_publicada']);
    }

    /** A tecla não abre um endereço vazio: sem bloco, não há o que publicar. */
    public function test_uma_campanha_sem_bloco_nao_sobe_pela_tecla(): void
    {
        $vazia = FunnelPage::create(['nome' => 'Campanha sem conteúdo', 'slug' => 'sem-conteudo', 'status' => 'rascunho']);

        $this->actingAs($this->admin)->post(route('admin.funil.toggle', $vazia))
            ->assertSessionHas('error');
        $this->assertSame('rascunho', $vazia->fresh()->status);
        $this->get('/c/sem-conteudo')->assertNotFound();

        $vazia->blocks()->create(['tipo' => 'hero', 'titulo' => 'Abertura', 'ordem' => 1, 'ativa' => true]);

        $this->actingAs($this->admin)->post(route('admin.funil.toggle', $vazia))
            ->assertSessionHas('success');
        $this->assertSame('publicada', $vazia->fresh()->status);
        $this->get('/c/sem-conteudo')->assertOk();
    }

    /** O controle está à mão nas duas telas da campanha: na listagem e no contador. */
    public function test_a_tecla_aparece_na_listagem_e_no_contador_da_campanha(): void
    {
        $url = route('admin.funil.toggle', $this->campanha);

        $this->actingAs($this->admin)->get(route('admin.funil.index'))->assertOk()
            ->assertSee('Marketing (funil)')
            ->assertSee('action="'.$url.'"', false);

        $esta = $this->get(route('admin.funil.show', $this->campanha))->assertOk()->getContent();
        $this->assertStringContainsString('action="'.$url.'"', $esta);
        $this->assertStringContainsString('Tirar do ar', $esta);
        $this->assertStringContainsString('data-confirmar=', $esta);
    }

    /** A foto do bloco de texto é o plano de fundo da faixa, não um quadro na coluna ao lado. */
    public function test_a_foto_do_bloco_de_texto_fica_no_plano_de_fundo(): void
    {
        $html = $this->get('/c/frota-j16')->assertOk()->getContent();

        $this->assertStringContainsString('<section class="f-conteudo com-foto">', $html);
        $this->assertStringContainsString('class="f-foto"', $html);
        $this->assertStringNotContainsString('f-conteudo-foto', $html);

        // O véu que segura a leitura mora no CSS, junto do do herói.
        $css = file_get_contents(public_path('css/golden-funil.css'));
        $this->assertStringContainsString('.f-conteudo .f-foto{position:absolute;inset:0', $css);
        $this->assertStringContainsString('.f-conteudo.com-foto::before', $css);
    }

    public function test_o_bloco_guarda_so_os_campos_que_o_tipo_declara(): void
    {
        $this->actingAs($this->admin)->post(route('admin.funil.blocos.store', $this->campanha), [
            'tipo' => 'video',
            'titulo' => 'Apresentação da plataforma',
            'texto' => 'Dois minutos de tour.',
            'cta_destino' => 'https://www.youtube.com/watch?v=OhtdphIxdf0',
            'itens' => 'texto que o tipo video nao usa',
            'imagem' => 'img/home/nao-usada.jpg',
            'ordem' => 20,
            'ativa' => '1',
        ])->assertRedirect(route('admin.funil.edit', $this->campanha));

        $bloco = FunnelBlock::latest('id')->firstOrFail();

        $this->assertSame('video', $bloco->tipo);
        $this->assertSame('Apresentação da plataforma', $bloco->titulo);
        $this->assertNull($bloco->itens, 'Campo fora do tipo não pode entrar escondido.');
        $this->assertNull($bloco->imagem);
    }

    public function test_trocar_o_tipo_do_bloco_esvazia_o_que_ele_nao_mostra(): void
    {
        $bloco = $this->campanha->blocks()->create([
            'tipo' => 'beneficios',
            'titulo' => 'Oito benefícios',
            'itens' => "fa-star|Um|Texto\nfa-star|Dois|Texto",
        ])->id;

        $this->actingAs($this->admin)->post(route('admin.funil.blocos.update', [$this->campanha, $bloco]), [
            'tipo' => 'formulario',
            'titulo' => 'Cadastre-se',
            'texto' => 'Sem compromisso.',
            'cta_texto' => 'Quero a proposta',
        ])->assertRedirect(route('admin.funil.edit', $this->campanha));

        $depois = FunnelBlock::find($bloco);
        $this->assertSame('formulario', $depois->tipo);
        $this->assertNull($depois->itens);
    }

    public function test_apagar_o_preco_da_pagina_zera_a_oferta_do_google(): void
    {
        $this->campanha->update(['preco_cents' => 129_000]);

        $this->actingAs($this->admin)->post(route('admin.funil.update', $this->campanha), $this->dadosDaPagina(['preco' => '']));

        $this->assertNull($this->campanha->fresh()->preco_cents);
        $this->assertStringNotContainsString('"@type":"Product"', $this->get('/c/frota-j16')->getContent());

        $this->actingAs($this->admin)->post(route('admin.funil.update', $this->campanha), $this->dadosDaPagina(['preco' => '1.290,00']));
        $this->assertSame(129_000, $this->campanha->fresh()->preco_cents);
        $this->assertStringContainsString('"@type":"Product"', $this->get('/c/frota-j16')->getContent());
    }

    public function test_a_pagina_da_campanha_e_restrita_ao_administrador(): void
    {
        $this->actingAs(User::where('email', 'vendedor@golden.com.br')->firstOrFail())
            ->get(route('admin.funil.index'))
            ->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function dadosDaPagina(array $mudancas = []): array
    {
        $pagina = $this->campanha->fresh();

        return array_merge([
            '_token' => csrf_token(),
            'nome' => $pagina->nome,
            'slug' => $pagina->slug,
            'status' => $pagina->status,
            'visibilidade' => $pagina->visibilidade,
            'pele' => $pagina->pele,
            'titulo' => $pagina->titulo,
            'meta_description' => $pagina->meta_description,
            'nota_legal' => $pagina->nota_legal,
        ], $mudancas);
    }

    private function leadCapturado(): Lead
    {
        return Lead::latest('id')->firstOrFail();
    }
}
