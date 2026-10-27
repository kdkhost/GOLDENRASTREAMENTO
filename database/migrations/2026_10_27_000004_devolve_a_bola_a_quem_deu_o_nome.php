<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A palavra que devolve a bola a quem acabou de dar o nome.
 *
 * A máquina pergunta o nome, a pessoa responde "Maria Aparecida" — e até aqui essa resposta era
 * tratada como pergunta: ia para a IA, que sem chave devolvia "não sei, chamei a equipe". Quem se
 * apresentou era atendido como se tivesse feito um questionamento. A fala de reconhecimento é
 * fixa, dita pelo código, e por isso existe uma linha própria na ficha: em branco, vale o padrão
 * de `config('assistente.persona.confirmacao_nome')`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistente', function (Blueprint $table) {
            $table->text('confirmacao_nome')->nullable()->after('pergunta_nome');
        });
    }

    public function down(): void
    {
        Schema::table('assistente', function (Blueprint $table) {
            $table->dropColumn('confirmacao_nome');
        });
    }
};
