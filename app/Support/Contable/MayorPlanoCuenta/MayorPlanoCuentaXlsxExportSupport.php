<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Services\Contable\MayorPlanoCuentaReporteService;
use App\Support\Export\XlsxStreamWriter;

/**
 * Excel plano (.xlsx) con las mismas columnas que el CSV histórico,
 * escrito en streaming para volúmenes de cierre (ene–ago, multiempresa).
 */
final class MayorPlanoCuentaXlsxExportSupport
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     * @return array{path: string, filas: int, bytes: int}
     */
    public static function escribirExcelPlano(
        MayorPlanoCuentaReporteService $reporteService,
        array $resultado,
        array $filtros,
        string $rutaAbsoluta,
        string $nombreHoja = 'Mayor plano',
    ): array {
        $writer = new XlsxStreamWriter($rutaAbsoluta, $nombreHoja);
        $writer->escribirCabecera(MayorPlanoCuentaCsvExportSupport::cabecerasExcelPlano($filtros));

        foreach ($reporteService->iterarMovimientosExcelPlano($resultado, $filtros) as $fila) {
            $writer->escribirFila(MayorPlanoCuentaCsvExportSupport::filaExcelPlanoACsv($fila, $filtros));
        }

        return $writer->cerrar();
    }
}
