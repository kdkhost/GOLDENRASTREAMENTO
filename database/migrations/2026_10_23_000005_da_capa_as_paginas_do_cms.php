<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada página do CMS passa a ter a própria foto de capa, enviada pelo formulário
 * da página (Admin → Conteúdo → Páginas) e guardada como caminho relativo no
 * disco público — do mesmo jeito que o produto faz. Sem envio, a página abre
 * com o fundo padrão do tema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('imagem')->nullable()->after('corpo');
            $table->string('imagem_alt')->nullable()->after('imagem');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['imagem', 'imagem_alt']);
        });
    }
};
