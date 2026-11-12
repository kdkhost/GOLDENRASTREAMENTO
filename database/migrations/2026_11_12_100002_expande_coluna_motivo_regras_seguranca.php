<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_ip_rules', function (Blueprint $table): void {
            $table->text('motivo')->change();
        });
    }

    public function down(): void
    {
        Schema::table('security_ip_rules', function (Blueprint $table): void {
            $table->string('motivo', 255)->change();
        });
    }
};
