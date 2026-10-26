<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\ChavesDeIntegracao;
use App\Support\DepoimentosDoGoogle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * As três origens do depoimento e o carrossel que as mistura.
 *
 * O painel escreve (`site`), o Google traz a avaliação com a foto de quem escreveu (`google`,
 * idempotente pelo `reviewId`) e o cliente ativo manda o relato dele pela própria conta
 * (`cliente`) — esse sempre entra oculto, porque publicar é decisão da equipe. No site os três
 * saem do mesmo `aprovadosParaSite()`: quem tem foto aparece com a cara do autor, quem não tem
 * fica com a inicial. A ficha de CRM é a assinatura do enviado pelo portal, e ela é o que faz o
 * cliente ver o próprio texto na tela — nunca o de outro cliente.
 */
class DepoimentosDasTresOrigensTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Setting::esquecerMemoria();
        $this->admin = User::where('email', 'admin@golden.com.br')->firstOrFail();
        Storage::fake('public');
    }

    private function configurarGoogle(): void
    {
        config([
            'golden.google_business.account' => '1234',
            'golden.google_business.location' => '5678',
            'golden.google_business.token' => 'token-de-teste',
        ]);
    }

    /** A resposta da Business Profile API, com uma foto de perfil de verdade para baixar. */
    private function responderUmaAvaliacao(string $reviewId = 'rev-1', string $nota = 'FIVE'): void
    {
        Http::fake([
            'mybusiness.googleapis.com/v4/*' => Http::response([
                'reviews' => [[
                    'reviewId' => $reviewId,
                    'starRating' => $nota,
                    'comment' => 'O rastreador salvou minha viagem de entrega.',
                    'createTime' => '2026-10-01T12:00:00Z',
                    'reviewer' => [
                        'displayName' => 'João Estrada',
                        'profilePhotoUrl' => 'https://lh3.googleusercontent.com/a/foto-joao',
                    ],
                ]],
                'nextPageToken' => null,
            ]),
            'lh3.googleusercontent.com/*' => Http::response('bytes-da-foto', 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    public function test_o_painel_grava_a_origem_e_o_avatar_do_depoimento(): void
    {
        Storage::disk('public')->put('site/depoimentos/joao.jpg', 'foto');

        $resposta = $this->actingAs($this->admin)->post(route('admin.conteudo.depoimentos.store'), [
            'nome' => 'Ana Pista',
            'cidade' => 'Três Pontas',
            'papel' => 'Transportadora',
            'texto' => 'Plataforma respondeu rápido na instalação.',
            'nota' => 5,
            'ordem' => 0,
            'fonte' => 'site',
            'avatar' => 'site/depoimentos/joao.jpg',
            'aprovado' => '1',
        ]);

        $resposta->assertRedirect(route('admin.conteudo.depoimentos.index'));

        $depoimento = Testimonial::where('nome', 'Ana Pista')->firstOrFail();
        $this->assertSame('site', $depoimento->fonte);
        $this->assertSame('site/depoimentos/joao.jpg', $depoimento->avatar);
        $this->assertSame(asset('storage/site/depoimentos/joao.jpg'), $depoimento->avatarUrl());
    }

    public function test_o_painel_recusa_um_avatar_que_nao_foi_carregado_por_ele(): void
    {
        $resposta = $this->actingAs($this->admin)->post(route('admin.conteudo.depoimentos.store'), [
            'nome' => 'Esperto',
            'texto' => 'Texto válido qualquer.',
            'nota' => 4,
            'fonte' => 'site',
            'avatar' => 'https://site-qualquer.com.br/foto.jpg',
        ]);

        $resposta->assertSessionHasErrors('avatar');
        $this->assertFalse(Testimonial::where('nome', 'Esperto')->exists());
    }

    public function test_editar_sem_tocar_no_avatar_mantem_a_foto(): void
    {
        $depoimento = Testimonial::create([
            'nome' => 'Rita Rota', 'texto' => 'Equipamento firme.', 'nota' => 5,
            'fonte' => 'google', 'externo_id' => 'rev-9', 'avatar' => 'site/depoimentos/rita.jpg', 'aprovado' => true,
        ]);
        Storage::disk('public')->put('site/depoimentos/rita.jpg', 'foto');

        $this->actingAs($this->admin)->post(route('admin.conteudo.depoimentos.update', $depoimento), [
            'nome' => 'Rita Rota', 'texto' => 'Equipamento firme e o suporte ajuda.', 'nota' => 5,
            'fonte' => 'google', 'aprovado' => '1',
        ])->assertRedirect();

        $this->assertSame('site/depoimentos/rita.jpg', $depoimento->fresh()->avatar);
        Storage::disk('public')->assertExists('site/depoimentos/rita.jpg');
    }

    public function test_listar_filtra_pela_origem_e_mostra_os_pendentes(): void
    {
        Testimonial::create(['nome' => 'Do Google', 'texto' => 'Bom demais.', 'nota' => 5, 'fonte' => 'google', 'aprovado' => true]);
        Testimonial::create(['nome' => 'Do portal', 'texto' => 'Funcionou bem.', 'nota' => 4, 'fonte' => 'cliente', 'aprovado' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.conteudo.depoimentos.index', ['fonte' => 'cliente']))
            ->assertOk()
            ->assertSee('Do portal')
            ->assertDontSee('Do Google');
    }

    public function test_a_importacao_do_google_traz_nome_nota_texto_e_a_foto_baixada(): void
    {
        $this->configurarGoogle();
        $this->responderUmaAvaliacao();

        $this->artisan('depoimentos:google')->assertSuccessful();

        $depoimento = Testimonial::where('externo_id', 'rev-1')->firstOrFail();
        $this->assertSame('João Estrada', $depoimento->nome);
        $this->assertSame('google', $depoimento->fonte);
        $this->assertSame(5, $depoimento->nota);
        $this->assertTrue($depoimento->aprovado);
        $this->assertStringStartsWith('site/depoimentos/', (string) $depoimento->avatar);
        Storage::disk('public')->assertExists((string) $depoimento->avatar);
    }

    public function test_reimportar_atualiza_pelo_reviewid_em_vez_de_duplicar(): void
    {
        $this->configurarGoogle();
        $this->responderUmaAvaliacao();
        $this->artisan('depoimentos:google')->assertSuccessful();

        $depoimento = Testimonial::where('externo_id', 'rev-1')->firstOrFail();
        $depoimento->update(['aprovado' => false]);

        $this->artisan('depoimentos:google')->assertSuccessful();

        $this->assertSame(1, Testimonial::where('externo_id', 'rev-1')->count());
        // Desligado no painel continua desligado depois da reimportação.
        $this->assertFalse($depoimento->fresh()->aprovado);
    }

    public function test_o_importador_deixa_fora_o_que_nao_tem_texto_ou_est_abaixo_da_nota(): void
    {
        $this->configurarGoogle();
        Http::fake([
            'mybusiness.googleapis.com/v4/*' => Http::response([
                'reviews' => [
                    ['reviewId' => 'sem-texto', 'starRating' => 'FIVE', 'comment' => '', 'reviewer' => ['displayName' => 'Mudo']],
                    ['reviewId' => 'nota-baixa', 'starRating' => 'THREE', 'comment' => 'Poderia ser melhor.', 'reviewer' => ['displayName' => 'Insatisfeito']],
                    ['reviewId' => 'boim', 'starRating' => 'FOUR', 'comment' => 'Cumpre o que promete.', 'reviewer' => ['displayName' => 'Boim']],
                ],
                'nextPageToken' => null,
            ]),
        ]);

        $this->artisan('depoimentos:google')->assertSuccessful();

        $this->assertSame(['boim'], Testimonial::whereNotNull('externo_id')->pluck('externo_id')->all());
    }

    public function test_sem_as_chaves_do_google_a_importacao_avisa_em_vez_de_chamar_a_api(): void
    {
        config([
            'golden.google_business.account' => '',
            'golden.google_business.location' => '',
            'golden.google_business.token' => '',
        ]);
        Setting::esquecerMemoria();
        Http::fake();

        $this->artisan('depoimentos:google')
            ->expectsOutputToContain('GOOGLE_BUSINESS_TOKEN')
            ->expectsOutputToContain(ChavesDeIntegracao::CAMINHO_NO_PAINEL)
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_o_aviso_das_avaliacoes_aponta_a_tela_de_chaves_e_nao_o_env(): void
    {
        config([
            'golden.google_business.account' => '',
            'golden.google_business.location' => '',
            'golden.google_business.token' => '',
        ]);
        Setting::esquecerMemoria();

        $aviso = DepoimentosDoGoogle::avisoDeConfiguracao();

        $this->assertStringContainsString(ChavesDeIntegracao::CAMINHO_NO_PAINEL, $aviso);
        $this->assertStringContainsString('Token de acesso', $aviso);
        $this->assertStringNotContainsString('.env', $aviso);
    }

    public function test_as_chaves_gravadas_no_painel_configuram_a_leitura_sem_tocar_no_env(): void
    {
        config([
            'golden.google_business.account' => '',
            'golden.google_business.location' => '',
            'golden.google_business.token' => '',
        ]);
        Setting::esquecerMemoria();

        ChavesDeIntegracao::guardar('google_business_account', '1234');
        ChavesDeIntegracao::guardar('google_business_location', '5678');
        ChavesDeIntegracao::guardar('google_business_token', 'token-do-painel');
        ChavesDeIntegracao::aplicar();

        $this->assertSame('banco', ChavesDeIntegracao::origem('google_business_token'));
        $this->assertSame([], DepoimentosDoGoogle::faltantes());
        $this->assertSame('accounts/1234/locations/5678', DepoimentosDoGoogle::recurso());

        $this->responderUmaAvaliacao();
        $this->artisan('depoimentos:google')->assertSuccessful();

        $this->assertTrue(Testimonial::where('externo_id', 'rev-1')->exists());
    }

    public function test_o_cliente_ativo_envia_pelo_portal_e_nasce_oculto_assinado_pela_ficha(): void
    {
        $cliente = Customer::create([
            'nome' => 'Maria Compradora', 'documento' => '123.456.789-01', 'tipo' => 'fisica',
            'email' => 'maria@exemplo.com.br', 'email_portal' => 'maria@exemplo.com.br',
            'cidade' => 'Três Pontas', 'status' => 'ativo',
        ]);
        $cliente->password = 'senha-portal-123';
        $cliente->save();

        $this->actingAs($cliente, 'cliente')
            ->post(route('portal.depoimento.enviar'), [
                'nome' => 'Maria C.',
                'papel' => 'Transportadora autônoma',
                'texto' => 'Instalei o J16 na frota e o relatório de percurso mudou meu dia.',
                'nota' => 5,
            ])
            ->assertRedirect(route('portal.depoimento'))
            ->assertSessionHas('success');

        $depoimento = Testimonial::where('fonte', 'cliente')->firstOrFail();
        $this->assertFalse($depoimento->aprovado);
        $this->assertSame($cliente->id, $depoimento->customer_id);
        $this->assertSame('Três Pontas', $depoimento->cidade);
    }

    public function test_o_portal_nao_deixa_enviar_duas_vezes_sem_aprovacao_e_nem_a_ficha_desativada(): void
    {
        $ativa = Customer::create([
            'nome' => 'Clara Conta', 'email' => 'clara@exemplo.com.br', 'email_portal' => 'clara@exemplo.com.br', 'status' => 'ativo',
        ]);
        $ativa->password = 'senha-portal-123';
        $ativa->save();

        $dados = ['nome' => 'Clara', 'texto' => 'Atendimento nota dez, resolveu o que ninguém resolveu.', 'nota' => 5];

        $this->actingAs($ativa, 'cliente')->post(route('portal.depoimento.enviar'), $dados)->assertSessionHas('success');
        $this->actingAs($ativa, 'cliente')->post(route('portal.depoimento.enviar'), $dados)
            ->assertSessionHas('warning');
        $this->assertSame(1, Testimonial::where('customer_id', $ativa->id)->count());

        $desativada = Customer::create([
            'nome' => 'Dora Desativada', 'email' => 'dora@exemplo.com.br', 'email_portal' => 'dora@exemplo.com.br', 'status' => 'inativo',
        ]);
        $desativada->password = 'senha-portal-123';
        $desativada->save();

        $this->actingAs($desativada, 'cliente')->post(route('portal.depoimento.enviar'), $dados)
            ->assertSessionHas('error');
        $this->assertFalse(Testimonial::where('customer_id', $desativada->id)->exists());
    }

    public function test_o_portal_mostra_o_que_a_ficha_ja_enviou_e_esconde_o_dos_outros(): void
    {
        $dona = Customer::create(['nome' => 'Elis Enviosa', 'email' => 'elis@exemplo.com.br', 'email_portal' => 'elis@exemplo.com.br', 'status' => 'ativo']);
        $dona->password = 'senha-portal-123';
        $dona->save();
        $outra = Customer::create(['nome' => 'Outra Cliente', 'email' => 'outra@exemplo.com.br', 'email_portal' => 'outra@exemplo.com.br', 'status' => 'ativo']);

        Testimonial::create(['nome' => 'Elis', 'texto' => 'Meu relato do rastreador novo.', 'nota' => 5, 'fonte' => 'cliente', 'customer_id' => $dona->id, 'aprovado' => false]);
        Testimonial::create(['nome' => 'Outra', 'texto' => 'Relato de outra pessoa aqui.', 'nota' => 4, 'fonte' => 'cliente', 'customer_id' => $outra->id, 'aprovado' => false]);

        $this->actingAs($dona, 'cliente')->get(route('portal.depoimento'))
            ->assertOk()
            ->assertSee('Meu relato do rastreador novo')
            ->assertDontSee('Relato de outra pessoa');
    }

    public function test_o_carrossel_da_home_mistura_as_tres_origens_com_a_foto_de_cada_autor(): void
    {
        Storage::disk('public')->put('site/depoimentos/joao.jpg', 'foto');
        Testimonial::create(['nome' => 'Do Google', 'texto' => 'Bom demais.', 'nota' => 5, 'fonte' => 'google', 'avatar' => 'site/depoimentos/joao.jpg', 'aprovado' => true, 'ordem' => 0]);
        Testimonial::create(['nome' => 'Do painel', 'texto' => 'Cliente de longa data.', 'nota' => 5, 'fonte' => 'site', 'aprovado' => true, 'ordem' => 1]);
        Testimonial::create(['nome' => 'Oculto', 'texto' => 'Ainda aguardando a equipe.', 'nota' => 3, 'fonte' => 'cliente', 'aprovado' => false, 'ordem' => 2]);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('js-carrossel')
            ->assertSee('Bom demais.')
            ->assertSee('storage/site/depoimentos/joao.jpg')
            ->assertSee('Cliente de longa data.')
            ->assertDontSee('Ainda aguardando a equipe.');
    }
}
