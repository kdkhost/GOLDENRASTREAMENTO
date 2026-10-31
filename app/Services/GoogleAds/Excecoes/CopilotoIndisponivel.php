<?php

namespace App\Services\GoogleAds\Excecoes;

use App\Services\Assistente\Provedores\Fabrica;
use App\Support\ChavesDeIntegracao;
use RuntimeException;
use Throwable;

/**
 * O copiloto não pôde responder: falta IA pronta, a chamada falhou ou a resposta veio fora do
 * formato.
 *
 * Todas as mensagens são a frase que a tela mostra. O detalhe técnico do provedor já está no
 * log da própria chamada (`assistente_api_logs`); repeti-lo aqui em inglês só faria a pessoa
 * ler um HTTP 429 sem saber o que fazer.
 */
final class CopilotoIndisponivel extends RuntimeException
{
    /** Nenhuma IA de conversa está pronta para atender. */
    public static function semModelo(): self
    {
        $faltando = Fabrica::faltando();
        $detalhe = $faltando[0] ?? 'A capacidade de conversa não está configurada.';

        return new self(
            'O copiloto ainda não tem uma IA pronta para responder. '.$detalhe
            .' Ajuste em '.ChavesDeIntegracao::CAMINHO_NO_PAINEL.'.'
        );
    }

    /** A chamada saiu e voltou com erro — cota, tempo ou rede. Nada foi gravado. */
    public static function chamadaFalhou(?Throwable $causa = null): self
    {
        return new self(
            'O copiloto não conseguiu responder agora: a chamada à IA falhou. '
            .'Nada foi gravado — tente de novo em instantes.',
            0,
            $causa,
        );
    }

    /** A IA respondeu texto, mas não o JSON que o painel sabe ler, nem depois da segunda tentativa. */
    public static function respostaInvalida(string $tipo): self
    {
        return new self(
            "A IA respondeu, mas fora do formato que o painel espera: o {$tipo} não veio em JSON. "
            .'Nada foi gravado — gere de novo; se repetir, ajuste as instruções da ficha do copiloto.'
        );
    }
}
