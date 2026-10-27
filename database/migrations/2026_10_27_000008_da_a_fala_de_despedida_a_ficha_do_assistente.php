<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A fala com que a casa se despede de quem encerra o atendimento.
 *
 * Quem escreve "obrigado, era isso" não fez pergunta nenhuma, e responder a isso com o texto de
 * escalada deixaria o cliente sem resposta na última linha da conversa — e a equipe chamada para
 * nada. A despedida passou a ser reconhecida por regra, e regra fala com texto da ficha, não com
 * texto de programador: é a coluna que o painel edita, na mesma régua do pedido de avaliação.
 *
 * Em branco, vale o padrão de `config('assistente.persona.despedida')`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistente', function (Blueprint $table) {
            $table->text('despedida')->nullable()->after('agradecimento_avaliacao');
        });
    }

    public function down(): void
    {
        Schema::table('assistente', function (Blueprint $table) {
            $table->dropColumn('despedida');
        });
    }
};
