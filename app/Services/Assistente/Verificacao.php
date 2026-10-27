<?php

namespace App\Services\Assistente;

use App\Models\Customer;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * A conferência de segurança da ficha: o que o assistente pode perguntar e como se confere.
 *
 * Existe uma razão única para esta classe: o assistente do site atende gente que não fez login.
 * Antes de mandar a segunda via da fatura para um e-mail, o sistema precisa saber que aquela
 * pessoa é dona da conta — e a única prova disponível é o que já está gravado na ficha. Então a
 * pergunta, a comparação e o teto de tentativas mora tudo aqui, num lugar só, para que nenhuma
 * tela, nenhum prompt e nenhum provedor de IA possa afrouxar a régua por conta própria.
 *
 * Três decisões que não mudam em lugar nenhum:
 *
 * **A resposta nunca é devolvida.** Quem errou a placa ouve "não bateu com o cadastro", nunca
 * "a placa certa é ABC1D23". Uma conferência que corrige é uma consulta que vaza.
 *
 * **Só entra na conta o que a ficha tem.** O painel escolhe os campos em
 * *Configurações → Chaves de integração → Assistente*; um campo escolhido que está vazio na ficha
 * não é perguntado — é motivo para a máquina não poder atender aquilo, não para adivinhar.
 *
 * **Comparar é normalizar antes.** Ninguém digita CPF, data e placa do mesmo jeito duas vezes, e a
 * régua de cada campo está escrita uma única vez aqui em cima, no `CAMPOS`.
 */
final class Verificacao
{
    /**
     * Os campos que a casa pode exigir, com a pergunta que a pessoa lê e o jeito de conferir.
     *
     * A ordem desta lista é a ordem das perguntas na conversa. Os códigos são o que o painel
     * guarda (config `assistente.verificacao.campos`) e o que viaja em `conversas.acao_passo`,
     * por isso são palavras fixas, não rótulos: trocar o rótulo na tela não pode mover a coluna
     * nem a regra de ninguém.
     */
    public const CAMPOS = [
        'documento' => [
            'rotulo' => 'CPF ou CNPJ',
            'pergunta' => 'Me informe o CPF ou CNPJ que está no cadastro da sua conta.',
            'jeito' => 'digitos',
        ],
        'nascimento' => [
            'rotulo' => 'data de nascimento',
            'pergunta' => 'Agora, sua data de nascimento, no formato dia/mês/ano.',
            'jeito' => 'data',
        ],
        'placas' => [
            'rotulo' => 'placa do veículo',
            'pergunta' => 'Qual é a placa de um dos veículos rastreados no seu cadastro?',
            'jeito' => 'placa',
        ],
        'telefone' => [
            'rotulo' => 'telefone',
            'pergunta' => 'Confirme o telefone com DDD que está no seu cadastro.',
            'jeito' => 'telefone',
        ],
        'cep' => [
            'rotulo' => 'CEP',
            'pergunta' => 'Para finalizar, me diga o CEP do endereço cadastrado.',
            'jeito' => 'cep',
        ],
    ];

    /** Os códigos que o painel oferece no seletor de campos. */
    public static function opcoes(): array
    {
        return collect(self::CAMPOS)
            ->map(fn (array $campo): string => $campo['rotulo'])
            ->all();
    }

    /**
     * Os campos exigidos agora, do jeito que o painel escolheu e o registro conhece.
     *
     * Um nome escrito à mão no `.env` que não existe em `CAMPOS` é descartado aqui, e não mais
     * adiante: é esta linha que garante que a conversa nunca fica esperando a resposta de uma
     * pergunta que ninguém sabe fazer.
     *
     * @return array<int, string>
     */
    public static function escolhidos(): array
    {
        $valor = config('assistente.verificacao.campos');
        $itens = is_array($valor) ? $valor : explode(',', (string) $valor);

        return array_values(array_unique(array_filter(
            array_map(fn ($campo): string => Str::lower(trim((string) $campo)), $itens),
            fn (string $campo): bool => isset(self::CAMPOS[$campo])
        )));
    }

    /**
     * Dos campos escolhidos, os que esta ficha realmente tem — e na ordem da conversa.
     *
     * @return array<int, string>
     */
    public static function exigiveis(Customer $ficha): array
    {
        return array_values(array_filter(
            self::escolhidos(),
            fn (string $campo): bool => self::tem($ficha, $campo)
        ));
    }

    /** A ficha tem valor neste campo? */
    public static function tem(Customer $ficha, string $campo): bool
    {
        return filled($ficha->{$campo} ?? null);
    }

    /** Quantos acertos a casa exige para liberar o envio. */
    public static function minimo(): int
    {
        return max(1, (int) config('assistente.verificacao.minimo', 2));
    }

    /** Quantas respostas erradas a máquina ainda aceita antes de chamar a equipe. */
    public static function tentativas(): int
    {
        return max(1, (int) config('assistente.verificacao.tentativas', 3));
    }

    /** Por quantos minutos a conferência fica de pé. Venceu, recomeça. */
    public static function minutos(): int
    {
        return max(1, (int) config('assistente.verificacao.minutos', 20));
    }

