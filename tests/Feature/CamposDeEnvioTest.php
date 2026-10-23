<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Os campos de arrastar-e-soltar do painel: o endpoint único de envio (regras de
 * config/envios.php), o preview que o campo descreve no HTML, a gravação dos caminhos
 * em produto/pedido e a barreira que não deixa ninguém apontar o campo para outra pasta.
 */
class CamposDeEnvioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Setting::esquecerMemoria();
        Cache::flush();
        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    private function enviar(string $campo, UploadedFile $arquivo)
    {
        return $this->actingAs($this->admin())->postJson('/admin/uploads', [
            'campo' => $campo,
            'arquivo' => $arquivo,
        ]);
    }

    public function test_visitante_nao_consegue_enviar_arquivo(): void
    {
        $this->post('/admin/uploads', ['campo' => 'produto_imagem'])
            ->assertRedirect(route('login'));
    }

    public function test_imagem_valida_e_grava_na_pasta_do_campo_e_descrita_na_resposta(): void
    {
        $resposta = $this->enviar('produto_imagem', UploadedFile::fake()->image('rastreador.jpg', 1200, 1200));

        $resposta->assertOk()->assertJsonPath('ok', true);

        $arquivo = $resposta->json('arquivo');
        $this->assertStringStartsWith('produtos/', $arquivo['caminho']);
        $this->assertSame('imagem', $arquivo['tipo']);
        $this->assertSame(1200, $arquivo['largura']);
        $this->assertSame(1200, $arquivo['altura']);
        $this->assertGreaterThan(0, $arquivo['bytes']);
        $this->assertMatchesRegularExpression('/^[\d,.]+ (B|KB|MB)$/', $arquivo['bytes_texto']);
        Storage::disk('public')->assertExists($arquivo['caminho']);
    }

    public function test_formato_fora_da_regra_do_campo_e_recusado(): void
    {
        $resposta = $this->enviar('produto_imagem', UploadedFile::fake()->create('planilha.csv', 20, 'text/csv'));

        $resposta->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertStringContainsString('JPG, JPEG, PNG, WEBP', $resposta->json('erro'));
    }

    public function test_arquivo_maior_que_o_limite_do_campo_e_recusado(): void
    {
        $resposta = $this->enviar('produto_imagem', UploadedFile::fake()->image('grande.jpg', 2000, 2000)->size(6144));

        $resposta->assertStatus(422);
        $this->assertStringContainsString('4 MB', $resposta->json('erro'));
    }

    public function test_documento_aceito_no_campo_de_documentos_e_recusado_no_de_imagem(): void
    {
        $pdf = fn () => UploadedFile::fake()->create('comprovante.pdf', 120, 'application/pdf');

        $this->enviar('pedido_documento', $pdf())->assertOk()
            ->assertJsonPath('arquivo.tipo', 'documento');

        $this->enviar('produto_imagem', $pdf())->assertStatus(422);
    }

    public function test_campo_de_envio_inexistente_e_recusado(): void
    {
        $this->enviar('avatares', UploadedFile::fake()->image('eu.png'))
            ->assertStatus(422)
            ->assertJsonPath('erro', 'Campo de envio inválido.');
    }

    /**
     * A extensão gravada vem do conteúdo, nunca do nome: o que o navegador batizou de
     * .jpg precisa ser imagem de verdade, senão o arquivo seria escrito como script
     * executável dentro de public/storage.
     */
    public function test_arquivo_disfarcado_como_imagem_e_recusado_antes_de_gravar(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'envio-');
        file_put_contents($script, "<?php echo 'oi'; ?>\n");

        $resposta = $this->enviar('produto_imagem', new UploadedFile($script, 'capa.jpg', null, null, true));

        $resposta->assertStatus(422);
        $this->assertStringContainsString('JPG, JPEG, PNG, WEBP', (string) $resposta->json('erro'));
        $this->assertSame([], Storage::disk('public')->allFiles('produtos'));
    }

    public function test_descarte_apaga_o_arquivo_mas_nao_deixa_escrever_fora_da_pasta(): void
    {
        $caminho = $this->enviar('produto_imagem', UploadedFile::fake()->image('erro.png'))->json('arquivo.caminho');
        $outro = $this->enviar('pedido_documento', UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf'))->json('arquivo.caminho');

        $this->actingAs($this->admin())->deleteJson('/admin/uploads', [
            'campo' => 'produto_imagem',
            'caminho' => $outro,
        ])->assertStatus(422);

        Storage::disk('public')->assertExists($outro);

        $this->actingAs($this->admin())->deleteJson('/admin/uploads', [
            'campo' => 'produto_imagem',
            'caminho' => $caminho,
        ])->assertOk();

        Storage::disk('public')->assertMissing($caminho);
    }

    public function test_produto_guarda_imagem_e_video_enviados_pelos_campos(): void
    {
        $imagem = $this->enviar('produto_imagem', UploadedFile::fake()->image('kit.jpg'))->json('arquivo.caminho');
        $video = $this->enviar('produto_video', UploadedFile::fake()->create('kit.mp4', 800, 'video/mp4'))->json('arquivo.caminho');

        $this->actingAs($this->admin())->post(route('admin.catalogo.produtos.store'), [
            'nome' => 'Kit envio testado',
            'preco' => 199.9,
            'imagem' => $imagem,
            'video' => $video,
        ])->assertRedirect(route('admin.catalogo.produtos.index'));

        $produto = Product::where('nome', 'Kit envio testado')->firstOrFail();
        $this->assertSame($imagem, $produto->imagem);
        $this->assertStringStartsWith('produtos/video/', $video);
        $this->assertSame($video, $produto->video);

        // A vitrine em cache também passa a levar o vídeo, sem consulta nova.
        $this->assertSame($video, Product::doCatalogo($produto->id)['video']);
    }

    public function test_produto_recusa_caminho_que_nao_veio_do_certo_campo_de_envio(): void
    {
        $this->actingAs($this->admin())->post(route('admin.catalogo.produtos.store'), [
            'nome' => 'Produto forjado',
            'preco' => 10,
            'imagem' => 'pedidos/qualquer-coisa.pdf',
        ])->assertSessionHasErrors('imagem');

        $this->assertFalse(Product::where('nome', 'Produto forjado')->exists());
    }

    public function test_trocar_a_imagem_do_produto_apaga_o_arquivo_antigo(): void
    {
        $antiga = $this->enviar('produto_imagem', UploadedFile::fake()->image('antiga.jpg'))->json('arquivo.caminho');
        $nova = $this->enviar('produto_imagem', UploadedFile::fake()->image('nova.jpg'))->json('arquivo.caminho');

        $produto = Product::firstOrFail();
        $produto->update(['imagem' => $antiga]);

        $this->actingAs($this->admin())->post(route('admin.catalogo.produtos.update', $produto), [
            'nome' => $produto->nome,
            'preco' => 10,
            'imagem' => $nova,
        ])->assertRedirect(route('admin.catalogo.produtos.index'));

        $this->assertSame($nova, $produto->refresh()->imagem);
        Storage::disk('public')->assertMissing($antiga);
        Storage::disk('public')->assertExists($nova);
    }

    public function test_remover_o_arquivo_no_campo_limpa_o_registro_e_o_disco(): void
    {
        $caminho = $this->enviar('produto_imagem', UploadedFile::fake()->image('sozinha.png'))->json('arquivo.caminho');
        $produto = Product::firstOrFail();
        $produto->update(['imagem' => $caminho]);

        // O dropzone não envia o campo quando o operador remove o arquivo.
        $this->actingAs($this->admin())->post(route('admin.catalogo.produtos.update', $produto), [
            'nome' => $produto->nome,
            'preco' => 10,
        ])->assertRedirect(route('admin.catalogo.produtos.index'));

        $this->assertNull($produto->refresh()->imagem);
        Storage::disk('public')->assertMissing($caminho);
    }

    public function test_documentos_do_pedido_sao_salvos_e_os_removidos_saem_do_disco(): void
    {
        $um = $this->enviar('pedido_documento', UploadedFile::fake()->create('comprovante.pdf', 40, 'application/pdf'))->json('arquivo.caminho');
        $dois = $this->enviar('pedido_documento', UploadedFile::fake()->create('nfe.pdf', 40, 'application/pdf'))->json('arquivo.caminho');
        $pedido = Order::firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.pedidos.documentos', $pedido), [
            'documentos' => [$um, $dois],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame([$um, $dois], $pedido->refresh()->documentos);

        $this->actingAs($this->admin())->post(route('admin.pedidos.documentos', $pedido), [
            'documentos' => [$dois],
        ])->assertRedirect();

        $this->assertSame([$dois], $pedido->refresh()->documentos);
        Storage::disk('public')->assertMissing($um);
        Storage::disk('public')->assertExists($dois);
    }

    public function test_documento_do_pedido_nao_pode_apontar_para_pasta_de_imagem(): void
    {
        $pedido = Order::firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.pedidos.documentos', $pedido), [
            'documentos' => ['produtos/armazenada.jpg'],
        ])->assertSessionHasErrors('documentos');

        $this->assertNull($pedido->refresh()->documentos);
    }

    public function test_o_campo_descreve_formato_limite_e_dimensao_dentro_do_upload(): void
    {
        $pagina = $this->actingAs($this->admin())->get(route('admin.catalogo.produtos.create'))->assertOk();

        $pagina->assertSee('JPG / JPEG / PNG / WEBP')
            ->assertSee('até 4 MB')
            ->assertSee('ideal 1200 × 1200 px (1:1)', false)
            ->assertSee('Arraste a foto aqui ou clique para escolher')
            ->assertSee('data-campo="produto_imagem"', false)
            ->assertSee('data-campo="produto_video"', false)
            ->assertSee('MP4 / WEBM')
            ->assertSee('até 40 MB');
    }

    public function test_pagina_do_pedido_traz_o_campo_de_documentos_com_os_ja_enviados(): void
    {
        $caminho = $this->enviar('pedido_documento', UploadedFile::fake()->create('nfe.pdf', 30, 'application/pdf'))->json('arquivo.caminho');
        $pedido = Order::firstOrFail();
        $pedido->update(['documentos' => [$caminho]]);

        $this->actingAs($this->admin())->get(route('admin.pedidos.show', $pedido))
            ->assertOk()
            ->assertSee('Arraste comprovantes e notas aqui ou clique para escolher')
            ->assertSee('PDF / JPG / JPEG / PNG')
            ->assertSee('até 10 MB')
            ->assertSee(\basename($caminho), false);
    }
}
