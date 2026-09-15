<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Compras\ComprasIvaMaestrosAnitaImportService;
use Illuminate\Console\Command;

class ImportarComprasIvaMaestrosAnitaCommand extends Command
{
    protected $signature = 'compras:importar-iva-maestros-anita
        {--dry-run : Solo analiza, no persiste (default si no hay --ejecutar)}
        {--ejecutar : Persiste columnas, conceptos y tipos de compra}
        {--path= : Path Anita (default ANITA_BDD_PATH)}';

    protected $description = 'Importa de Anita colivacomp, conccomp y t_comp (+cont_comp) al ERP. Dry-run por defecto.';

    public function handle(ComprasIvaMaestrosAnitaImportService $service): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = (bool) $this->option('dry-run') || ! $ejecutar;

        if ($ejecutar && $this->option('dry-run')) {
            $this->error('No combine --dry-run con --ejecutar.');

            return self::FAILURE;
        }

        $path = $this->option('path');
        $path = is_string($path) && trim($path) !== '' ? trim($path) : null;

        $this->info($dryRun ? 'Dry-run: no se persiste nada.' : 'Ejecutando importación (Anita → ERP).');

        try {
            $ret = $dryRun ? $service->analizar($path) : $service->ejecutar($path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Path Anita: '.$ret['path']);
        foreach ($ret['diagnostico'] as $linea) {
            $this->comment('• '.$linea);
        }
        $this->newLine();

        $filas = [];
        foreach (['columna_ivacompra', 'concepto_ivacompra', 'tipotransaccion_compra'] as $clave) {
            $stats = $ret[$clave];
            $filas[] = [
                $clave,
                (string) ($stats['fuente'] ?? ''),
                (string) ($stats['en_anita'] ?? 0),
                (string) ($stats['crear'] ?? 0).($dryRun ? ' (sim)' : ''),
                (string) ($stats['actualizar'] ?? 0).($dryRun ? ' (sim)' : ''),
                (string) ($stats['omitidos'] ?? 0),
                (string) ($stats['vinculos_concepto'] ?? '-'),
            ];
        }

        $this->table(
            ['Maestro', 'Fuente', 'En Anita', 'Crear', 'Actualizar', 'Sin cambio', 'Vínculos concepto'],
            $filas
        );

        foreach (['columna_ivacompra', 'concepto_ivacompra', 'tipotransaccion_compra'] as $clave) {
            $muestra = $ret[$clave]['muestra'] ?? [];
            if (! is_array($muestra) || $muestra === []) {
                continue;
            }
            $this->newLine();
            $this->line("→ {$clave}:");
            foreach ($muestra as $item) {
                $this->line('    - '.$item);
            }
        }

        foreach ($ret['errores'] as $error) {
            $this->warn($error);
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Para persistir: php artisan compras:importar-iva-maestros-anita --ejecutar');
        }

        return $ret['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
