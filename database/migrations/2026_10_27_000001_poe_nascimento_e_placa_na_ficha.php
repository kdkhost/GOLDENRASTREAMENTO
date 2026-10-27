<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Os dois dados que a ficha não tinha e que agora são pergunta de segurança.
 *
 * O assistente passou a poder entregar a segunda via da fatura por e-mail, e para isso precisa
 * provar que quem pede é dono da conta. Documento, telefone, CEP e endereço já existiam; data de
 * nascimento e placa do veículo não existiam em lugar nenhum — e são justamente as duas respostas
 * que só quem tem o carro ou o contrato na mão sabe dar.
 *
 * `placas` é texto livre, separado por vírgula, porque a casa atende do motorista autônomo à frota
 * de dezoito veículos: uma linha por placa obrigaria a montar uma tabela de veículos para responder
 * uma única pergunta ("essa placa é sua?"). A lista vive na ficha, e `Customer::placasListadas()` é
 * o único caminho que lê aquilo — normalizando maiúscula e sem espaços, do jeito que se confere.
 *
 * `nascimento` é `date`, não string: "27/10/1990", "27-10-1990" e "1990-10-27" têm de dar na mesma
 * data, e é o tipo que garante isso sem parser inventado na view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->date('nascimento')->nullable()->after('documento');
            $table->string('placas', 190)->nullable()->after('nascimento');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['nascimento', 'placas']);
        });
    }
};
