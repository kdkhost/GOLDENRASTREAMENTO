<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Fluxo comercial completo da loja: o pedido passa a carregar documento do comprador,
 * modalidade de entrega e endereço detalhado, ganha um token público (o cliente abre o
 * status pela URL sem expor o número sequencial) e cada mudança de status fica registrada
 * em order_histories, que alimenta a linha do tempo da página de confirmação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('token', 40)->nullable()->after('numero');
            $table->string('tipo_pessoa', 8)->nullable()->after('telefone');  // fisica | juridica
            $table->string('documento', 20)->nullable()->after('tipo_pessoa'); // CPF/CNPJ só dígitos
            $table->string('entrega', 12)->default('entrega')->after('documento'); // retira | entrega
            $table->string('bairro', 96)->nullable()->after('endereco');
            $table->string('complemento', 120)->nullable()->after('bairro');
            $table->boolean('consent_given')->default(false)->after('notas');
            $table->index('token');
        });

        Schema::create('order_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('status', 16);
            $table->string('observacao')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('sku', 64)->nullable()->after('nome');
        });

        // Pedidos antigos entram na linha do tempo com o status que já tinham.
        DB::table('orders')->orderBy('id')->chunkById(200, function ($pedidos) {
            $agora = now();
            $linhas = [];

            foreach ($pedidos as $pedido) {
                $linhas[] = [
                    'order_id' => $pedido->id,
                    'status' => $pedido->status,
                    'created_at' => $pedido->created_at ?: $agora,
                    'updated_at' => $agora,
                ];

                if ($pedido->token === null) {
                    DB::table('orders')->where('id', $pedido->id)->update(['token' => Str::random(32)]);
                }
            }

            if ($linhas !== []) {
                DB::table('order_histories')->insert($linhas);
            }
        });

        // "em andamento" vira "em separação", a etapa comercial entre pago e enviado.
        DB::table('orders')->where('status', 'em_andamento')->update(['status' => 'separando']);
        DB::table('order_histories')->where('status', 'em_andamento')->update(['status' => 'separando']);
    }

    public function down(): void
    {
        DB::table('orders')->where('status', 'separando')->update(['status' => 'em_andamento']);

        Schema::dropIfExists('order_histories');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('sku');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['token', 'tipo_pessoa', 'documento', 'entrega', 'bairro', 'complemento', 'consent_given']);
        });
    }
};
