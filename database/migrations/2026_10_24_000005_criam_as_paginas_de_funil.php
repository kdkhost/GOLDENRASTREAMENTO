<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A página de funil é conteúdo, não arquivo: o nome, o endereço, a SEO, os pixels de
 * anúncio e cada bloco vivem aqui, para o operador montar a campanha do Google, do
 * Meta ou do TikTok sem mexer no site. O slug é único porque é ele que a URL `/c/`
 * entrega, e `caminhos` guarda todo endereço que a página já teve — trocar o slug não
 * apaga o histórico de visitas do contador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_pages', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);              // como o painel chama a página
            $table->string('slug', 120)->unique();    // /c/{slug}
            $table->string('status', 20)->default('rascunho');
            $table->string('visibilidade', 24)->default('noindex');
            $table->string('pele', 16)->default('ouro');

            // Cabeçalho de SEO e de compartilhamento. Vazio = o título e a foto padrão do site.
            $table->string('titulo', 190)->nullable();
            $table->string('meta_description', 255)->nullable();
            $table->string('meta_keywords', 190)->nullable();
            $table->string('og_imagem', 255)->nullable();
            $table->string('og_imagem_alt', 190)->nullable();
            $table->string('canonical', 255)->nullable();

            // Pixels da campanha. Cada anúncio mede no próprio identificador; nada de
            // herdar o pixel global do site, que contaria a venda de outra página.
            $table->string('pixel_ga4', 40)->nullable();
            $table->string('pixel_google_ads', 40)->nullable();      // AW-000000000
            $table->string('conversao_google_ads', 60)->nullable();  // rótulo da conversão
            $table->string('pixel_meta', 60)->nullable();
            $table->string('pixel_tiktok', 60)->nullable();
            $table->text('codigo_head')->nullable();                 // outras plataformas
            $table->text('codigo_body')->nullable();

            // Matéria-prima do JSON-LD: o preço anunciado e a nota da empresa.
            $table->unsignedInteger('preco_cents')->nullable();
            $table->decimal('nota_media', 3, 2)->nullable();
            $table->unsignedInteger('num_avaliacoes')->nullable();

            $table->text('nota_legal')->nullable();
            $table->text('caminhos')->nullable();   // JSON: todo /c/... que já serviu

            $table->timestamps();

            $table->index(['status', 'slug']);
        });

        Schema::create('funnel_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_page_id')->constrained('funnel_pages')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('titulo', 190)->nullable();
            $table->text('texto')->nullable();
            $table->text('corpo')->nullable();      // HTML simples, sanitizado na gravação
            $table->string('imagem', 255)->nullable();
            $table->string('imagem_alt', 190)->nullable();
            $table->text('itens')->nullable();      // um por linha, colunas separadas por |
            $table->string('cta_texto', 120)->nullable();
            $table->string('cta_destino', 255)->nullable();
            $table->string('cta2_texto', 120)->nullable();
            $table->string('cta2_destino', 255)->nullable();
            $table->dateTime('limite_em')->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('ativa')->default(true);
            $table->timestamps();

            $table->index(['funnel_page_id', 'ativa', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_blocks');
        Schema::dropIfExists('funnel_pages');
    }
};
