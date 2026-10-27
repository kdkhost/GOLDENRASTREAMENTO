<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A única forma de escrever CSV na plataforma.
 *
 * O padrão é o do Excel em português: separador `;`, BOM UTF-8 e linha terminada em CRLF.
 * Com vírgula, o Excel pt-BR juntava cada linha numa célula só e o arquivo abria como uma
 * coluna ilegível — o "não tem coluna nenhuma" que a planilha mostrava.
 *
 * As colunas saem em ordem alfabética pelo rótulo: duas exportações do mesmo relatório se
 * comparam lado a lado e quem abre o arquivo não procura onde está o telefone.
 *
 * Quem usa declara `Rótulo => fn (registro) => valor` e recebe o download pronto. Texto é
 * aparado e tem suas quebras de linha viradas espaço (célula multilinha desalinha a
 * planilha inteira), aspas duplas são dobradas, e o que começa com `=`, `+`, `@`, `-`, tab
 * ou CR ganha apóstrofo — senão a planilha executa o que era para ser um dado.
 */
class Csv
{
    private const BOM = "\xEF\xBB\xBF";

    private const PERIGOSO = '/^[=+@\-\t\r]/u';

    /**
     * Baixa o arquivo: `$colunas` é o mapa rótulo => função que lê o valor do registro.
     *
     * @param  array<string, callable>  $colunas
     * @param  iterable<int, mixed>  $registros
     */
    public static function baixar(string $prefixo, array $colunas, iterable $registros): StreamedResponse
    {
        $ordenadas = self::ordenar($colunas);
        $arquivo = $prefixo.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($ordenadas, $registros) {
            $saida = fopen('php://output', 'w');

            fwrite($saida, self::BOM);
            fwrite($saida, self::linha(array_keys($ordenadas)));

            foreach ($registros as $registro) {
                $valores = array_map(
                    static fn (callable $pegar) => $pegar($registro),
                    array_values($ordenadas)
                );

                fwrite($saida, self::linha($valores));
            }

            fclose($saida);
        }, $arquivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Colunas em ordem alfabética pelo rótulo, sem deixar o acento atrapalhar a comparação. */
    public static function ordenar(array $colunas): array
    {
        $rotulos = array_keys($colunas);

        $chaves = array_map(
            static fn (string $rotulo): string => Str::of($rotulo)->ascii()->lower()->toString(),
            $rotulos
        );

        asort($chaves);

        $ordenadas = [];

        foreach (array_keys($chaves) as $posicao) {
            $ordenadas[$rotulos[$posicao]] = $colunas[$rotulos[$posicao]];
        }

        return $ordenadas;
    }

    /** Uma linha do arquivo: células entre aspas, separadas por ponto e vírgula, em CRLF. */
    public static function linha(array $celulas): string
    {
        return implode(';', array_map(self::celula(...), array_values($celulas)))."\r\n";
    }

    /** A célula já escapada e entre aspas. Números saem com vírgula decimal, como o Excel quer. */
    public static function celula(mixed $valor): string
    {
        $texto = match (true) {
            $valor === null => '',
            is_bool($valor) => $valor ? 'sim' : 'não',
            is_float($valor) => number_format($valor, 2, ',', ''),
            is_int($valor) => (string) $valor,
            default => self::texto((string) $valor),
        };

        return '"'.str_replace('"', '""', $texto).'"';
    }

    /** Texto de planilha: sem quebra de linha, sem espaço sobrando e sem executar na abertura. */
    public static function texto(?string $valor): string
    {
        $limpo = trim(preg_replace('/\s+/u', ' ', (string) $valor) ?? '');

        return preg_match(self::PERIGOSO, $limpo) ? "'".$limpo : $limpo;
    }

    /** Centavos como moeda brasileira: 1234567 vira "12.345,67". Vazio continua vazio. */
    public static function moeda(?int $centavos): string
    {
        return $centavos === null ? '' : number_format($centavos / 100, 2, ',', '.');
    }

    /** Data no formato que o Excel em português entende, e em uma linha só. */
    public static function data(\DateTimeInterface|string|null $momento, string $formato = 'd/m/Y H:i'): string
    {
        if (! $momento) {
            return '';
        }

        $data = $momento instanceof \DateTimeInterface
            ? Carbon::instance($momento)
            : Carbon::parse($momento);

        return $data->format($formato);
    }
}
