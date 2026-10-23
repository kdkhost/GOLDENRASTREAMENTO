<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de arrastar-e-soltar do painel: o produto ganha vídeo além da imagem, e o
 * pedido passa a carregar a lista de documentos (comprovante, nota fiscal, proposta)
 * enviados pelo operador. Ambos guardam só o caminho relativo no disco público —
 * o arquivo em si entra pelo endpoint de envio, nunca pelo POST do formulário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('video')->nullable()->after('imagem');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->json('documentos')->nullable()->after('consent_given');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('video');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('documentos');
        });
    }
};
