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
    private const CACHE_SUFIJO_PLAN = 'desc40legacy';

    protected $signature = 'gastronomia:importar-facturas-anita
                            {--sucursal= : Código sucursal Anita (3 u 8)}
                            {--desde= : Número desde (inclusive)}
                            {--hasta= : Número hasta (inclusive)}
                            {--empresa= : empresa_id (default config gastronomia_anita_import)}
                            {--usuario= : usuario_id para altas}
                            {--identificador-pc= : Override PC gastronomía (todos los lotes)}
                            {--dry-run : Solo simular}
                            {--lote : Importar PV3 1270698-1270801 y PV8 807697-807829}
                            {--plan= : JSON con lotes bulk (fecha_desde, fecha_hasta, lotes[{sucursal,desde,hasta,identificador_pc?}])}
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

        $planPath = trim((string) ($this->option('plan') ?? ''));
        $fechaCacheDesde = trim((string) ($this->option('fecha-desde') ?? ''));
        $fechaCacheHasta = trim((string) ($this->option('fecha-hasta') ?? ''));

        if ($planPath !== '') {
            $plan = $this->resolverPlanImportacion($planPath);
            $lotes = $plan['lotes'];
            if ($fechaCacheDesde === '' && ($plan['fecha_desde'] ?? '') !== '') {
                $fechaCacheDesde = (string) $plan['fecha_desde'];
            }
            if ($fechaCacheHasta === '' && ($plan['fecha_hasta'] ?? '') !== '') {
                $fechaCacheHasta = (string) $plan['fecha_hasta'];
            }
        } elseif ($this->option('lote')) {
            $lotes = [
                ['sucursal' => 3, 'desde' => 1270698, 'hasta' => 1270801],
                ['sucursal' => 8, 'desde' => 807697, 'hasta' => 807829],
            ];
        } else {
            $lotes = [[
                'sucursal' => (int) $this->option('sucursal'),
                'desde' => (int) $this->option('desde'),
                'hasta' => (int) ($this->option('hasta') ?: $this->option('desde')),
            ]];
        }

        if ($lotes === [] || $lotes[0]['sucursal'] <= 0 || $lotes[0]['desde'] <= 0) {
            $this->error('Indique --plan, --lote o --sucursal, --desde y --hasta.');

            return self::FAILURE;
        }

        $pcOverride = $this->option('identificador-pc');
        $pcOverride = is_string($pcOverride) && trim($pcOverride) !== '' ? trim($pcOverride) : null;
        $sinCache = (bool) $this->option('sin-cache');
        $forzarCache = (bool) $this->option('forzar-cache');
        $usarCache = ! $sinCache
            && (bool) config('gastronomia_anita_import.usar_cache_local', true)
            && $fechaCacheDesde !== '';

        $sufijoCache = '';
        $rangosCache = null;
        if ($planPath !== '') {
            $sufijoCache = self::CACHE_SUFIJO_PLAN;
            $rangosCache = $this->rangosPorSucursalDesdeLotes($lotes);
        }

        if ($usarCache) {
            $fechaCacheHasta = $fechaCacheHasta !== '' ? $fechaCacheHasta : $fechaCacheDesde;
            try {
                $manifest = $cacheSupport->descargar(
                    $empresaId,
                    $fechaCacheDesde,
                    $fechaCacheHasta,
                    $forzarCache,
                    $rangosCache,
                    $sufijoCache,
                );
                $importacion->setCacheReader($cacheSupport->crearReader(
                    $empresaId,
                    $fechaCacheDesde,
                    $fechaCacheHasta,
                    $sufijoCache,
                ));
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

        $this->info(sprintf(
            'Plan bulk: %d sucursal(es) · cache %s → %s%s',
            count($lotes),
            $fechaCacheDesde !== '' ? $fechaCacheDesde : '—',
            $fechaCacheHasta !== '' ? $fechaCacheHasta : ($fechaCacheDesde !== '' ? $fechaCacheDesde : '—'),
            $usarCache ? ' (local)' : ' (sin cache)',
        ));

        foreach ($lotes as $lote) {
            $suc = (int) $lote['sucursal'];
            $desde = (int) $lote['desde'];
            $hasta = (int) $lote['hasta'];
            $pcLote = isset($lote['identificador_pc']) && trim((string) $lote['identificador_pc']) !== ''
                ? trim((string) $lote['identificador_pc'])
                : null;
            $pcEfectivo = $pcLote ?? $pcOverride;
            $pc = $pcEfectivo ?? (string) (config('gastronomia_anita_import.identificador_pc_por_sucursal')[$suc] ?? '');

            $this->info("FAC B {$suc} {$desde}..{$hasta} · empresa {$empresaId} · PC {$pc}".($dryRun ? ' [dry-run]' : ''));

            try {
                $ret = $importacion->importarRango($suc, $desde, $hasta, $empresaId, $usuarioId, $dryRun, $pcEfectivo);
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

    /**
     * @return array{fecha_desde:?string,fecha_hasta:?string,lotes:list<array{sucursal:int,desde:int,hasta:int,identificador_pc?:string}>}
     */
    private function resolverPlanImportacion(string $planPath): array
    {
        if (! is_file($planPath)) {
            throw new \InvalidArgumentException('Plan inexistente: '.$planPath);
        }

        $raw = file_get_contents($planPath);
        if ($raw === false) {
            throw new \InvalidArgumentException('No se pudo leer el plan: '.$planPath);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Plan JSON inválido: '.$planPath);
        }

        $lotesRaw = $decoded['lotes'] ?? $decoded['sucursales'] ?? null;
        if (! is_array($lotesRaw) || $lotesRaw === []) {
            throw new \InvalidArgumentException('El plan debe incluir clave "lotes" con al menos un rango.');
        }

        $lotes = [];
        foreach ($lotesRaw as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (isset($item['rangos']) && is_array($item['rangos'])) {
                $sucursal = (int) ($item['sucursal'] ?? 0);
                $pc = isset($item['identificador_pc']) ? trim((string) $item['identificador_pc']) : null;
                foreach ($item['rangos'] as $rango) {
                    if (! is_array($rango)) {
                        continue;
                    }
                    $desde = (int) ($rango['desde'] ?? 0);
                    $hasta = (int) ($rango['hasta'] ?? $desde);
                    if ($sucursal <= 0 || $desde <= 0) {
                        continue;
                    }
                    $lote = ['sucursal' => $sucursal, 'desde' => $desde, 'hasta' => $hasta];
                    if ($pc !== null && $pc !== '') {
                        $lote['identificador_pc'] = $pc;
                    }
                    $lotes[] = $lote;
                }

                continue;
            }

            $sucursal = (int) ($item['sucursal'] ?? 0);
            $desde = (int) ($item['desde'] ?? $item['nro_min'] ?? 0);
            $hasta = (int) ($item['hasta'] ?? $item['nro_max'] ?? $desde);
            if ($sucursal <= 0 || $desde <= 0) {
                continue;
            }
            $lote = ['sucursal' => $sucursal, 'desde' => $desde, 'hasta' => $hasta];
            $pc = isset($item['identificador_pc']) ? trim((string) $item['identificador_pc']) : null;
            if ($pc !== null && $pc !== '') {
                $lote['identificador_pc'] = $pc;
            }
            $lotes[] = $lote;
        }

        if ($lotes === []) {
            throw new \InvalidArgumentException('El plan no contiene rangos válidos.');
        }

        return [
            'fecha_desde' => isset($decoded['fecha_desde']) ? trim((string) $decoded['fecha_desde']) : null,
            'fecha_hasta' => isset($decoded['fecha_hasta']) ? trim((string) $decoded['fecha_hasta']) : null,
            'lotes' => $lotes,
        ];
    }

    /**
     * @param  list<array{sucursal:int,desde:int,hasta:int}>  $lotes
     * @return array<int, array{min:int,max:int}>
     */
    private function rangosPorSucursalDesdeLotes(array $lotes): array
    {
        /** @var array<int, array{min:int,max:int}> $rangos */
        $rangos = [];
        foreach ($lotes as $lote) {
            $sucursal = (int) ($lote['sucursal'] ?? 0);
            $desde = (int) ($lote['desde'] ?? 0);
            $hasta = (int) ($lote['hasta'] ?? $desde);
            if ($sucursal <= 0 || $desde <= 0) {
                continue;
            }
            if (! isset($rangos[$sucursal])) {
                $rangos[$sucursal] = ['min' => $desde, 'max' => $hasta];
            } else {
                $rangos[$sucursal]['min'] = min($rangos[$sucursal]['min'], $desde);
                $rangos[$sucursal]['max'] = max($rangos[$sucursal]['max'], $hasta);
            }
        }

        return $rangos;
    }
}
