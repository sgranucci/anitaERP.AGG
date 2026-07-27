<?php

declare(strict_types=1);

namespace App\Services\Contable\Sicore;

use App\Models\Contable\Sicore_Config;
use App\Repositories\Contable\Sicore_ConfigRepositoryInterface;
use App\Support\Contable\Sicore\SicoreCriteriosSupport;
use App\Support\Contable\Sicore\SicoreFormatoV8Support;
use App\Support\Contable\Sicore\SicoreListadoFiltros;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class SicoreReporteService
{
    /** TTL del resultado cacheado tras Consultar / liquidación (segundos). */
    private const CACHE_TTL = 2700;

    public function __construct(
        private readonly Sicore_ConfigRepositoryInterface $configRepository,
        private readonly SicoreVentasDatosService $ventasDatosService,
        private readonly SicoreComprasDatosService $comprasDatosService,
        private readonly SicoreSueldosDatosService $sueldosDatosService,
        private readonly SicoreConciliacionContableService $conciliacionService,
    ) {
    }

    /**
     * Devuelve resultado cacheado si la firma coincide; si no, genera y guarda.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarOCache(array $filtros): array
    {
        $cached = $this->leerCache($filtros);
        if ($cached !== null) {
            return $this->hidratarResultadoCacheado($cached);
        }

        $resultado = $this->generar($filtros);
        $this->guardarCache($filtros, $resultado);

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>|null
     */
    public function leerCache(array $filtros): ?array
    {
        if (! SicoreListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return null;
        }

        $pack = Cache::get(SicoreListadoFiltros::claveCacheResultado($filtros));
        if (! is_array($pack) || ($pack['firma'] ?? '') !== SicoreListadoFiltros::firma($filtros)) {
            return null;
        }
        if (! isset($pack['registros'], $pack['totales'], $pack['conciliacion'])) {
            return null;
        }

        return $pack;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     */
    public function guardarCache(array $filtros, array $resultado): void
    {
        if (! SicoreListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return;
        }

        Cache::put(
            SicoreListadoFiltros::claveCacheResultado($filtros),
            [
                'firma' => SicoreListadoFiltros::firma($filtros),
                'registros' => $resultado['registros'] ?? [],
                'totales' => $resultado['totales'] ?? [],
                'conciliacion' => $resultado['conciliacion'] ?? [],
            ],
            self::CACHE_TTL,
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $proceso = (string) ($filtros['criterio'] ?? '');
        $fechaDesde = (string) ($filtros['fecha_desde'] ?? '');
        $fechaHasta = (string) ($filtros['fecha_hasta'] ?? '');

        $criteriosConfig = SicoreCriteriosSupport::criteriosConfigParaProceso($proceso);
        /** @var Collection<int, Sicore_Config> $configs */
        $configs = $this->configRepository->activosPorCriterios($criteriosConfig);

        $registros = [];
        foreach ($configs as $config) {
            $bloque = match ($proceso) {
                SicoreCriteriosSupport::VENTAS => $this->ventasDatosService->generar($empresaId, $fechaDesde, $fechaHasta, $config),
                SicoreCriteriosSupport::COMPRAS => $this->comprasDatosService->generar($empresaId, $fechaDesde, $fechaHasta, $config),
                SicoreCriteriosSupport::SUELDOS => $this->sueldosDatosService->generar($empresaId, $fechaDesde, $fechaHasta, $config),
                default => [],
            };
            $registros = array_merge($registros, $bloque);
        }

        usort($registros, static function (array $a, array $b): int {
            $cmp = ((int) ($a['cod_regimen'] ?? 0)) <=> ((int) ($b['cod_regimen'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['fecha_retencion'] ?? ''), (string) ($b['fecha_retencion'] ?? ''));
        });

        $totales = [
            'registros' => count($registros),
            'importe' => round(array_sum(array_map(static fn (array $r) => (float) ($r['importe'] ?? 0), $registros)), 2),
            'base_calculo' => round(array_sum(array_map(static fn (array $r) => (float) ($r['base_calculo'] ?? 0), $registros)), 2),
        ];

        $conciliacion = $this->conciliacionService->conciliar($filtros, $registros, $configs);

        return [
            'registros' => $registros,
            'totales' => $totales,
            'configs' => $configs,
            'conciliacion' => $conciliacion,
            'archivo_v8' => SicoreFormatoV8Support::generarArchivo($registros),
            'desde_cache' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $pack
     * @return array<string, mixed>
     */
    private function hidratarResultadoCacheado(array $pack): array
    {
        $registros = $pack['registros'] ?? [];

        return [
            'registros' => $registros,
            'totales' => $pack['totales'] ?? [],
            'configs' => collect(),
            'conciliacion' => $pack['conciliacion'] ?? [],
            'archivo_v8' => SicoreFormatoV8Support::generarArchivo($registros),
            'desde_cache' => true,
        ];
    }
}
