<?php

declare(strict_types=1);

namespace App\Services\Contable\Sicore;

use App\Models\Contable\Sicore_Config;
use App\Repositories\Contable\Sicore_ConfigRepositoryInterface;
use App\Support\Contable\Sicore\SicoreCriteriosSupport;
use App\Support\Contable\Sicore\SicoreFormatoV8Support;
use Illuminate\Support\Collection;

final class SicoreReporteService
{
    public function __construct(
        private readonly Sicore_ConfigRepositoryInterface $configRepository,
        private readonly SicoreVentasDatosService $ventasDatosService,
        private readonly SicoreComprasDatosService $comprasDatosService,
        private readonly SicoreSueldosDatosService $sueldosDatosService,
        private readonly SicoreConciliacionContableService $conciliacionService,
    ) {
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
        ];
    }
}
