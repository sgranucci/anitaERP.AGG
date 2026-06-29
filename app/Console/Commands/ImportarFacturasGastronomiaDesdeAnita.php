<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Ventas\Gastronomia\GastronomiaFacturaImportacionAnitaService;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportCacheSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class ImportarFacturasGastronomiaDesdeAnita extends Command
{
    protected $signature = 'gastronomia:importar-facturas-anita
                            {--sucursal= : Código sucursal Anita (3 u 8)}
                            {--desde= : Número desde (inclusive)}
                            {--hasta= : Número hasta (inclusive)}
                            {--empresa= : empresa_id (default config gastronomia_anita_import)}
                            {--usuario= : usuario_id para altas}
                            {--identificador-pc= : Override PC gastronomía}
                            {--dry-run : Solo simular}
                            {--lote : Importar PV3 1270698-1270801 y PV8 807697-807829}
                            {--fecha-desde= : Jornada inicial para cache local (Y-m-d)}
                            {--fecha-hasta= : Jornada final cache (opcional)}
                            {--forzar-cache : Re-descarga cache aunque exista}
                            {--sin-cache : Lee bridge por comprobante}';

    protected $description = 'Importa facturas FAC B desde Informix (venta, ítems, resvta/cobranza) para cerrar turnos en anitaERP';

    public function handle(
        GastronomiaFacturaImportacionAnitaService $importacion,
        GastronomiaAnitaImportCacheSupport $cacheSupport,
    ): int {
        $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido para importación.');

            return self::FAILURE;
        }

        $empresaId = (int) ($this->option('empresa') ?: config('gastronomia_anita_import.empresa_id', 1));
        $dryRun = (bool) $this->option('dry-run');

        Config::set(
            'gastronomia.genera_contabilidad_al_cobrar',
            (bool) config('gastronomia_anita_import.genera_contabilidad_cobranza', false),
        );

        $lotes = $this->option('lote')
            ? [
                ['sucursal' => 3, 'desde' => 1270698, 'hasta' => 1270801],
                ['sucursal' => 8, 'desde' => 807697, 'hasta' => 807829],
            ]
            : [[
                'sucursal' => (int) $this->option('sucursal'),
                'desde' => (int) $this->option('desde'),
                'hasta' => (int) ($this->option('hasta') ?: $this->option('desde')),
            ]];

        if ($lotes[0]['sucursal'] <= 0 || $lotes[0]['desde'] <= 0) {
            $this->error('Indique --lote o --sucursal, --desde y --hasta.');

            return self::FAILURE;
        }

        $pcOverride = $this->option('identificador-pc');
        $pcOverride = is_string($pcOverride) && trim($pcOverride) !== '' ? trim($pcOverride) : null;

        $fechaCacheDesde = trim((string) ($this->option('fecha-desde') ?? ''));
        $fechaCacheHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        $sinCache = (bool) $this->option('sin-cache');
        $forzarCache = (bool) $this->option('forzar-cache');
        $usarCache = ! $sinCache
            && (bool) config('gastronomia_anita_import.usar_cache_local', true)
            && $fechaCacheDesde !== '';

        if ($usarCache) {
            $fechaCacheHasta = $fechaCacheHasta !== '' ? $fechaCacheHasta : $fechaCacheDesde;
            try {
                $manifest = $cacheSupport->descargar($empresaId, $fechaCacheDesde, $fechaCacheHasta, $forzarCache);
                $importacion->setCacheReader($cacheSupport->crearReader($empresaId, $fechaCacheDesde, $fechaCacheHasta));
                $this->comment(sprintf(
                    'Cache local: %s (%d consultas bridge, %d cabeceras venta)',
                    $manifest['directorio'] ?? '—',
                    (int) ($manifest['consultas_bridge'] ?? 0),
                    (int) ($manifest['counts']['venta'] ?? 0),
                ));
            } catch (\Throwable $e) {
                $this->error('Cache import: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $totalImportados = 0;
        $totalOmitidos = 0;
        $todosErrores = [];

        foreach ($lotes as $lote) {
            $suc = (int) $lote['sucursal'];
            $desde = (int) $lote['desde'];
            $hasta = (int) $lote['hasta'];
            $pc = $pcOverride ?? (string) (config('gastronomia_anita_import.identificador_pc_por_sucursal')[$suc] ?? '');

            $this->info("FAC B {$suc} {$desde}..{$hasta} · empresa {$empresaId} · PC {$pc}".($dryRun ? ' [dry-run]' : ''));

            try {
                $ret = $importacion->importarRango($suc, $desde, $hasta, $empresaId, $usuarioId, $dryRun, $pcOverride);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $totalImportados += $ret['importados'];
            $totalOmitidos += $ret['omitidos'];
            $todosErrores = array_merge($todosErrores, $ret['errores']);

            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Importados', (string) $ret['importados']],
                    ['Omitidos (ya en ERP)', (string) $ret['omitidos']],
                    ['Errores', (string) count($ret['errores'])],
                ],
            );

            foreach ($ret['errores'] as $err) {
                $this->warn($err);
            }
        }

        $this->newLine();
        $this->info("Total importados: {$totalImportados}; omitidos: {$totalOmitidos}; errores: ".count($todosErrores));

        if ($usarCache) {
            $importacion->setCacheReader(null);
        }

        return $todosErrores === [] ? self::SUCCESS : self::FAILURE;
    }
}
