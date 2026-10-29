<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O cadastro de quem trabalha no painel deixa de ser só "nome, e-mail e perfil".
 *
 * Cargo, setor, admissão e uma apresentação curta são o que a ficha precisa para ser lida por
 * gente — e o último acesso com o IP é o que a aba de Segurança mostra sem obrigar ninguém a
 * cruzar a auditoria à mão. A foto (`avatar`) já existia na tabela desde o começo; faltava o
 * campo que a envia e as telas que a desenham.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cargo', 80)->nullable()->after('telefone');
            $table->string('setor', 80)->nullable()->after('cargo');
            $table->string('bio', 500)->nullable()->after('avatar');
            $table->date('admissao')->nullable()->after('bio');
            $table->timestamp('ultimo_acesso_em')->nullable()->after('admissao');
            $table->string('ultimo_acesso_ip', 45)->nullable()->after('ultimo_acesso_em');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'cargo', 'setor', 'bio', 'admissao', 'ultimo_acesso_em', 'ultimo_acesso_ip',
            ]);
        });
    }
};
