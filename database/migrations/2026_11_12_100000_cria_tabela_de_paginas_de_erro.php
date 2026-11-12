<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_pages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('code')->unique();
            $table->string('title', 160);
            $table->string('subtitle', 255)->nullable();
            $table->text('message')->nullable();
            $table->string('primary_button_text', 80)->nullable();
            $table->string('primary_button_url', 255)->nullable();
            $table->boolean('show_whatsapp_button')->default(true);
            $table->string('whatsapp_custom_text', 120)->nullable();
            $table->string('icon', 60)->default('fa-triangle-exclamation');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_pages');
    }
};

