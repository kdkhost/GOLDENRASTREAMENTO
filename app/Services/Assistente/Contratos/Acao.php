<?php

namespace App\Services\Assistente\Contratos;

use App\Models\Conversa;

/**
 * Uma ação que o assistente *executa* no meio da conversa — não uma resposta a mais: um feito.
 *
 * A interface existe por uma razão só: o atendimento tem de poder crescer (contrato, comprovante,
 * reemissão de acesso) sem que `Atendedor` passe a conhecer o financeiro, a ficha ou o SMTP de cada
 * um. Quem atende gente pergunta "há ação em curso? qual? e agora?" e obedece; o que cada ação faz
 * com o resto do sistema mora nela.
 *
 * Três regras que toda ação daqui tem de obedecer, e que é por isso que o estado dela vive em
 * `conversas` e não na cabeça de quem atende:
 *
 * **Determinística.** Quem decide o passo é o estado gravado, nunca a IA. Uma decisão de envio não
 * pode depender do que um modelo entendeu da frase.
 *
 * **A fala é dela enquanto durar.** Entre a pergunta e a resposta, a próxima fala do cliente
 * pertence à ação — desviar aquilo para o `Motor` faria a máquina responder "não entendi" no meio
 * de uma conferência.
 *
 * **Nada do que a pessoa informa volta para a tela.** A ação confirma, corrige e agradece; não
 * repete dado de cadastro, não diz qual resposta estava certa.
 */
interface Acao
{
    /** O código gravado em `conversas.acao`. Palavra fixa, não rótulo: a tela traduz. */
    public function codigo(): string;

    /** O que a equipe lê na conversa. */
    public function rotulo(): string;

    /** O passo em curso, em português: a tela de atendimento mostra, o cliente não vê. */
    public function rotuloDoPasso(?string $passo): string;

    /** O interruptor da casa. Desligada, nem o gatilho existe — nada é consultado. */
    public function ligada(): bool;

    /** A fala de agora está pedindo esta ação? */
    public function gatilho(string $fala): bool;

    /** Abre a ação na conversa e devolve a primeira pergunta — o que o cliente lê. */
    public function abrir(Conversa $conversa): string;

    /** Responde ao passo em curso e devolve a fala para o cliente. */
    public function atender(Conversa $conversa, string $fala): string;
}
