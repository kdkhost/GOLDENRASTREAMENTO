<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Regras e execução dos campos de envio do painel (config/envios.php).
 *
 * O partial de upload e o endpoint consomem a mesma regra: a dica que o
 * operador lê dentro do campo — formatos, tamanho máximo, dimensão
 * recomendada — é o que o servidor valida, sem cópia à mão.
 */
class Envio
{
    /** Extensões aceitas → MIME real verificado no arquivo. */
    private const MIME = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'pdf' => 'application/pdf',
    ];

    /** @return array<string, array<string, mixed>> */
    public static function campos(): array
    {
        return config('envios', []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function regra(string $campo): array
    {
        $regra = config("envios.{$campo}");

        if (! is_array($regra)) {
            throw new RuntimeException("Campo de envio desconhecido: {$campo}.");
        }

        return $regra;
    }

    /** Valor do atributo accept do <input type=file>. */
    public static function accept(string $campo): string
    {
        return implode(',', self::mimesDeConteudo($campo));
    }

    /** MIME esperados no conteúdo do arquivo, derivados dos formatos do campo. */
    public static function mimesDeConteudo(string $campo): array
    {
        $mapa = array_map(
            fn (string $ext) => self::MIME[$ext] ?? null,
            self::regra($campo)['formatos']
        );

        if (in_array(null, $mapa, true)) {
            throw new RuntimeException("Um formato do campo {$campo} não tem MIME em Envio::\$MIME.");
        }

        return array_values(array_unique($mapa));
    }

    /** A linha de orientação do campo: formatos, limite e dimensão. */
    public static function dica(string $campo): string
    {
        $r = self::regra($campo);
        $partes = [
            implode(' / ', array_map(fn (string $e) => strtoupper($e), $r['formatos'])),
            'até '.self::textoTamanho($r['max_mb'] * 1024 * 1024),
        ];

        if (! empty($r['dimensao'])) {
            $partes[] = 'ideal '.$r['dimensao'].(! empty($r['proporcao']) ? " ({$r['proporcao']})" : '');
        }

        return implode(' · ', $partes);
    }

    public static function maxBytes(string $campo): int
    {
        return (int) (self::regra($campo)['max_mb'] * 1024 * 1024);
    }

    /**
     * Grava o arquivo na pasta da regra e devolve o descritor que o campo usa
     * para desenhar o preview e o rodapé "carregado em 0,8 s · 342 KB".
     *
     * O nome no disco parte do nome original (slug + sufixo aleatório), porque o
     * caminho é o que sobra depois: é por ele que o documento é reconhecido na
     * tela meses depois, e hash puro não diz nada.
     *
     * @return array<string, mixed>
     */
    public static function guardar(UploadedFile $arquivo, string $campo): array
    {
        $r = self::regra($campo);
        $extensao = self::extensaoLiberada($arquivo, $campo);
        $base = Str::slug(pathinfo($arquivo->getClientOriginalName(), PATHINFO_FILENAME));
        $base = $base === '' ? $r['tipo'] : Str::limit($base, 60, '');

        $caminho = $arquivo->storeAs(
            $r['pasta'],
            $base.'-'.Str::random(8).'.'.$extensao,
            'public'
        );

        return self::descritor($caminho, $campo, $arquivo->getClientOriginalName());
    }

    /**
     * A extensão que vai para o disco é a do conteúdo do arquivo, e ela precisa estar
     * entre os formatos que o campo aceita: um script PHP batizado de foto.jpg é
     * recusado em vez de virar arquivo executável dentro de public/storage.
     */
    private static function extensaoLiberada(UploadedFile $arquivo, string $campo): string
    {
        $formatos = self::regra($campo)['formatos'];
        $extensao = strtolower($arquivo->extension() ?: '');

        if (! in_array($extensao, $formatos, true)) {
            throw ValidationException::withMessages([
                'arquivo' => 'O conteúdo do arquivo não corresponde aos formatos aceitos: '
                    .implode(', ', array_map(fn (string $e) => strtoupper($e), $formatos)).'.',
            ]);
        }

        return $extensao;
    }

    /**
     * Descritor de um arquivo que já está no disco — serve tanto ao preview
     * recém-enviado quanto ao que a página recarrega depois de salvar.
     *
     * @return array<string, mixed>
     */
    public static function descritor(string $caminho, string $campo, ?string $nome = null): array
    {
        $r = self::regra($campo);
        $absoluto = Storage::disk('public')->path($caminho);
        $bytes = is_file($absoluto) ? (int) filesize($absoluto) : 0;
        $dimensoes = $r['tipo'] === 'imagem' && is_file($absoluto) ? @getimagesize($absoluto) : false;

        return [
            'campo' => $campo,
            'tipo' => $r['tipo'],
            'caminho' => $caminho,
            'url' => asset('storage/'.$caminho),
            'nome' => $nome ?: basename($caminho),
            'bytes' => $bytes,
            'bytes_texto' => self::textoTamanho($bytes),
            'extensao' => strtolower(pathinfo($caminho, PATHINFO_EXTENSION)),
            'largura' => $dimensoes ? $dimensoes[0] : null,
            'altura' => $dimensoes ? $dimensoes[1] : null,
        ];
    }

    /**
     * O caminho veio mesmo de um envio deste campo? Usado ao receber o valor do
     * formulário (que é texto) — barreiras: pasta certa, sem travessia, arquivo
     * existe no disco público.
     */
    public static function caminhoConfere(?string $caminho, string $campo): bool
    {
        $pasta = self::regra($campo)['pasta'];

        if ($caminho === null || $caminho === '' || ! Str::startsWith($caminho, $pasta.'/')) {
            return false;
        }

        if (str_contains($caminho, '..') || ! preg_match('#^[\w./-]+$#', $caminho)) {
            return false;
        }

        return Storage::disk('public')->exists($caminho);
    }

    /** Apaga um caminho de envio, se ele existir e pertencer àquele campo. */
    public static function remover(?string $caminho, string $campo): void
    {
        if (self::caminhoConfere($caminho, $campo)) {
            Storage::disk('public')->delete($caminho);
        }
    }

    public static function textoTamanho(int|float $bytes): string
    {
        if ($bytes >= 1048576) {
            return rtrim(rtrim(number_format($bytes / 1048576, 1, ',', ''), '0'), ',').' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024).' KB';
        }

        return round($bytes).' B';
    }
}
