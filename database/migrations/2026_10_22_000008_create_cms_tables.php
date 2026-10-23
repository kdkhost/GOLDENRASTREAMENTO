<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('slug')->unique();
            $table->string('eyebrow')->nullable();
            $table->text('intro')->nullable();
            $table->longText('corpo')->nullable();        // HTML/parágrafos editáveis
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->boolean('exibir_menu')->default(false);
            $table->unsignedInteger('ordem_menu')->default(0);
            $table->boolean('publicada')->default(true);
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('cidade', 96)->nullable();
            $table->string('papel')->nullable();          // ex: Motorista de app
            $table->text('texto');
            $table->unsignedTinyInteger('nota')->default(5);
            $table->boolean('aprovado')->default(true);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('pergunta');
            $table->text('resposta');
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('ativa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('pages');
    }
};
