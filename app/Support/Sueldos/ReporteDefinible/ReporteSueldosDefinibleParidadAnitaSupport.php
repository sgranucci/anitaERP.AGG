<?php

namespace App\Support\Sueldos\ReporteDefinible;

use App\Models\Sueldos\ReporteSueldosDefinible;
use App\Support\Sueldos\AnitaAuxLiquidacionSupport;

/**
 * Recalcula en aux* los mismos conceptos/signos de cada columna del reporte ERP.
 * Soporta nómina normal (auxhist), confidencial (auxconfh) o ambas.
 */
class ReporteSueldosDefinibleParidadAnitaSupport
{
    public function __construct(private AnitaAuxLiquidacionSupport $aux) {}

    /**
     * @return array<int, float> Totales Anita por número de columna.
     */
    public function totales(
        ReporteSueldosDefinible $reporte,
        int $empresaAnita,
        int $liquidacionAnita,
        string $nomina = AnitaAuxLiquidacionSupport::NOMINA_NORMAL
    ): array {
        $porLegajo = $this->valoresPorLegajo($reporte, $empresaAnita, $liquidacionAnita, $nomina);
        $totales = [];
        foreach ($porLegajo as $columnas) {
            foreach ($columnas as $nro => $valor) {
                $totales[(int) $nro] = (float) ($totales[(int) $nro] ?? 0) + (float) $valor;
            }
        }

        return array_map(fn ($total) => round((float) $total, 4), $totales);
    }

    /**
     * @return array<int, array<int, float>> [legajo => [nro_columna => valor]]
     */
    public function valoresPorLegajo(
        ReporteSueldosDefinible $reporte,
        int $empresaAnita,
        int $liquidacionAnita,
        string $nomina = AnitaAuxLiquidacionSupport::NOMINA_NORMAL
    ): array {
        $reporte->loadMissing('columnas.conceptos');
        $codigos = $reporte->columnas
            ->flatMap(fn ($columna) => $columna->conceptos->pluck('concepto_codigo'))
            ->map(fn ($codigo) => (int) $codigo)
            ->filter(fn ($codigo) => $codigo > 0)
            ->unique()
            ->values()
            ->all();

        if ($codigos === []) {
            return [];
        }

        $porLegajoConcepto = $this->aux->valoresPorLegajoConcepto(
            $empresaAnita,
            $liquidacionAnita,
            $codigos,
            $nomina,
            true
        );

        $valores = [];
        foreach ($porLegajoConcepto as $legajo => $porConcepto) {
            foreach ($reporte->columnas as $columna) {
                if (in_array($columna->contenido, [
                    ReporteSueldosDefinibleSupport::CONTENIDO_CAMPO_EMPLEADO,
                    ReporteSueldosDefinibleSupport::CONTENIDO_FORMULA,
                ], true)) {
                    continue;
                }
                $total = 0.0;
                foreach ($columna->conceptos as $concepto) {
                    $tipo = match ($columna->contenido) {
                        ReporteSueldosDefinibleSupport::CONTENIDO_CANTIDAD => 'cantidad',
                        ReporteSueldosDefinibleSupport::CONTENIDO_VALOR => 'valor',
                        default => 'importe',
                    };
                    $signo = $concepto->signo === '-' ? -1.0 : 1.0;
                    $total += $signo * (float) ($porConcepto[(int) $concepto->concepto_codigo][$tipo] ?? 0);
                }
                $valores[(int) $legajo][(int) $columna->nro_columna] = round($total, 4);
            }
        }

        return $valores;
    }
}
