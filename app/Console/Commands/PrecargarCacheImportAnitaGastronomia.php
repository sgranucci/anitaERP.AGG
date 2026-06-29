<?php

namespace App\Console\Commands;

use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportCacheSupport;
use Illuminate\Console\Command;

class PrecargarCacheImportAnitaGastronomia extends Command
{
    protected $signature = 'gastronomia:precargar-cache-import-anita
                            {--fecha-desde= : Fecha jornada inicial Y-m-d}
                            {--fecha-hasta= : Fecha jornada final Y-m-d (opcional)}
                            {--empresa=1 : empresa_id}
                            {--forzar : Re-descarga aunque exista cache completa}';

    protected $description = 'Descarga venta/stkmov/vengrav/vencae/resvta a storage local para importación sin N lecturas al bridge';

    public function handle(GastronomiaAnitaImportCacheSupport $cacheSupport): int
    {
        $fechaDesde = trim((string) $this->option('fecha-desde'));
        if ($fechaDesde === '') {
            $this->error('Indique --fecha-desde=Y-m-d');

            return self::FAILURE;
        }

        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        $fechaHasta = $fechaHasta !== '' ? $fechaHasta : $fechaDesde;
        $empresaId = (int) $this->option('empresa');
        $forzar = (bool) $this->option('forzar');

        try {
            $manifest = $cacheSupport->descargar($empresaId, $fechaDesde, $fechaHasta, $forzar);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Cache import empresa %d | %s → %s',
            $empresaId,
            $manifest['fecha_desde'] ?? $fechaDesde,
            $manifest['fecha_hasta'] ?? $fechaHasta,
        ));
        $this->line('Directorio: '.($manifest['directorio'] ?? '—'));
        $this->line('Bridge: '.($manifest['bridge'] ?? '—').' ('.($manifest['ifx_server'] ?? '—').')');
        $this->line('Consultas bridge: '.(string) ($manifest['consultas_bridge'] ?? '—'));

        $counts = $manifest['counts'] ?? [];
        $this->table(['Tabla', 'Filas'], [
            ['venta', (string) ($counts['venta'] ?? 0)],
            ['stkmov', (string) ($counts['stkmov'] ?? 0)],
            ['vengrav', (string) ($counts['vengrav'] ?? 0)],
            ['vencae', (string) ($counts['vencae'] ?? 0)],
            ['resvta', (string) ($counts['resvta'] ?? 0)],
            ['sucursales', (string) ($counts['sucursales'] ?? 0)],
        ]);

        return self::SUCCESS;
    }
}
