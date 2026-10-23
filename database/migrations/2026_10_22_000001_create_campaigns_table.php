<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // ex.: "Black Novembro Frota"
            $table->string('source')->nullable();         // utm_source / gclid / fbclid (google, meta...)
            $table->string('medium')->nullable();         // cpc, paid-social, organic...
            $table->string('content')->nullable();        // utm_content / criativo
            $table->string('channel')->default('pago');   // pago | organico | direto | referencia
            $table->boolean('ativa')->default(true);
            $table->timestamps();

            $table->unique(['name', 'source', 'medium'], 'campaigns_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
