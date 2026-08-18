<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Contable\AsientoAnitaNroAsientoBackfillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class BackfillAsientoAnitaNroAsientoCommand extends Command
{
    protected $signature = 'contable:backfill-asiento-anita-nro-asiento
                            {--anio=2025 : Año completo a leer de subhist}
                            {--empresa=1 : Código de empresa Anita}
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste asiento.anita_nro_asiento}';

    protected $description = 'Completa el número de asiento resumen Anita con una única lectura anual de subhist';

    public function handle(AsientoAnitaNroAsientoBackfillService $service): int
    {
        $anio = (int) $this->option('anio');
        $empresa = (int) $this->option('empresa');
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');

        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Año %d | empresa Anita %d | una lectura anual de subhist | %s',
            $anio,
            $empresa,
            $dryRun ? 'DRY-RUN' : 'EJECUTAR',
        ));

        try {
            $resultado = $service->ejecutar($anio, $empresa, ! $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Concepto', 'Valor'], [
            ['Lecturas subhist', (string) $resultado['lecturas_subhist']],
            ['Filas subhist', (string) $resultado['subhist_filas']],
            ['Operaciones con N° resumen', (string) $resultado['operaciones_con_nro_asiento']],
            ['Operaciones con variantes', (string) count($resultado['operaciones_con_variantes'])],
            ['Operaciones ambiguas', (string) count($resultado['operaciones_ambiguas'])],
            ['Asientos ERP revisados', (string) $resultado['asientos_revisados']],
            ['Asientos con match', (string) $resultado['asientos_con_match']],
            ['Ya completos', (string) $resultado['asientos_ya_completos']],
            [$dryRun ? 'A actualizar' : 'Actualizados', (string) $resultado['asientos_actualizados']],
            ['Sin match', (string) $resultado['asientos_sin_match']],
        ]);

        $reportePath = storage_path(sprintf(
            'logs/asiento_anita_nro_asiento_%s_%d_emp%d.json',
            $dryRun ? 'dryrun' : 'exec',
            $anio,
            $empresa,
        ));
        File::put($reportePath, json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('Reporte JSON: '.$reportePath);

        return count($resultado['operaciones_ambiguas']) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
