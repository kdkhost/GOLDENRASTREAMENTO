<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conversa passa a poder guardar uma ação em curso.
 *
 * Até aqui o assistente só respondia texto. A segunda via da fatura por e-mail é a primeira vez
 * que ele *faz* alguma coisa, e fazer exige estado: qual ação abriu, qual resposta ainda falta,
 * quantas tentativas erradas já houve. Sem estas quatro colunas a única alternativa seria deduzir
 * tudo relendo o histórico — e "deduzir por onde o robô ia" é exatamente como se entrega a fatura
 * de alguém a quem não devia.
 *
 * `nome_pedidos` existe pelo mesmo motivo, do outro lado: a máquina pergunta o nome antes de
 * qualquer assunto, mas não pode ficar presa perguntando para sempre se a pessoa não quer dizer.
 * É a conta das perguntas já feitas, e é ela que devolve a conversa ao atendimento normal depois
 * da terceira recusa.
 *
 * `acao_dados_json` é a mesa de trabalho da ação em curso: qual ficha foi achada, os campos que
 * ainda faltam conferir e quantos já bateram. Fica no banco, e não em memória, porque o chat é uma
 * requisição por fala — sem ela, a segunda pergunta da conferência recomeçaria do zero e ninguém
 * passaria nunca. Não guarda resposta nenhuma de cliente, nem o e-mail digitado: o que foi dito é
 * comparado e jogado fora, só sobra a contagem.
 *
 * `customer_id` já existia (é a ponte da conversa com a ficha) e é preenchida só quando a
 * verificação passa — nunca antes, para uma conta não aparecer na tela de quem falhou de propósito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->string('acao', 40)->nullable()->after('escala_motivo');
            $table->string('acao_passo', 24)->nullable()->after('acao');
            $table->tinyInteger('acao_tentativas')->unsigned()->default(0)->after('acao_passo');
            $table->json('acao_dados_json')->nullable()->after('acao_tentativas');
            $table->timestamp('acao_em')->nullable()->after('acao_dados_json');
            $table->tinyInteger('nome_pedidos')->unsigned()->default(0)->after('acao_em');
        });
    }

    public function down(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->dropColumn(['acao', 'acao_passo', 'acao_tentativas', 'acao_dados_json', 'acao_em', 'nome_pedidos']);
        });
    }
};
