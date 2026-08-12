<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\Cuentacontable;
use App\Models\Contable\ReporteContable;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;

/**
 * Cobertura del plan de cuentas vs asignación del informe (estilo FSV unassigned).
 */
class ReporteDefinibleCoberturaSupport
{
    public function __construct(
        private readonly ReporteDefinibleCuentaRangoSupport $cuentaRangoSupport,
    ) {
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array{
     *   total_plan: int,
     *   asignadas: int,
     *   pct: float,
     *   huerfanas: list<array{codigo: int, codigo_fmt: string, nombre: string}>,
     *   huerfanas_total: int,
     *   duplicadas: list<array{codigo: int, codigo_fmt: string, rubros: list<string>}>,
     *   sin_cuenta_erp: list<array{codigo: int, codigo_fmt: string, rubro: string}>
     * }
     */
    public function analizar(ReporteContable $reporte, array $empresaIds = []): array
    {
        $reporte->loadMissing(['rubros.cuentas.cuentacontable']);
        app(ReporteDefinibleConjuntoSupport::class)->expandirEnReporte($reporte);

        $empresaIds = array_values(array_filter(array_map('intval', $empresaIds)));

        $queryPlan = Cuentacontable::query()->where('tipocuenta', 1);
        if ($empresaIds !== []) {
            $queryPlan->whereIn('empresa_id', $empresaIds);
        }
        $plan = $queryPlan->get(['id', 'codigo', 'nombre']);
        $planPorCodigo = [];
        foreach ($plan as $c) {
            $codigo = (int) $c->codigo;
            if (! isset($planPorCodigo[$codigo])) {
                $planPorCodigo[$codigo] = [
                    'codigo' => $codigo,
                    'codigo_fmt' => MayorPlanoCuentaSupport::formatearCodigoCuenta($codigo),
                    'nombre' => (string) $c->nombre,
                ];
            }
        }

        /** @var array<int, list<string>> $asignacion */
        $asignacion = [];
        $sinErp = [];
        foreach ($reporte->rubros as $rubro) {
            $etiqueta = trim((string) ($rubro->codigo_linea ? $rubro->codigo_linea.' ' : '').$rubro->nombre);
            foreach ($rubro->cuentas as $cta) {
                if ($cta->origen === ReporteDefinibleSupport::ORIGEN_PRESUPUESTO) {
                    continue;
                }
                foreach ($this->cuentaRangoSupport->expandirAsignacion($cta) as $codigo) {
                    $asignacion[$codigo][] = $etiqueta;
                    if (! $cta->cuentacontable_id && ! isset($planPorCodigo[$codigo])) {
                        $sinErp[] = [
                            'codigo' => $codigo,
                            'codigo_fmt' => MayorPlanoCuentaSupport::formatearCodigoCuenta($codigo),
                            'rubro' => $etiqueta,
                        ];
                    }
                }
            }
        }

        $asignadasUnicas = array_keys($asignacion);
        $huerfanas = [];
        foreach ($planPorCodigo as $codigo => $meta) {
            if (! isset($asignacion[$codigo])) {
                $huerfanas[] = $meta;
            }
        }

        $duplicadas = [];
        foreach ($asignacion as $codigo => $rubros) {
            $rubrosUnicos = array_values(array_unique($rubros));
            if (count($rubrosUnicos) > 1) {
                $duplicadas[] = [
                    'codigo' => $codigo,
                    'codigo_fmt' => MayorPlanoCuentaSupport::formatearCodigoCuenta($codigo),
                    'rubros' => $rubrosUnicos,
                ];
            }
        }

        $totalPlan = count($planPorCodigo);
        $asignadasEnPlan = count(array_filter(
            $asignadasUnicas,
            fn (int $c) => isset($planPorCodigo[$c])
        ));
        $pct = $totalPlan > 0 ? round(($asignadasEnPlan / $totalPlan) * 100, 1) : 0.0;

        return [
            'total_plan' => $totalPlan,
            'asignadas' => $asignadasEnPlan,
            'pct' => $pct,
            'huerfanas' => array_slice($huerfanas, 0, 80),
            'huerfanas_total' => count($huerfanas),
            'duplicadas' => $duplicadas,
            'sin_cuenta_erp' => array_slice($sinErp, 0, 40),
        ];
    }
}
