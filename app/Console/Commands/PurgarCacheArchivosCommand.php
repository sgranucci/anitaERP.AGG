<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Borra las entradas vencidas del cache de archivos.
 *
 * El store 'file' de Laravel no tiene recolector de basura: al leer una entrada vencida la
 * borra, pero si nadie la vuelve a pedir queda en disco para siempre. Los reportes contables
 * (SumasSaldosController, MayorConceptoController) guardan packs de cientos de MB con TTL de
 * 2 a 4 horas, así que el directorio crece sin techo. Medido el 2026-09-20: 2,1 GB en 4.400
 * archivos, el más viejo de marzo.
 *
 * Corre como www-data desde el scheduler, que es quien es dueño de los subdirectorios. Si se
 * ejecuta a mano con otro usuario no va a poder borrar: por eso informa los rechazos en vez
 * de fallar en silencio.
 */
class PurgarCacheArchivosCommand extends Command
{
    protected $signature = 'cache-archivos:purge
                            {--dry-run : Solo informa cuánto borraría, sin borrar}
                            {--horas= : Antigüedad mínima en horas (default config)}';

    protected $description = 'Borra las entradas vencidas del cache de archivos (el store file no tiene GC)';

    public function handle(): int
    {
        $ruta = (string) config('cache.stores.file.path');

        if ($ruta === '' || ! is_dir($ruta)) {
            $this->warn('No hay directorio de cache de archivos en: '.$ruta);

            return self::SUCCESS;
        }

        $simulacion = (bool) $this->option('dry-run');
        $horas = max(1, (int) ($this->option('horas') ?: config('cache_archivos_purga.horas', 24)));
        $limite = now()->subHours($horas)->getTimestamp();

        $borrados = 0;
        $bytes = 0;
        $rechazados = 0;

        foreach ($this->archivos($ruta) as $archivo) {
            if ($archivo->getFilename() === '.gitignore' || $archivo->getMTime() >= $limite) {
                continue;
            }

            $tamano = $archivo->getSize();

            if ($simulacion) {
                $borrados++;
                $bytes += $tamano;

                continue;
            }

            // @unlink: el warning de permisos se reporta agregado más abajo.
            if (@unlink($archivo->getPathname())) {
                $borrados++;
                $bytes += $tamano;
            } else {
                $rechazados++;
            }
        }

        if (! $simulacion) {
            $this->borrarDirectoriosVacios($ruta);
        }

        $this->info(sprintf(
            '%s %s archivos (%s MB) con más de %d h.',
            $simulacion ? 'Simulado:' : 'Borrados:',
            number_format($borrados),
            number_format($bytes / 1048576, 1),
            $horas
        ));

        if ($rechazados > 0) {
            $this->error(sprintf(
                'No se pudieron borrar %s archivos por permisos. Correr como www-data, '
                .'que es el dueño de los subdirectorios de %s.',
                number_format($rechazados),
                $ruta
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
     * Los subdirectorios del store son hashes (data/b1/9d/...): al vaciarse no sirven para
     * nada. Se preserva el directorio raíz del store.
     */
    private function borrarDirectoriosVacios(string $raiz): void
    {
        $dirs = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($dirs as $dir) {
            if (! $dir->isDir()) {
                continue;
            }
            // Si quedó algo adentro (por ejemplo un .gitignore), rmdir falla y se sigue.
            @rmdir($dir->getPathname());
        }
    }
}
