<?php

namespace App\Services\Assistente;

use App\Models\Conversa;
use App\Models\ConversaMensagem;
use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Quem está do outro lado do chat — nome e e-mail, tirados do que a pessoa diz.
 *
 * O nome já vivia em `Nome`; esta classe é o resto da identificação, e existe por um motivo
 * comercial e um de segurança. O comercial: atendimento personalizado é o que olha a conta da
 * pessoa, e uma máquina que abre a segunda via da fatura sem saber de quem é a conta está pedindo
 * para entregar dado de um cliente a outro. O de segurança: com o e-mail dito antes, a ação já
 * começa com a ficha localizada e só precisa conferir os dados sensíveis — sem ele ela pergunta,
 * confere e envia, e nada aqui afrouxa aquela conferência.
 *
 * Duas decisões que não mudam em lugar nenhum:
 *
 * **Nada é trancado para sempre.** O pedido tem teto (`assistente.contato.perguntas_max`), igual ao
 * do nome. Passado o teto, a conversa segue: o assunto genérico continua sendo respondido e o
 * personalizado vai para a equipe em vez de virar formulário infinito.
 *
 * **O e-mail dito aqui nunca é destino.** Ele serve para *achar* a ficha; o envio é sempre para a
 * coluna do banco. Um endereço digitado no chat que virasse destino de fatura é a consulta que vaza.
 */
final class Identificacao
{
    /** O endereço escrito no chat, sem o resto da frase. */
    private const ENDERECO = '/(?<![\w.%+\-])[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/';

    /**
     * As palavras que só servem para anunciar o e-mail — e por isso não são assunto.
     *
     * É o que sobra de "meu e-mail é maria@exemplo.com" depois de tirar o endereço: palavra de
     * apresentação, não pergunta. "quero", "segunda" e "via" não estão aqui, e é por isso que quem
     * pede a fatura na mesma frase é atendido na mesma frase. Comparadas sem acento, porque quem
     * digita no celular não acentua.
     */
    private const APRESENTACOES = [
        'meu', 'minha', 'o', 'a', 'e', 'é', 'email', 'mail', 'este', 'esse', 'esta', 'essa',
        'oi', 'ola', 'alo', 'sim', 'segue', 'conforme', 'do', 'da', 'de', 'para', 'pra', 'que',
        'me', 'sou', 'nome', 'cadastro', 'contato', 'certo', 'ok', 'ta', 'obg', 'obrigado',
        'obrigada', 'grato', 'grata', 'valeu',
    ];

    /** Pedir o e-mail é regra da casa, e a casa liga ou desliga na tela de chaves. */
    public static function pedir(): bool
    {
        return (bool) config('assistente.contato.pedir');
    }

    /** Quantas vezes a máquina ainda pede antes de seguir sem o endereço. */
    public static function perguntasMax(): int
    {
        return max(1, (int) config('assistente.contato.perguntas_max', 3));
    }

    /**
     * A tranca do atendimento personalizado: desligada na tela de chaves, a ação abre com o que a
     * conversa tiver. A conferência de segurança da ficha não depende desta chave e continua
     * valendo do mesmo jeito — o que ela libera é a pergunta a mais, nunca o envio.
     */
    public static function exigir(): bool
    {
        return (bool) config('assistente.contato.exigir_para_personalizado');
    }

    /**
     * O que a casa resolveu pedir foi dito: é o piso de um atendimento que olha a conta da pessoa.
     */
    public static function pronta(Conversa $conversa): bool
    {
        return self::faltam($conversa) === [];
    }

    /**
     * Grava o e-mail na conversa e, achando o cadastro, amarra a conversa à ficha do cliente.
     *
     * A busca é por `Customer::porEmail()`, que compara o endereço de contato e o de acesso ao
     * portal sem diferenciar maiúsculas. Quando o cadastro aparece, a conversa ganha `customer_id`
     * — é a ponte que a equipe lê na caixa de entrada — e o nome/telefone que a ficha ainda não
     * tinha são completados dali. O que a pessoa já disse nunca é sobrescrito.
     *
     * O achado deixa uma fala de sistema no fio, do mesmo jeito que o cadastro do formulário deixa:
     * sem ela, a equipe abre a conversa e não sabe por que a ficha do cliente está ali. Devolve o
     * cliente para quem precisa escolher a frase de confirmação — ou nulo quando o endereço ainda
     * não é de ninguém.
     */
    public static function vincular(Conversa $conversa, string $email): ?Customer
    {
        $normalizado = Str::lower(trim($email));

        if ($normalizado === '') {
            return null;
        }

        $cliente = Customer::porEmail($normalizado);
        $dados = ['email' => $normalizado];

        if ($cliente !== null) {
            $dados['customer_id'] = $cliente->id;

            if (blank($conversa->nome)) {
                $dados['nome'] = $cliente->nome;
            }

            if (blank($conversa->contato)) {
                $dados['contato'] = (string) ($cliente->telefone ?: $cliente->emailDeAcesso());
            }
        }

        $conversa->update($dados);

        if ($cliente !== null && ! $conversa->mensagens()->where('autor', 'sistema')->where('corpo', 'like', 'Cadastro localizado%')->exists()) {
            ConversaMensagem::create([
                'conversa_id' => $conversa->id,
                'autor' => 'sistema',
                'tipo' => 'texto',
                'corpo' => 'Cadastro localizado pelo e-mail: cliente #'.$cliente->id.' — '.$cliente->nome.'.',
            ]);
        }

        return $cliente;
    }

