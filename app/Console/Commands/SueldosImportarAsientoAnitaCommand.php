<?php

namespace App\Console\Commands;

use App\Services\Sueldos\SueldosAsientoAnitaImportService;
use Illuminate\Console\Command;

/**
 * Migra el asiento de sueldos de Anita (asimae/asicon/asicta) al mapeo ERP.
 * Dry-run por defecto.
 */
class SueldosImportarAsientoAnitaCommand extends Command
{
    protected $signature = 'sueldos:importar-asiento-anita
                            {--empresas=1,2,3 : IDs de empresa ERP, separados por coma}
                            {--reemplazar : Pisa imputaciones y patas fijas ya cargadas}
                            {--dry-run : Solo analiza, no persiste}
                            {--ejecutar : Persiste el mapeo}';

    protected $description = 'Importa asimae/asicon/asicta de Anita a imputación contable de conceptos (dry-run por defecto)';

    public function handle(SueldosAsientoAnitaImportService $service): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = (bool) $this->option('dry-run') || ! $ejecutar;
        if ($ejecutar && $this->option('dry-run')) {
            $this->error('No combine --dry-run con --ejecutar.');

            return self::FAILURE;
        }

        $empresas = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('empresas'))
        ), fn (int $id) => $id > 0));

        try {
            $plan = $dryRun
                ? $service->analizar($empresas, (bool) $this->option('reemplazar'))
                : $service->ejecutar($empresas, (bool) $this->option('reemplazar'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry-run: no se persistió nada.' : 'Importación persistida.');
        foreach ($plan['cabeceras'] as $cab) {
            $this->line(sprintf('  Anita asiento %d: %s', $cab['nro'], $cab['titulo']));
        }
        $this->line('Empresas: '.implode(', ', $plan['empresa_ids']));

        $this->table(
            ['Imputaciones', 'Crear', 'Actualizar', 'Igual', 'Omitir'],
            [[
                count($plan['imputaciones']),
                $plan['conteo']['crear'] ?? 0,
                $plan['conteo']['actualizar'] ?? 0,
                $plan['conteo']['igual'] ?? 0,
                $plan['conteo']['omitir'] ?? 0,
            ]]
        );
        $this->table(
            ['Cuentas automáticas', 'Crear', 'Actualizar', 'Igual', 'Omitir'],
            [[
                count($plan['automaticas']),
                $plan['conteo_automaticas']['crear'] ?? 0,
                $plan['conteo_automaticas']['actualizar'] ?? 0,
                $plan['conteo_automaticas']['igual'] ?? 0,
                $plan['conteo_automaticas']['omitir'] ?? 0,
            ]]
        );

        $mapeos = [];
        foreach ($plan['mapeos'] as $m) {
            $mapeos[] = [
                $m['codigo'],
                $m['origen_asiento'],
                $m['cuenta_debe'] ?: '',
                $m['cuenta_haber'] ?: '',
                $m['resta_de'] ?: '',
            ];
        }
        $this->table(['Concepto', 'Asiento Anita', 'Debe', 'Haber', 'Resta de'], $mapeos);

        foreach ($plan['errores'] as $error) {
            $this->warn($error);
        }

        if ($dryRun) {
            $this->comment('Para persistir: php artisan sueldos:importar-asiento-anita --empresas='.implode(',', $empresas).' --ejecutar');
        }

        return $plan['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
