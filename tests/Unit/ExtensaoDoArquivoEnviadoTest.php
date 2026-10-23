<?php

namespace Tests\Unit;

use App\Support\Envio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A extensão que vai para o disco é a do conteúdo do arquivo, e ela precisa estar
 * entre os formatos que o campo aceita. O nome enviado pelo navegador não decide
 * nada — é justamente o que quem faz o upload controla.
 */
class ExtensaoDoArquivoEnviadoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function arquivoComConteudo(string $conteudo, string $nome): UploadedFile
    {
        $destino = tempnam(sys_get_temp_dir(), 'envio-');
        file_put_contents($destino, $conteudo);

        return new UploadedFile($destino, $nome, null, null, true);
    }

    public function test_script_batizado_de_jpg_e_recusado_antes_de_tocar_o_disco(): void
    {
        $recusado = false;

        try {
            Envio::guardar($this->arquivoComConteudo("<?php echo 'oi'; ?>\n", 'capa.jpg'), 'produto_imagem');
        } catch (ValidationException $e) {
            $recusado = true;
            $this->assertStringContainsString('JPG, JPEG, PNG, WEBP', $e->errors()['arquivo'][0]);
        }

        $this->assertTrue($recusado, 'O script foi gravado como arquivo de imagem.');
        $this->assertSame([], Storage::disk('public')->allFiles(''));
    }

    public function test_script_batizado_de_pdf_e_recusado_no_campo_de_documentos(): void
    {
        $this->expectException(ValidationException::class);

        Envio::guardar($this->arquivoComConteudo("<?php echo 'oi'; ?>\n", 'notas.pdf'), 'pedido_documento');
    }

    public function test_o_nome_gravado_e_legivel_e_a_extensao_veio_do_conteudo(): void
    {
        $caminho = Envio::guardar(
            UploadedFile::fake()->image('rastreador j16 original.png', 640, 480),
            'produto_imagem'
        )['caminho'];

        $this->assertMatchesRegularExpression('#^produtos/rastreador-j16-original-[a-zA-Z0-9]{8}\.png$#', $caminho);
        Storage::disk('public')->assertExists($caminho);
    }
}
