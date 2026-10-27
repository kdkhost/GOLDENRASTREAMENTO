<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conversa passa a saber com quem está falando — e o que essa pessoa achou do atendimento.
 *
 * `email` é a metade que faltava da identificação. O nome já era aproveitado de qualquer fala, mas
 * atendimento personalizado é outro tipo de conversa: a segunda via da fatura, o status de um
 * chamado, qualquer coisa que olhe a conta da pessoa, precisa de um endereço para saber de quem é a
 * conta. Sem a coluna, o e-mail digitado no chat vivia só no histórico e a máquina precisava
 * perguntá-lo de novo dentro de cada ação — três perguntas para uma pessoa que já tinha respondido
 * duas. Ele nunca é destino de envio: a ação continua mandando para a coluna da ficha, não para o
 * que foi digitado aqui.
 *
 * `email_pedidos` é o mesmo teto do `nome_pedidos`: a casa pede, insiste no máximo até o número do
 * painel e segue atendendo. Atendimento travado atrás de um dado que o visitante não quer dar é
 * lead perdido, não segurança ganha.
 *
 * As quatro colunas de avaliação existem porque o atendimento da Ana precisa de medida: `nota` é o
 * número de 1 a 5, `texto` é o que a pessoa escreveu junto, `avaliada_em` prova quando e
 * `avaliacao_pedida_em` evita a máquina pedir duas notas na mesma conversa. Índice na nota porque a
 * média da casa é leitura de painel, e painel que soma coluna sem índice é consulta que cresce com
 * o volume de conversas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->string('email', 180)->nullable()->after('contato');
            $table->tinyInteger('email_pedidos')->unsigned()->default(0)->after('nome_pedidos');
            $table->tinyInteger('avaliacao_nota')->unsigned()->nullable()->after('email_pedidos');
            $table->string('avaliacao_texto', 500)->nullable()->after('avaliacao_nota');
            $table->timestamp('avaliada_em')->nullable()->after('avaliacao_texto');
            $table->timestamp('avaliacao_pedida_em')->nullable()->after('avaliada_em');

            $table->index('email');
            $table->index('avaliacao_nota');
        });
    }

    public function down(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['avaliacao_nota']);
            $table->dropColumn([
                'email', 'email_pedidos', 'avaliacao_nota', 'avaliacao_texto', 'avaliada_em', 'avaliacao_pedida_em',
            ]);
        });
    }
};
