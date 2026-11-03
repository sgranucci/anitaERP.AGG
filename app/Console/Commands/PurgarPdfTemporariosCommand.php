<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Borra copias viejas de storage/pdf. Esas carpetas se vuelven a escribir al
 * imprimir o exportar; no son el archivo del comprobante.
 *
 * El scheduler corre como www-data, dueño de varias carpetas (pagoproveedor).
 * Si se ejecuta con otro usuario, informa los rechazos en vez de fallar en silencio.
 */
class PurgarPdfTemporariosCommand extends Command
{
    protected $signature = 'pdf-temporarios:purge
                            {--dry-run : Solo informa cuánto borraría, sin borrar}
                            {--dias= : Antigüedad mínima en días (default config)}';

    protected $description = 'Borra PDF y exportaciones temporarias de storage/pdf más viejos que la retención';

    public function handle(): int
    {
        $raiz = storage_path('pdf');
        $raizReal = realpath($raiz);

        if ($raizReal === false || ! is_dir($raizReal)) {
            $this->warn('No hay directorio de PDF temporarios en: '.$raiz);

            return self::SUCCESS;
        }

        $simulacion = (bool) $this->option('dry-run');
        $dias = max(1, (int) ($this->option('dias') ?: config('pdf_temporarios.dias', 3)));
        $limite = now()->subDays($dias)->getTimestamp();
        $directorios = array_values(array_filter(array_map(
            'strval',
            (array) config('pdf_temporarios.directorios', [])
        )));

        if ($directorios === []) {
            $this->warn('pdf_temporarios.directorios está vacío. No se borra nada.');

            return self::SUCCESS;
        }

        $borrados = 0;
        $bytes = 0;
        $rechazados = 0;

        foreach ($directorios as $nombre) {
            $dir = $raizReal.DIRECTORY_SEPARATOR.$nombre;
            if (! is_dir($dir)) {
                continue;
            }

            foreach ($this->archivos($dir) as $archivo) {
                if ($archivo->getFilename() === '.gitignore' || $archivo->getMTime() >= $limite) {
                    continue;
                }

                $ruta = $archivo->getPathname();
                $real = realpath($ruta);
                if ($real === false || ! str_starts_with($real, $dir.DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $tamano = $archivo->getSize();

                if ($simulacion) {
                    $borrados++;
                    $bytes += $tamano;

                    continue;
                }

                if (@unlink($real)) {
                    $borrados++;
                    $bytes += $tamano;
                } else {
                    $rechazados++;
                }
            }

            if (! $simulacion) {
                $this->borrarDirectoriosVacios($dir);
            }
        }

        $this->info(sprintf(
            '%s %s archivos (%s MB) con más de %d días.',
            $simulacion ? 'Simulado:' : 'Borrados:',
            number_format($borrados),
            number_format($bytes / 1048576, 1),
            $dias
        ));

        if ($rechazados > 0) {
            $this->error(sprintf(
                'No se pudieron borrar %s archivos por permisos. El cron corre como www-data y los toma en la próxima pasada.',
                number_format($rechazados)
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function archivos(string $ruta): iterable
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
    }

    /**
     * Subcarpetas vacías (por ejemplo listados/sicore_liquidacion). La carpeta
     * de cada tipo se conserva: el próximo PDF la vuelve a usar.
     */
    private function borrarDirectoriosVacios(string $raiz): void
    {
        $dirs = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($dirs as $dir) {
            if (! $dir->isDir() || $dir->getPathname() === $raiz) {
                continue;
            }

            @rmdir($dir->getPathname());
        }
    }
}
