<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices dos caminhos de leitura do site e das listas do painel. As páginas públicas
 * servem do cache, mas toda gravação no painel invalida a entrada e a próxima leitura
 * precisa encontrar as linhas sem varredura completa na tabela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['ativo', 'ordem'], 'products_ativo_ordem_idx');
            $table->index(['category_id', 'ativo'], 'products_category_ativo_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['ativa', 'ordem'], 'categories_ativa_ordem_idx');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->index(['publicada', 'exibir_menu', 'ordem_menu'], 'pages_menu_idx');
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->index(['aprovado', 'ordem'], 'testimonials_aprovado_ordem_idx');
        });

        Schema::table('faqs', function (Blueprint $table) {
            $table->index(['ativa', 'ordem'], 'faqs_ativa_ordem_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'orders_status_created_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['product_id', 'created_at'], 'stock_product_created_idx');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->index(['campaign_id', 'created_at'], 'leads_campaign_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_ativo_ordem_idx');
            $table->dropIndex('products_category_ativo_idx');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_ativa_ordem_idx');
        });
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex('pages_menu_idx');
        });
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropIndex('testimonials_aprovado_ordem_idx');
        });
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropIndex('faqs_ativa_ordem_idx');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_created_idx');
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_product_created_idx');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_campaign_created_idx');
        });
    }
};