    /** O rótulo em português de um campo — o que a tela do painel mostra. */
    public static function rotulo(string $campo): string
    {
        return (string) (self::CAMPOS[$campo]['rotulo'] ?? $campo);
    }

    /** A pergunta que o cliente lê. É texto da casa, não do provedor: ninguém reescreve daqui. */
    public static function pergunta(string $campo): string
    {
        return (string) (self::CAMPOS[$campo]['pergunta'] ?? 'Confirme o dado do seu cadastro.');
    }

    /**
     * A resposta bate com o que está na ficha?
     *
     * Comparação sempre normalizada e sempre contra a coluna do banco — nunca contra um valor que
     * alguém informou por outro caminho. Campo desconhecido ou ficha sem valor é `false`: a régua
     * não tem "dúvida", e uma dúvida não libera envio.
     */
    public static function confere(Customer $ficha, string $campo, string $resposta): bool
    {
        $jeito = self::CAMPOS[$campo]['jeito'] ?? null;
        $guardado = $ficha->{$campo} ?? null;

        if ($jeito === null || blank($guardado) || blank($resposta)) {
            return false;
        }

        return match ($jeito) {
            'digitos', 'cep' => self::digitos($resposta) !== '' && self::digitos($resposta) === self::digitos((string) $guardado),
            'data' => self::data($resposta) !== null && self::data($resposta) === self::dataGuardada($guardado),
            'placa' => self::placa($resposta) !== '' && in_array(self::placa($resposta), $ficha->placasListadas(), true),
            'telefone' => self::telefoneBate($resposta, (string) $guardado),
            default => false,
        };
    }

    /** Só os números, com ou sem máscara. */
    private static function digitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    /** Placa no mesmo formato do `placasListadas()` da ficha: maiúscula, sem espaço nem traço. */
    private static function placa(string $valor): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $valor) ?? '');
    }

    /** A data que está na ficha, no `Y-m-d` — ela já vem de coluna `date`, não de digitação. */
    private static function dataGuardada(mixed $valor): ?string
    {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        return blank($valor) ? null : self::data((string) $valor);
    }

    /**
     * A data que a pessoa escreveu, no `Y-m-d` da ficha, ou `null` quando não é uma data.
     *
     * A barra é desmontada antes de qualquer parser porque o brasileiro só conhece dia/mês/ano, e
     * `Carbon::parse('27/10/1990')` não entende nada enquanto `Carbon::parse('10/27/1990')` lê 27
     * de outubro: deixá-la nas mãos do parser da framework faria a conferência trocar mês por dia
     * exatamente nas datas que passam de 12. Além da barra, cabem o traço, o espaço, os oito
     * dígitos seguidos (o jeito de quem digita no celular) e o ISO de quem copia do cadastro.
     */
    private static function data(string $valor): ?string
    {
        $texto = trim($valor);

        if ($texto === '') {
            return null;
        }

        if (preg_match('#^(\d{1,2})[/\-. ](\d{1,2})[/\-. ](\d{2,4})$#', $texto, $parte)) {
            return self::montarData((int) $parte[1], (int) $parte[2], (int) $parte[3]);
        }

        $digitos = self::digitos($texto);

        if (strlen($digitos) === 8) {
            return self::montarData((int) substr($digitos, 0, 2), (int) substr($digitos, 2, 2), (int) substr($digitos, 4, 4));
        }

        if (strlen($digitos) === 6) {
            return self::montarData((int) substr($digitos, 0, 2), (int) substr($digitos, 2, 2), (int) substr($digitos, 4, 2));
        }

        try {
            return self::aceitar(Carbon::parse($texto));
        } catch (Throwable) {
            return null;
        }
    }

    /** Dia, mês e ano escritos à mão viram data — ou nada, se o dia não existe. */
    private static function montarData(int $dia, int $mes, int $ano): ?string
    {
        if ($ano < 100) {
            $ano += $ano > (int) now()->format('y') ? 1900 : 2000;
        }

        if (! checkdate($mes, $dia, $ano)) {
            return null;
        }

        return self::aceitar(Carbon::create($ano, $mes, $dia));
    }

    /** Data válida é a que existe no calendário e no passado: ninguém nasce amanhã. */
    private static function aceitar(Carbon $data): ?string
    {
        return $data->year >= 1900 && ! $data->greaterThan(now()) ? $data->format('Y-m-d') : null;
    }

    /**
     * Telefone com ou sem DDD, com ou sem o 55 do país.
     *
     * Os últimos oito dígitos são a única comparação que sobrevive a "11 91234-5678" contra
     * "+55 (11) 91234-5678". Oito é o tamanho de um número fixo com DDD, então o piso também não
     * aceita um dígito isolado qualquer bater com o fim de um CNPJ.
     */
    private static function telefoneBate(string $resposta, string $guardado): bool
    {
        $resposta = self::digitos($resposta);
        $guardado = self::digitos($guardado);

        if (strlen($resposta) < 8 || strlen($guardado) < 8) {
            return false;
        }

        return str_ends_with($guardado, $resposta) || str_ends_with($resposta, $guardado);
    }
}
