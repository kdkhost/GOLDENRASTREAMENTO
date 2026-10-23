<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 32);
            $table->string('email')->nullable();
            $table->string('profile')->nullable();     // motorista | empresa | locadora | rural | outro
            $table->string('fleet_size', 16)->nullable(); // 1 | 5 | 10 | 10+
            $table->string('kit')->nullable();         // kit escolhido
            $table->string('message')->nullable();     // observação livre do visitante

            // Atribuição de campanha / tráfego pago
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('gclid')->nullable();
            $table->string('fbclid')->nullable();
            $table->string('visitor_id', 64)->nullable()->index();
            $table->string('landing_page')->nullable();
            $table->string('referrer')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent')->nullable();

            // Funil e gestão
            $table->string('status', 24)->default('novo')->index(); // novo|contatado|proposta|vendido|perdido
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('consent_given')->default(false);
            $table->unsignedInteger('value')->nullable(); // valor fechado (centavos) quando vendido
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
