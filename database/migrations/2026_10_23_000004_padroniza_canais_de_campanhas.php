<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * As campanhas passam a usar o mesmo vocabulário de canal do classificador de tráfego
     * (config('golden.canais')). 'pago' vira 'anuncio', 'organico' vira 'busca_organica'.
     */
    public function up(): void
    {
        $mapa = [
            'pago' => 'anuncio',
            'organico' => 'busca_organica',
            'direto' => 'direto',
            'referencia' => 'indicacao',
        ];

        foreach ($mapa as $antigo => $novo) {
            DB::table('campaigns')->where('channel', $antigo)->update(['channel' => $novo]);
        }

        DB::table('campaigns')
            ->whereNotIn('channel', ['anuncio', 'rede_social', 'busca_organica', 'busca_ia', 'indicacao', 'email', 'parceiro', 'direto', 'indexador', 'desconhecido'])
            ->update(['channel' => 'desconhecido']);
    }

    public function down(): void
    {
        $reverso = [
            'anuncio' => 'pago',
            'busca_organica' => 'organico',
            'busca_ia' => 'organico',
            'rede_social' => 'organico',
            'indicacao' => 'referencia',
            'parceiro' => 'referencia',
            'email' => 'referencia',
            'indexador' => 'direto',
            'desconhecido' => 'direto',
        ];

        foreach ($reverso as $novo => $antigo) {
            DB::table('campaigns')->where('channel', $novo)->update(['channel' => $antigo]);
        }
    }
};
