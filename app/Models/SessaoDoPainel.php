<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma janela aberta no painel, por pessoa.
 *
 * A sessão em si continua onde sempre esteve (arquivo, no servidor); esta linha é o índice que
 * permite a uma pessoa ver quantos aparelhos estão logados em nome dela e fechar um por um. Sem
 * índice não há lista, e sem lista "encerrar o notebook do escritório" é conselho de suporte.
 */
class SessaoDoPainel extends Model
{
    protected $table = 'sessoes_do_painel';

    protected $fillable = ['user_id', 'sessao', 'carimbo', 'ip', 'agente', 'ultimo_visto_em'];

    protected $casts = ['ultimo_visto_em' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** O nome que a tela mostra: primeiro o navegador, depois o sistema, e o resto é detalhe. */
    public function dispositivo(): string
    {
        $agente = (string) $this->agente;

        if ($agente === '') {
            return 'Aparelho não identificado';
        }

        $navegador = match (true) {
            (bool) preg_match('/Edg\/[\d.]+/i', $agente) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $agente) => 'Opera',
            (bool) preg_match('/Firefox/i', $agente) => 'Firefox',
            (bool) preg_match('/Chrome|CriOS/i', $agente) => 'Chrome',
            (bool) preg_match('/Safari/i', $agente) => 'Safari',
            default => 'Navegador',
        };

        $sistema = match (true) {
            (bool) preg_match('/Windows/i', $agente) => 'Windows',
            (bool) preg_match('/Android/i', $agente) => 'Android',
            (bool) preg_match('/iPhone|iPad|iOS/i', $agente) => 'iOS',
            (bool) preg_match('/Mac OS X/i', $agente) => 'macOS',
            (bool) preg_match('/Linux/i', $agente) => 'Linux',
            default => '',
        };

        return trim($navegador.' '.$sistema) !== '' ? trim($navegador.' '.$sistema) : $navegador;
    }

    /** A impressão digital do par rede+aparelho: o que distingue "deste celular" de "qualquer um". */
    public static function carimbo(string $ip, string $agente): string
    {
        return sha1($ip.'|'.$agente);
    }

    /**
     * "há 3 minutos" — a última batida desta janela, em linguagem de gente.
     *
     * A tela de Segurança precisa desta frase, e nenhuma view deveria montá-la sozinha: é o número
     * que diz se aquela janela ainda está em uso ou é uma aba esquecida num computador do estoque.
     */
    public function vistasHa(): string
    {
        return $this->ultimo_visto_em === null
            ? 'ainda sem sinal'
            : $this->ultimo_visto_em->diffForHumans();
    }
}
