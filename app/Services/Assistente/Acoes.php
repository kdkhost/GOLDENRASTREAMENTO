<?php

namespace App\Services\Assistente;

use App\Models\Conversa;
use App\Services\Assistente\Contratos\Acao;

/**
 * O registro das ações que o assistente sabe executar.
 *
 * É a única lista que o atendimento consulta: uma ação nova é escrever a classe e acrescentar a
 * linha em `catalogo()` — `Atendedor`, a bolha e a tela de conversas continuam sem saber o nome
 * dela. O preço de ser lista é a ordem, e ela importa: a primeira que reconhecer a fala é a que
 * abre, então gatilho que casa com mais de uma ação se resolve aqui, uma vez, e não em três telas.
 *
 * `emCurso()` é também a lixeira: código que saiu do catálogo e interruptor que a casa desligou no
 * meio de uma conversa fazem a ação morrer na hora, com o estado apagado da conversa. Sem esta
 * linha, uma conferência começada com a segunda via ligada continuaria andando depois que alguém
 * a desligasse na tela de chaves — e interruptor que só vale para a próxima conversa não é
 * interruptor.
 */
final class Acoes
{
    /**
     * As ações conhecidas, na ordem em que o gatilho é testado.
     *
     * @return array<string, Acao>
     */
    public static function catalogo(): array
    {
        return [
            SegundaViaDaFatura::CODIGO => new SegundaViaDaFatura,
        ];
    }

    /** A ação por código — a que a conversa traz em `conversas.acao`, ou nenhuma. */
    public static function porCodigo(?string $codigo): ?Acao
    {
        return blank($codigo) ? null : (self::catalogo()[$codigo] ?? null);
    }

    /**
     * A ação em curso nesta conversa, ou `null` quando ela volta a ser só conversa.
     */
    public static function emCurso(Conversa $conversa): ?Acao
    {
        $acao = self::porCodigo($conversa->acao);

        if ($acao === null || ! $acao->ligada()) {
            if (filled($conversa->acao)) {
                $conversa->encerrarAcao();
            }

            return null;
        }

        return $acao;
    }

    /** A ação que esta fala pede, entre as ligadas. Não abre nada: quem acha, abre. */
    public static function pedidaPela(string $fala): ?Acao
    {
        foreach (self::catalogo() as $acao) {
            if ($acao->ligada() && $acao->gatilho($fala)) {
                return $acao;
            }
        }

        return null;
    }

    /** O que a tela da conversa mostra no lugar do código. Sem ação, vazio. */
    public static function rotulo(?string $codigo): string
    {
        return (string) (self::porCodigo($codigo)?->rotulo() ?? '');
    }

    /**
     * A linha da ação em curso, do jeito que a equipe lê: o que a máquina faz, até onde ela já foi
     * e quando a conferência vence. Os dados que a pessoa informou não entram aqui — a tela vê o
     * estado da máquina, nunca as respostas dela.
     */
    public static function descrever(Conversa $conversa): string
    {
        $acao = self::porCodigo($conversa->acao);

        if ($acao === null) {
            return '';
        }

        $gastos = (int) $conversa->acao_tentativas;
        $teto = Verificacao::tentativas();
        $vence = $conversa->acao_em?->addMinutes(Verificacao::minutos());

        return collect([
            $acao->rotulo(),
            $acao->rotuloDoPasso($conversa->acao_passo),
            $gastos > 0 ? $gastos.' de '.$teto.' erros' : null,
            $vence !== null ? 'vence '.$vence->format('d/m/Y H:i') : null,
        ])->filter()->implode(' · ');
    }
}
