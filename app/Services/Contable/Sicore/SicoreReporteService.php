<?php

declare(strict_types=1);

namespace App\Services\Contable\Sicore;

use App\Models\Contable\Sicore_Config;
use App\Repositories\Contable\Sicore_ConfigRepositoryInterface;
use App\Support\Contable\Sicore\SicoreCriteriosSupport;
use App\Support\Contable\Sicore\SicoreCuentaRgpSupport;
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
        $configs = $this->completarGananciasPagoSiFalta($proceso, $empresaId, $configs);

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
     * Sin fila de compras_ganancias el reporte no consulta Anita.
     * La cuenta sale de RGP; impuesto y quincenas son los del régimen de pagos.
     *
     * @param  Collection<int, Sicore_Config>  $configs
     * @return Collection<int, Sicore_Config>
     */
    private function completarGananciasPagoSiFalta(string $proceso, int $empresaId, Collection $configs): Collection
    {
        if ($proceso !== SicoreCriteriosSupport::COMPRAS || $empresaId <= 0) {
            return $configs;
        }
        if ($configs->contains(static fn (Sicore_Config $c) => $c->criterio === 'compras_ganancias')) {
            return $configs;
        }
        if (SicoreCuentaRgpSupport::ganancias($empresaId) === null) {
            return $configs;
        }

        $config = new Sicore_Config([
            'codigo_impuesto' => 217,
            'codigo_regimen' => null,
            'nombre' => 'Ret. impto. gcias. a 3ros (compras)',
            'descripcion' => 'Retenciones de ganancias en pagos a proveedores (retmov). Cuenta: RGP.',
            'criterio' => 'compras_ganancias',
            'codigo_operacion' => 1,
            'concilia_con' => 'sicore',
            'frecuencia' => 'quincenal',
            'quincena_1_desde' => 1,
            'quincena_1_hasta' => 15,
            'quincena_2_desde' => 16,
            'quincena_2_hasta' => 31,
            'activo' => true,
        ]);
        $config->id = 0;
        $config->exists = false;
        $config->setRelation('cuentas', collect());
        $configs->push($config);

        return $configs;
    }
}
