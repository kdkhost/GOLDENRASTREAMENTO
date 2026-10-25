<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 160);
            $table->string('codigo', 20)->unique();   // id do YouTube: é a identidade do vídeo na galeria
            $table->string('categoria', 60);
            $table->text('descricao')->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('ativa')->default(true);
            $table->timestamps();

            $table->index(['ativa', 'ordem']);
            $table->index('categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
