<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A galeria de vídeos tutoriais do site antigo volta à plataforma nova com a mesma
 * promessa das outras telas de conteúdo: o administrador cola o link que copiou do
 * YouTube, o sistema guarda só o código de onze caracteres (é ele que segura miniatura,
 * player e a regra de "este vídeo já está na galeria"), e a página pública serve tudo
 * do cache — abrir a galeria não visita o banco.
 */
class GaleriaDeVideosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
    }

    public function test_o_painel_guarda_o_codigo_de_um_link_completo(): void
    {
        $this->actingAs($this->admin)->post(route('admin.conteudo.videos.store'), [
            'titulo' => 'Como criar uma cerca virtual',
            'codigo' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'categoria' => 'plataforma',
            'ordem' => 3,
            'ativa' => '1',
        ])->assertRedirect(route('admin.conteudo.videos.index'))->assertSessionHasNoErrors();

        $video = Video::where('codigo', 'dQw4w9WgXcQ')->firstOrFail();
        $this->assertSame('Como criar uma cerca virtual', $video->titulo);
        $this->assertSame(3, $video->ordem);
        $this->assertTrue($video->ativa);

        $html = $this->get('/videos')->getContent();
        $this->assertStringContainsString('Como criar uma cerca virtual', $html);
        $this->assertStringContainsString('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $html);
    }

    public function test_toda_forma_de_endereco_do_youtube_chega_no_mesmo_codigo(): void
    {
        $esperado = 'OhtdphIxdf0';

        foreach ([
            'https://www.youtube.com/watch?v=OhtdphIxdf0',
            'https://youtube.com/watch?si=x7Zq&v=OhtdphIxdf0&feature=share',
            'https://m.youtube.com/watch?v=OhtdphIxdf0',
            'https://youtu.be/OhtdphIxdf0?si=9AbCdEf',
            'https://www.youtube.com/shorts/OhtdphIxdf0',
            'https://www.youtube.com/embed/OhtdphIxdf0',
            'https://www.youtube.com/live/OhtdphIxdf0',
            'https://www.youtube.com/v/OhtdphIxdf0',
            ' OhtdphIxdf0 ',
        ] as $colado) {
            $this->assertSame($esperado, Video::codigoDe($colado), "O endereço {$colado} não chegou ao código.");
        }

        $this->assertNull(Video::codigoDe('https://www.youtube.com/@goldenrastreamento'));
        $this->assertNull(Video::codigoDe(''));
        $this->assertNull(Video::codigoDe(null));
    }

    public function test_o_mesmo_video_nao_entra_duas_vezes_na_galeria(): void
    {
        $this->actingAs($this->admin)->post(route('admin.conteudo.videos.store'), [
            'titulo' => 'Segunda vez do mesmo tutorial',
            'codigo' => 'https://youtu.be/OhtdphIxdf0',
            'categoria' => 'aplicativo',
        ])->assertSessionHasErrors('codigo');

        $this->assertSame('Este vídeo já está na galeria.', session('errors')->first('codigo'));
        $this->assertSame(1, Video::where('codigo', 'OhtdphIxdf0')->count());
    }

    public function test_um_link_sem_codigo_volta_para_a_tela_com_o_que_foi_colado(): void
    {
        $colado = 'https://www.youtube.com/results?search_query=rastreador';

        $this->actingAs($this->admin)->from(route('admin.conteudo.videos.create'))
            ->post(route('admin.conteudo.videos.store'), [
                'titulo' => 'Tutorial qualquer',
                'codigo' => $colado,
                'categoria' => 'aplicativo',
            ])->assertRedirect(route('admin.conteudo.videos.create'))->assertSessionHasErrors('codigo');

        $this->assertStringContainsString('Não encontramos o código nesse link', session('errors')->first('codigo'));

        // O que a pessoa colou continua no campo: limpar a caixa obrigaria a procurar o link de novo.
        $tela = $this->followingRedirects()->post(route('admin.conteudo.videos.store'), [
            'titulo' => 'Tutorial qualquer',
            'codigo' => $colado,
            'categoria' => 'aplicativo',
        ])->getContent();

        $this->assertStringContainsString('value="'.e($colado).'"', $tela);
        $this->assertSame(0, Video::where('codigo', 'OhtdphIxdf0')->where('titulo', 'Tutorial qualquer')->count());
    }

    public function test_a_galeria_do_site_mostra_cada_tutorial_sem_carregar_o_player(): void
    {
        $html = $this->get('/videos')->assertOk()->getContent();

        $this->assertSame(Video::count(), substr_count($html, 'class="video-card"'));
        $this->assertSame(Video::count(), substr_count($html, 'data-farol="video"'));
        $this->assertStringNotContainsString('youtube-nocookie.com/embed', $html);
        $this->assertStringNotContainsString('<iframe', $html);

        // O cartão é um link de verdade: sem JavaScript o visitante ainda assiste no YouTube.
        $this->assertStringContainsString('href="https://www.youtube.com/watch?v=OhtdphIxdf0"', $html);
        $this->assertStringContainsString('id="videoModal"', $html);
    }

    public function test_o_filtro_de_categoria_so_deixa_os_videos_daquela_categoria(): void
    {
        $html = $this->get('/videos?categoria=frotas')->assertOk()->getContent();

        $this->assertStringContainsString('Frotas e carros de aluguel', $html);
        $this->assertStringNotContainsString('Conheça o app Golden', $html);
        $this->assertSame(Video::where('categoria', 'frotas')->count(), substr_count($html, 'class="video-card"'));

        $this->get('/videos?categoria=inexistente')->assertOk()
            ->assertSee('Ainda não há vídeo nessa categoria', false);
    }

    public function test_um_video_oculto_no_painel_sai_da_galeria(): void
    {
        $video = Video::where('codigo', 'OhtdphIxdf0')->firstOrFail();
        $video->update(['ativa' => false]);

        $html = $this->get('/videos')->assertOk()->getContent();
        $this->assertStringNotContainsString('OhtdphIxdf0', $html);
        $this->assertSame(Video::where('ativa', true)->count(), substr_count($html, 'class="video-card"'));
    }

    public function test_gravar_no_painel_devolve_a_galeria_sem_cache(): void
    {
        $esta = $this->get('/videos')->getContent();
        $this->assertStringNotContainsString('Tutorial gravado agora', $esta);

        $this->actingAs($this->admin)->post(route('admin.conteudo.videos.store'), [
            'titulo' => 'Tutorial gravado agora',
            'codigo' => 'dQw4w9WgXcQ',
            'categoria' => 'financeiro',
            'ativa' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertStringContainsString('Tutorial gravado agora', $this->get('/videos')->getContent());

        Video::where('codigo', 'dQw4w9WgXcQ')->firstOrFail()->delete();
        $this->assertStringNotContainsString('Tutorial gravado agora', $this->get('/videos')->getContent());
    }

    public function test_a_home_aponta_para_a_galeria_em_vez_de_repeti_la(): void
    {
        $home = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('site.videos').'"', $home);
        $this->assertStringNotContainsString('class="videos-grid"', $home);
        $this->assertStringContainsString('Vídeos tutoriais', $this->get('/contato')->getContent());
    }

    public function test_cada_categoria_tem_rotulo_icone_cor_e_a_jornada_a_conhece(): void
    {
        $tons = (array) config('golden.tons_status.video');

        foreach (Video::categorias() as $chave => $categoria) {
            $this->assertNotEmpty($categoria['rotulo'] ?? null);
            $this->assertNotEmpty($categoria['icone'] ?? null);
            $this->assertArrayHasKey($chave, $tons, "A categoria {$chave} não tem cor no config.");
        }

        $this->assertArrayHasKey('video', config('golden.rastreio.eventos'));
        $this->assertArrayHasKey('videos', config('golden.paginas_site'));
        $this->assertArrayHasKey('hero_videos', \App\Support\ConteudoSite::slots());
    }

    public function test_cada_tutorial_semeado_aponta_para_um_categoria_que_o_site_sabe_nomear(): void
    {
        $this->assertGreaterThan(0, Video::count());

        foreach (Video::pluck('categoria')->unique() as $categoria) {
            $this->assertArrayHasKey($categoria, Video::categorias());
        }
    }
}
