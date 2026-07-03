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
                            {--plan= : JSON con sucursales/rangos (solo esas consultas al bridge)}
                            {--cache-sufijo=desc40legacy : Sufijo directorio cache cuando se usa --plan}
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
        $planPath = trim((string) ($this->option('plan') ?? ''));
        $sufijoCache = trim((string) ($this->option('cache-sufijo') ?? ''));
        $rangosPorSucursal = null;

        if ($planPath !== '') {
            $plan = $this->resolverRangosDesdePlan($planPath);
            $rangosPorSucursal = $plan['rangos'];
            if ($fechaDesde === '' && ($plan['fecha_desde'] ?? '') !== '') {
                $fechaDesde = (string) $plan['fecha_desde'];
            }
            if ($fechaHasta === '' && ($plan['fecha_hasta'] ?? '') !== '') {
                $fechaHasta = (string) $plan['fecha_hasta'];
            }
            if ($sufijoCache === '') {
                $sufijoCache = 'desc40legacy';
            }
        }

        try {
            $manifest = $cacheSupport->descargar(
                $empresaId,
                $fechaDesde,
                $fechaHasta,
                $forzar,
                $rangosPorSucursal,
                $sufijoCache,
            );
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

        $this->line('Modo: '.($manifest['modo'] ?? '—'));
        if (($manifest['cache_sufijo'] ?? null) !== null) {
            $this->line('Sufijo cache: '.$manifest['cache_sufijo']);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{fecha_desde:?string,fecha_hasta:?string,rangos:array<int,array{min:int,max:int}>}
     */
    private function resolverRangosDesdePlan(string $planPath): array
    {
        if (! is_file($planPath)) {
            throw new \InvalidArgumentException('Plan inexistente: '.$planPath);
        }

        $decoded = json_decode((string) file_get_contents($planPath), true);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Plan JSON inválido: '.$planPath);
        }

        /** @var array<int, list<array{min:int,max:int}>> $rangos */
        $rangos = [];
        $items = $decoded['sucursales'] ?? $decoded['lotes'] ?? [];
        if (! is_array($items)) {
            throw new \InvalidArgumentException('El plan no contiene sucursales/lotes.');
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sucursal = (int) ($item['sucursal'] ?? 0);
            if ($sucursal <= 0) {
                continue;
            }

            $rangosItem = $item['rangos'] ?? [[
                'desde' => (int) ($item['desde'] ?? $item['nro_min'] ?? 0),
                'hasta' => (int) ($item['hasta'] ?? $item['nro_max'] ?? 0),
            ]];

            foreach ($rangosItem as $rango) {
                if (! is_array($rango)) {
                    continue;
                }
                $min = (int) ($rango['desde'] ?? $rango['min'] ?? 0);
                $max = (int) ($rango['hasta'] ?? $rango['max'] ?? $min);
                if ($min <= 0 || $max < $min) {
                    continue;
                }
                $rangos[$sucursal][] = ['min' => $min, 'max' => $max];
            }
        }

        if ($rangos === []) {
            throw new \InvalidArgumentException('El plan no contiene rangos válidos.');
        }

        return [
            'fecha_desde' => isset($decoded['fecha_desde']) ? trim((string) $decoded['fecha_desde']) : null,
            'fecha_hasta' => isset($decoded['fecha_hasta']) ? trim((string) $decoded['fecha_hasta']) : null,
            'rangos' => $rangos,
        ];
    }
}
