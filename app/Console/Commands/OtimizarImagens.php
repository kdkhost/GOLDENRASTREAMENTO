<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OtimizarImagens extends Command
{
    protected $signature = 'imagens:otimizar {--forcar : Recria todas as versões WebP mesmo que já existam}';

    protected $description = 'Otimiza imagens do site e gera versões WebP comprimidas de alto desempenho';

    public function handle(): int
    {
        $this->info('Iniciando otimização de imagens do site e armazenamento...');

        $pastas = [
            public_path('img'),
            storage_path('app/public'),
        ];

        $totalOriginais = 0;
        $totalOtimizadas = 0;
        $bytesAntes = 0;
        $bytesDepois = 0;
        $forcar = (bool) $this->option('forcar');

        foreach ($pastas as $pasta) {
            if (! is_dir($pasta)) {
                continue;
            }

            $arquivos = File::allFiles($pasta);

            foreach ($arquivos as $arquivo) {
                $ext = strtolower($arquivo->getExtension());
                if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                    continue;
                }

                $caminho = $arquivo->getRealPath();
                $caminhoWebp = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $caminho);

                $pesoOriginal = filesize($caminho);
                $bytesAntes += $pesoOriginal;
                $totalOriginais++;

                $precisaGerar = $forcar || ! file_exists($caminhoWebp) || (filemtime($caminho) > filemtime($caminhoWebp));

                if ($precisaGerar) {
                    $sucesso = $this->converterParaWebp($caminho, $caminhoWebp, $ext);
                    if ($sucesso) {
                        $pesoWebp = filesize($caminhoWebp);
                        $bytesDepois += $pesoWebp;
                        $totalOtimizadas++;
                        $this->line(sprintf(' <fg=green>✓</> %s: %s → %s', $arquivo->getRelativePathname(), $this->formatarBytes($pesoOriginal), $this->formatarBytes($pesoWebp)));
                    } else {
                        $bytesDepois += $pesoOriginal;
                    }
                } else {
                    $bytesDepois += file_exists($caminhoWebp) ? filesize($caminhoWebp) : $pesoOriginal;
                }
            }
        }

        $economia = $bytesAntes > 0 ? max(0, $bytesAntes - $bytesDepois) : 0;
        $pct = $bytesAntes > 0 ? round(($economia / $bytesAntes) * 100, 1) : 0;

        $this->newLine();
        $this->info(sprintf('Processamento concluído: %d imagens analisadas, %d WebP geradas/atualizadas.', $totalOriginais, $totalOtimizadas));
        $this->info(sprintf('Peso original: %s | Peso final WebP: %s | Economia de banda: %s (%s%%)',
            $this->formatarBytes($bytesAntes),
            $this->formatarBytes($bytesDepois),
            $this->formatarBytes($economia),
            $pct
        ));

        return Command::SUCCESS;
    }

    private function converterParaWebp(string $origem, string $destino, string $ext): bool
    {
        if (! function_exists('imagewebp')) {
            return false;
        }

        try {
            $img = null;

            if ($ext === 'png') {
                $img = @imagecreatefrompng($origem);
                if ($img) {
                    imagepalettetotruecolor($img);
                    imagealphablending($img, true);
                    imagesavealpha($img, true);
                }
            } elseif (in_array($ext, ['jpg', 'jpeg'], true)) {
                $img = @imagecreatefromjpeg($origem);
            }

            if (! $img) {
                return false;
            }

            $resultado = imagewebp($img, $destino, 82);
            imagedestroy($img);

            return $resultado;
        } catch (\Throwable) {
            return false;
        }
    }

    private function formatarBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.').' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', '.').' KB';
        }

        return $bytes.' B';
    }
}
