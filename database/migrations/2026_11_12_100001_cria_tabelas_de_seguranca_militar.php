<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_ip_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('ip', 45)->index();
            $table->enum('tipo', ['whitelist', 'blacklist'])->index();
            $table->string('motivo', 255);
            $table->enum('nivel_ameaca', ['baixo', 'medio', 'alto', 'critico'])->default('medio');
            $table->dateTime('expira_em')->nullable()->index();
            $table->unsignedInteger('tentativas_bloqueadas')->default(0);
            $table->dateTime('ultimo_bloqueio_em')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('security_events', function (Blueprint $table): void {
            $table->id();
            $table->string('ip', 45)->index();
            $table->string('evento', 60)->index();
            $table->enum('nivel', ['baixo', 'medio', 'alto', 'critico'])->index();
            $table->string('metodo', 12);
            $table->text('url');
            $table->string('user_agent', 500)->nullable();
            $table->text('payload_suspeito')->nullable();
            $table->string('acao_tomada', 60);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('security_ip_rules');
    }
};
