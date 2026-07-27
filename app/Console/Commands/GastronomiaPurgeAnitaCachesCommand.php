<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Elimina carpetas antiguas de storage/app/anita_audit_cache y anita_import_cache.
 */
class GastronomiaPurgeAnitaCachesCommand extends Command
{
    protected $signature = 'gastronomia:purge-anita-caches
                            {--dias= : Días de retención (default config)}
                            {--dry-run : Solo listar, no borrar}
                            {--solo-audit : Solo anita_audit_cache}
                            {--solo-import : Solo anita_import_cache}';

    protected $description = 'Purga caches Anita en storage (audit + import) más viejas que N días';

    public function handle(): int
    {
        $dias = (int) ($this->option('dias') ?: config('gastronomia.anita_storage_cache_purge.dias', 7));
        $dias = max(1, $dias);
        $dryRun = (bool) $this->option('dry-run');
        $soloAudit = (bool) $this->option('solo-audit');
        $soloImport = (bool) $this->option('solo-import');
        $corte = Carbon::now()->subDays($dias);

        $dirs = [];
        if (! $soloImport) {
            $dirs['anita_audit_cache'] = storage_path('app/anita_audit_cache');
        }
        if (! $soloAudit) {
            $importRel = trim((string) config('gastronomia_anita_import.cache_directorio', 'anita_import_cache'));
            $dirs[$importRel !== '' ? $importRel : 'anita_import_cache'] = storage_path(
                'app/'.($importRel !== '' ? $importRel : 'anita_import_cache')
            );
        }

        $eliminadas = 0;
        $bytes = 0;

        foreach ($dirs as $nombre => $base) {
            if (! is_dir($base)) {
                $this->warn("No existe {$nombre}: {$base}");

                continue;
            }

            foreach (File::directories($base) as $dir) {
                $mtime = Carbon::createFromTimestamp((int) filemtime($dir));
                if ($mtime->gte($corte)) {
                    continue;
                }

                $size = $this->tamañoDirectorio($dir);
                $rel = basename($dir);
                if ($dryRun) {
                    $this->line("[dry-run] {$nombre}/{$rel} ({$this->humano($size)}, mtime {$mtime->toDateTimeString()})");
                } else {
                    File::deleteDirectory($dir);
                    $this->line("Eliminado {$nombre}/{$rel} ({$this->humano($size)})");
                }
                $eliminadas++;
                $bytes += $size;
            }
        }

        $accion = $dryRun ? 'dry-run' : 'purga';
        $msg = sprintf(
            'gastronomia:purge-anita-caches %s: %d carpeta(s), %s, retención %d días (corte mtime < %s)',
            $accion,
            $eliminadas,
            $this->humano($bytes),
            $dias,
            $corte->toDateTimeString()
        );
        $this->info($msg);
        Log::info('gastronomia.purge_anita_caches', [
            'accion' => $accion,
            'eliminadas' => $eliminadas,
            'bytes' => $bytes,
            'dias' => $dias,
            'corte' => $corte->toDateTimeString(),
        ]);

        return self::SUCCESS;
    }

    private function tamañoDirectorio(string $dir): int
    {
        $total = 0;
        if (! is_dir($dir)) {
            return 0;
        }
        foreach (File::allFiles($dir) as $file) {
            $total += (int) $file->getSize();
        }

        return $total;
    }

    private function humano(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }
        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1073741824, 2).' GB';
    }
}
