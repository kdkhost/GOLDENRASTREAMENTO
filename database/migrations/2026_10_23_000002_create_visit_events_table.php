<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->string('visitor_id', 64)->nullable()->index();
            $table->string('tipo', 24);                     // view|clique|cta|telefone|whatsapp|email|form_inicio|lead...
            $table->string('path', 190)->nullable();
            $table->string('destino', 255)->nullable();      // para onde o clique levou
            $table->string('detalhe', 120)->nullable();      // o data-cta, o kit, o produto
            $table->timestamps();

            $table->index(['visit_id', 'tipo']);
            $table->index(['tipo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_events');
    }
};
