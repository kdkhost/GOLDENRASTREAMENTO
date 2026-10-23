<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id', 64)->index();     // cookie anônimo de 90 dias
            $table->string('sessao', 40)->nullable();      // sessão Laravel que originou a visita

            // A decisão do classificador (App\Services\Atribuidor).
            $table->string('canal', 24)->default('desconhecido');
            $table->string('rede', 40)->nullable();
            $table->boolean('pago')->default(false);
            $table->string('motivo', 40)->nullable();      // utm_medium | utm_source | referer | gclid | direto | robô

            // A matéria-prima da decisão: fica guardada para a decisão ser auditável.
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 190)->nullable();
            $table->string('utm_content', 190)->nullable();
            $table->string('utm_term', 190)->nullable();
            $table->string('clique_id', 40)->nullable();   // gclid, fbclid, ttclid, msclkid...
            $table->string('clique_valor', 120)->nullable();
            $table->string('referer', 255)->nullable();
            $table->string('origem_host', 120)->nullable();
            $table->string('origem_path', 190)->nullable();
            $table->string('landing_path', 190)->nullable();

            $table->string('dispositivo', 16)->nullable();  // celular | tablet | computador | robô
            $table->string('navegador', 24)->nullable();    // chrome, instagram, whatsapp, google-app...
            $table->string('sistema', 24)->nullable();
            $table->string('ip_hash', 64)->nullable();      // nunca o IP em claro

            $table->boolean('bot')->default(false);
            $table->unsignedSmallInteger('visualizacoes')->default(0);
            $table->unsignedSmallInteger('eventos')->default(0);
            $table->dateTime('aberta_em')->nullable();
            $table->dateTime('ultima_em')->nullable();
            $table->timestamps();

            $table->index(['canal', 'ultima_em']);
            $table->index(['rede', 'ultima_em']);
            $table->index(['ultima_em', 'bot']);
            $table->index(['visitor_id', 'ultima_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
