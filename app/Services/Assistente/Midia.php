<?php

namespace App\Services\Assistente;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * As regras dos arquivos que entram e saem do chat.
 *
 * O administrador decide no painel o que se pode mandar (áudio, imagem, documento) e até onde;
 * esta classe é onde essa decisão vira checagem, e é onde o megabyte pensado por gente vira o
 * byte que o PHP compara. Fazer isso em dois lugares — o validador do upload e a frase "até 4 MB"
 * da tela — é como se perde um limite escrito à mão.
 *
 * O arquivo fica no disco `public` porque o cliente e a equipe precisam abri-lo pelo navegador
 * depois; a pasta é a de `assistente`, e o nome sai do original, limpo e com sufixo, para duas
 * notas fiscais chamadas `nota.pdf` não se apagarem.
 */
final class Midia
{
    /** Os tipos que o chat entende, cada um com suas chaves de config. */
    private const TIPOS = [
        'audio' => ['permissao' => 'audio_entrada', 'mb' => 'audio_mb_max', 'extensoes' => 'audio_extensoes'],
        'imagem' => ['permissao' => 'imagem', 'mb' => 'imagem_mb_max', 'extensoes' => 'imagem_extensoes'],
        'arquivo' => ['permissao' => 'documento', 'mb' => 'documento_mb_max', 'extensoes' => 'documento_extensoes'],
    ];

    public static function permitido(string $tipo): bool
    {
        $chaves = self::TIPOS[$tipo] ?? null;

        return $chaves !== null && (bool) config('assistente.permissoes.'.$chaves['permissao']);
    }

    /** O limite da casa para o tipo, em bytes. */
    public static function bytesDe(string $tipo): int
    {
        return self::bytes((float) config('assistente.midia.'.self::TIPOS[$tipo]['mb']));
    }

    /** Megabytes em bytes, escrito uma vez: é a unidade que a tela mostra e a que se compara. */
    public static function bytes(float $megabytes): int
    {
        return (int) round($megabytes * 1024 * 1024);
    }

    /** O teto do áudio que a IA fala. Não é tipo de chat: é a resposta, e tem limite próprio. */
    public static function bytesDaRespostaEmAudio(): int
    {
        return self::bytes((float) config('assistente.midia.resposta_audio_mb_max'));
    }

    public static function limiteEmMb(string $tipo): float
    {
        return (float) config('assistente.midia.'.self::TIPOS[$tipo]['mb']);
    }

    /** @return array<int, string> */
    public static function extensoesDe(string $tipo): array
    {
        return (array) config('assistente.midia.'.self::TIPOS[$tipo]['extensoes']);
    }

    /**
     * Por que este arquivo não entra, ou nulo quando entra. Devolve a frase pronta para o toastr
     * porque a regra é uma só e o cliente não precisa adivinhar qual dos três tetos estourou.
     */
    public static function recusa(UploadedFile $arquivo, string $tipo): ?string
    {
        $nome = self::rotuloParaCliente($tipo);

        if (! self::permitido($tipo)) {
            return 'O envio de '.$nome.' não está disponível neste atendimento.';
        }

        $extensao = strtolower((string) $arquivo->getClientOriginalExtension());

        if (! in_array($extensao, self::extensoesDe($tipo), true)) {
            return ucfirst($nome).' só nos formatos '.implode(', ', self::extensoesDe($tipo)).'.';
        }

        $limite = self::bytesDe($tipo);

        if ($arquivo->getSize() > $limite) {
            return ucfirst($nome).' até '.self::limiteEmMb($tipo).' MB.';
        }

        return null;
    }

    /** Guarda no disco público e devolve o caminho relativo que vai na coluna `midia`. */
    public static function guardar(UploadedFile $arquivo, string $tipo): string
    {
        $nome = preg_replace('/[^a-z0-9.-]+/i', '-', pathinfo($arquivo->getClientOriginalName(), PATHINFO_FILENAME));
        $nome = mb_substr((string) $nome, 0, 60).'-'.now()->format('ymdHis').'-'.strtolower(mb_substr(bin2hex(random_bytes(4)), 0, 6));

        return $arquivo->storeAs(self::pasta(), $nome.'.'.$arquivo->getClientOriginalExtension(), 'public');
    }

    /** Os bytes do assistente, na pasta do ano e do mês para o disco não virar uma pilha só. */
    public static function pasta(): string
    {
        return trim((string) config('assistente.midia.pasta'), '/').'/'.now()->format('Y/m');
    }

