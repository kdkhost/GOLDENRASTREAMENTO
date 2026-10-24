<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O depoimento deixa de ser só um texto digitado no painel: ele passa a dizer de onde veio
 * (painel, Google ou o próprio cliente no portal), guarda a foto do autor e — quando vem do
 * Google — o identificador da avaliação, para a reimportação atualizar em vez de duplicar.
 * Quando vem do portal, a ficha do CRM é a assinatura: é por ela que a equipe confere que
 * quem escreveu é cliente ativo antes de aprovar.
 *
 * `avatar` cabe em 500 porque guarda ou um caminho do disco público (`site/depoimentos/…`)
 * ou a URL do foto de perfil devolvida pelo Google; `externo_id` em 191 é o limite que o
 * MySQL aceita em índice único com utf8mb4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('fonte', 16)->default('site')->after('nome');
            $table->string('externo_id', 191)->nullable()->after('fonte');
            $table->foreignId('customer_id')->nullable()->after('externo_id')->constrained('customers')->nullOnDelete();
            $table->string('avatar', 500)->nullable()->after('texto');

            $table->unique('externo_id', 'testimonials_externo_id_unique');
            $table->index(['aprovado', 'fonte'], 'testimonials_aprovado_fonte_idx');
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex('testimonials_aprovado_fonte_idx');
            $table->dropUnique('testimonials_externo_id_unique');
            $table->dropColumn(['fonte', 'externo_id', 'customer_id', 'avatar']);
        });
    }
};
