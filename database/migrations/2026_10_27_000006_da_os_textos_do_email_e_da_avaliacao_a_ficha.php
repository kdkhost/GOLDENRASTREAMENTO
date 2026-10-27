<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As quatro falas que faltavam na ficha do assistente.
 *
 * Tudo que a máquina diz ao cliente por fora da IA é texto fixo da casa, e por isso mora em coluna
 * própria da ficha: `pergunta_email` e `confirmacao_email` fecham a identificação (a máquina pede o
 * endereço e reconhece quem o deu, sem gastar uma chamada de IA com um e-mail digitado sozinho);
 * `pedido_avaliacao` e `agradecimento_avaliacao` são a vez da pessoa avaliar o atendimento da Ana.
 *
 * É a mesma decisão da `pergunta_nome` e da `confirmacao_nome`: uma frase que sai do código não pode
 * ser frase de programador. Em branco em cada coluna, vale o padrão de
 * `config('assistente.persona')` — e o painel muda sem mexer em arquivo nenhum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistente', function (Blueprint $table) {
            $table->text('pergunta_email')->nullable()->after('confirmacao_nome');
            $table->text('confirmacao_email')->nullable()->after('pergunta_email');
            $table->text('pedido_avaliacao')->nullable()->after('confirmacao_email');
            $table->text('agradecimento_avaliacao')->nullable()->after('pedido_avaliacao');
        });
    }

    public function down(): void
    {
        Schema::table('assistente', function (Blueprint $table) {
            $table->dropColumn(['pergunta_email', 'confirmacao_email', 'pedido_avaliacao', 'agradecimento_avaliacao']);
        });
    }
};