    /** O caminho onde o áudio que a IA falou deve ser gravado, com a extensão do seu tipo. */
    public static function caminhoParaAudio(string $mime): string
    {
        return self::pasta().'/resposta-'.now()->format('ymdHis').'-'.strtolower(mb_substr(bin2hex(random_bytes(4)), 0, 6))
            .'.'.match (true) {
                str_contains($mime, 'mpeg') => 'mp3',
                str_contains($mime, 'wav') => 'wav',
                default => 'audio',
            };
    }

    /**
     * O texto de um documento que já é texto — e nulo para o que só uma IA consegue ler.
     *
     * O Gemini recebe arquivo em `inlineData`, mas não lê `text/plain` nem `text/csv`: a chamada
     * chega com o anexo e o modelo responde como se nada tivesse sido enviado, completando o que
     * falta com conhecimento geral — num contrato, isso vira cláusula inventada na frente de quem
     * mandou o arquivo. `.txt` e `.csv` não precisam de modelo para virar texto, e o `.docx`
     * guarda o seu num XML dentro do pacote; os dois são lidos aqui. PDF continua indo em
     * `inlineData`, que é o formato que o Gemini lê de verdade.
     */
    public static function textoDe(array $peca): ?string
    {
        $extensao = strtolower(pathinfo((string) ($peca['nome'] ?? $peca['caminho']), PATHINFO_EXTENSION));

        $texto = match ($extensao) {
            'txt', 'csv' => self::textoPuro($peca['caminho']),
            'docx' => self::textoDeDocx($peca['caminho']),
            default => null,
        };

        $texto = trim((string) $texto);

        if ($texto === '') {
            return null;
        }

        $limite = (int) config('assistente.resposta.documento_caracteres_max', 30000);

        if ($limite > 0 && mb_strlen($texto) > $limite) {
            $texto = mb_substr($texto, 0, $limite)."\n\n[documento maior que o limite de leitura da casa: o restante foi omitido]";
        }

        return $texto;
    }

    /** O arquivo como texto, sem inventar codificação: o que não é UTF-8 é lido como Windows-1252. */
    private static function textoPuro(string $caminho): ?string
    {
        $conteudo = (string) Storage::disk('public')->get($caminho);

        if ($conteudo === '') {
            return null;
        }

        $conteudo = (string) preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);

        return mb_check_encoding($conteudo, 'UTF-8')
            ? $conteudo
            : (string) mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
    }

    /**
     * O texto do `.docx`: o pacote é um zip e o conteúdo está em `word/document.xml`. Cada parágrafo
     * vira uma linha e cada célula um separador, porque sem isso a tabela do contrato chega ao
     * modelo como uma frase só.
     */
    private static function textoDeDocx(string $caminho): ?string
    {
        if (! class_exists(\ZipArchive::class)) {
            return null;
        }

        $zip = new \ZipArchive();
        $abriu = $zip->open(Storage::disk('public')->path($caminho));

        if ($abriu !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false || trim($xml) === '') {
            return null;
        }

        $xml = str_replace(['</w:p>', '</w:tr>', '</w:tc>'], ["\n", "\n", "\t"], $xml);
        $texto = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("/[ \t]+\n/u", "\n", $texto));
    }

    public static function rotuloDoTipo(string $tipo): string
    {
        return (string) (config('assistente.tipos_mensagem')[$tipo] ?? $tipo);
    }

    /**
     * O nome do tipo na fala com o cliente. O chat chama a fala de "Arquivo", mas quem está mandando
     * um PDF está mandando um documento — e é essa a palavra que a frase dele precisa usar.
     */
    public static function rotuloParaCliente(string $tipo): string
    {
        return match ($tipo) {
            'audio' => 'áudio',
            'imagem' => 'imagem',
            default => 'documento',
        };
    }

    /**
     * O tipo de atendimento de um arquivo enviado — o que a tela mostra e o teto que se aplica.
     *
     * O que não é imagem nem áudio é `arquivo`, e não `null`: um `.exe` enviado vira documento, a
     * lista de extensões o recusa, e o cliente lê o motivo. Devolver nulo seria um 500 onde
     * deveria haver uma frase.
     */
    public static function tipoDe(UploadedFile $arquivo): string
    {
        return match (true) {
            str_starts_with($arquivo->getMimeType(), 'image/') => 'imagem',
            str_starts_with($arquivo->getMimeType(), 'audio/') => 'audio',
            default => 'arquivo',
        };
    }
}
