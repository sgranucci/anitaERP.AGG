<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Contable\AsientoAnitaMetadatosBackfillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class BackfillAsientoAnitaMetadatosCommand extends Command
{
    protected $signature = 'contable:backfill-asiento-anita-metadatos
                            {--desde=2025-01-01 : Fecha inicial Y-m-d}
                            {--hasta= : Fecha final; default cutoff ERP configurado}
                            {--empresas=1,2,3 : Códigos Anita de empresa}
                            {--meses-bloque=1 : Meses por lectura masiva del bridge}
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste los metadatos en asiento}';

    protected $description = 'Materializa comprobante y emisor Anita en asientos históricos; dry-run por defecto';

    public function handle(AsientoAnitaMetadatosBackfillService $service): int
    {
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        if ($hasta === '') {
            $hasta = trim((string) config('contable.mayor_plano_cuenta.fuente_erp_hasta', ''));
        }
        $empresas = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('empresas')),
        )));
        $mesesBloque = max(1, (int) $this->option('meses-bloque'));
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');

        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Rango %s → %s | empresas %s | bloque %d mes(es) | %s',
            $desde,
            $hasta,
            implode(',', $empresas),
            $mesesBloque,
            $dryRun ? 'DRY-RUN' : 'EJECUTAR',
        ));
        $this->line('Lectura masiva: ctamov confirma origen; subdiario/subhist aportan emisor.');

        try {
            $resultado = $service->ejecutar(
                $desde,
                $hasta,
                $empresas,
                $mesesBloque,
                ! $dryRun,
                fn (string $mensaje) => $this->line($mensaje),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Concepto', 'Valor'], [
            ['Asientos revisados', (string) $resultado['asientos_revisados']],
            ['Confirmados contra Anita', (string) $resultado['asientos_anita_confirmados']],
            [$dryRun ? 'A actualizar' : 'Actualizados', (string) $resultado['asientos_actualizados']],
            ['Con emisor', (string) $resultado['emisores_persistidos']],
            ['Sin match bridge', (string) $resultado['asientos_sin_match_bridge']],
            ['No parseables', (string) $resultado['asientos_no_parseables']],
            ['ctamov leídas', (string) $resultado['ctamov_filas']],
            ['subdiario leídas', (string) $resultado['subdiario_filas']],
            ['subhist leídas', (string) $resultado['subhist_filas']],
            ['Errores bridge', (string) count($resultado['errores'])],
        ]);

        foreach (array_slice($resultado['errores'], 0, 20) as $error) {
            $this->error((string) $error);
        }
        if ($resultado['muestra_sin_match'] !== []) {
            $this->warn('Asientos parseables sin correspondencia Anita (muestra en el JSON).');
        }

        $reportePath = storage_path(sprintf(
            'logs/asiento_anita_metadatos_%s_%s_%s.json',
            $dryRun ? 'dryrun' : 'exec',
            str_replace('-', '', $desde),
            str_replace('-', '', $hasta),
        ));
        File::put($reportePath, json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('Reporte JSON: '.$reportePath);

        return $resultado['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