    /**
     * O que ainda falta, na ordem em que se pede: o nome primeiro, porque é assim que a casa chama
     * as pessoas, e o e-mail depois, porque é ele que localiza a conta.
     *
     * Só falta o que a casa resolveu pedir. Desligado o interruptor do nome na tela de chaves, a
     * conversa segue sem nome e a tranca continua valendo para o que restou — exigência que a casa
     * desligou não pode voltar pela porta dos fundos de uma ação.
     *
     * @return array<int, string>
     */
    public static function faltam(Conversa $conversa): array
    {
        $faltam = [];

        if (Nome::pedir() && blank($conversa->nome)) {
            $faltam[] = 'nome';
        }

        if (self::pedir() && blank($conversa->email)) {
            $faltam[] = 'email';
        }

        return $faltam;
    }

    /**
     * O e-mail dito nesta fala, ou `null`.
     *
     * Vale o endereço isolado e o que veio colado numa frase ("meu email é maria@exemplo.com, quero
     * a via"), sempre aparado do ponto que fecha a frase. O espaço em volta do `@` é descolado antes
     * de procurar, porque "maria @ exemplo.com" é o jeito de quem digita no celular. O endereço da
     * própria casa é descartado: quem cola o e-mail do suporte no chat não está se identificando,
     * está repetindo o que leu no site — gravá-lo como identificação da pessoa faria a máquina
     * atender um cadastro que não é dela.
     */
    public static function extrair(string $fala): ?string
    {
        if (preg_match_all(self::ENDERECO, self::descole($fala), $partes) < 1) {
            return null;
        }

        foreach ($partes[0] as $achado) {
            $email = Str::lower(trim((string) $achado, " \t\n\r\0\x0B.,;:!?-"));

            if (self::serve($email)) {
                return $email;
            }
        }

        return null;
    }

    /**
     * A fala é só o e-mail, sem assunto pendurado?
     *
     * É a mesma pergunta do nome, e pelo mesmo motivo: "maria@exemplo.com" sozinho não é pergunta, é
     * resposta à pergunta da casa. Mandá-lo à IA custaria uma chamada por conversa e, sem chave
     * ligada, escalaria quem apenas informou o próprio endereço.
     */
    public static function soOEmail(string $fala): bool
    {
        if (self::extrair($fala) === null) {
            return false;
        }

        $semEndereco = (string) preg_replace(self::ENDERECO, ' ', self::descole($fala));
        $palavras = preg_split('/\s+/u', trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $semEndereco)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_diff(array_map(self::esqueleto(...), $palavras), self::apresentacoes()) === [];
    }

    /**
     * É um endereço de gente, e não da casa?
     *
     * O teto de tamanho é o teto da coluna, e `filter_var` entra depois da regex porque a regex é
     * larga de propósito: ela precisa achar o endereço no meio da frase, e quem decide se ele é um
     * endereço é o validador.
     */
    private static function serve(string $email): bool
    {
        if ($email === '' || mb_strlen($email) > 180 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        return ! in_array($email, self::enderecosDaCasa(), true);
    }

    /** Os endereços que a casa publica — no site, no rodapé, na tela de contato. */
    private static function enderecosDaCasa(): array
    {
        $guardado = Str::lower(trim((string) Setting::get('contato_email', '')));

        return array_values(array_unique(array_filter(
            [$guardado, Str::lower((string) config('golden.email'))],
            fn (string $email): bool => $email !== ''
        )));
    }

    /** As apresentações já no alfabeto comparável. */
    private static function apresentacoes(): array
    {
        return array_map(self::esqueleto(...), self::APRESENTACOES);
    }

    /** Os espaços em volta do `@`, que o celular insiste em colocar, saem antes de procurar. */
    private static function descole(string $fala): string
    {
        return (string) preg_replace('/\s*@\s*/u', '@', $fala);
    }

    /** A palavra sem acento nem pontuação — é assim que a lista compara. */
    private static function esqueleto(string $valor): string
    {
        $semAcento = (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);

        return Str::lower(preg_replace('/[^A-Za-z]/', '', $semAcento) ?? '');
    }
}
