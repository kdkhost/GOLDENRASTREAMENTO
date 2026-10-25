<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O cadastro feito por uma página de funil precisa dizer a qual campanha pertence, e o
 * identificador de clique que a plataforma de anúncio cola na URL (ttclid do TikTok,
 * msclkid do Bing, gclid do Google, fbclid do Meta) precisa chegar na ficha do lead em
 * vez de ficar só na visita. `clique_id`/`clique_valor` guardam qualquer um deles, com o
 * mesmo par de colunas que a tabela de visitas já usa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('funnel_page_id')->nullable()->constrained('funnel_pages')->nullOnDelete();

            $table->string('clique_id', 40)->nullable();     // gclid, fbclid, ttclid, msclkid…
            $table->string('clique_valor', 120)->nullable();

            $table->index(['funnel_page_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['funnel_page_id', 'created_at']);
            $table->dropConstrainedForeignId('funnel_page_id');
            $table->dropColumn(['clique_id', 'clique_valor']);
        });
    }
};
