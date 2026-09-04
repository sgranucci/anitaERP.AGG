<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Services\Contable\MayorPlanoCuentaReporteService;
use App\Support\Contable\MayorPlanoCuentaListadoFiltros;

/**
 * CSV estilo Excel plano (l-mayor opción 3): mismas columnas que el export de pantalla,
 * con emisor / OC / CAPEX / facturas vía enrichers en lotes.
 */
final class MayorPlanoCuentaCsvExportSupport
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return list<string>
     */
    public static function cabecerasExcelPlano(array $filtros): array
    {
        $mostrarCc = MayorPlanoCuentaListadoFiltros::mostrarColumnaCentrocosto($filtros);

        return $mostrarCc
            ? ['Empresa', 'Nro.Asi.', 'Fecha', 'Cuenta', 'Descripcion', 'C.Costo', 'Mon', 'Cotizacion', 'Debe', 'Haber', 'Detalle', 'Cod. emisor', 'Nombre emisor', 'Usuario', 'fecha ult. mod', 'O.Compra', 'proyecto CAPEX', 'Que se compro (OC)', 'Numeros de Facturas']
            : ['Empresa', 'Nro.Asi.', 'Fecha', 'Cuenta', 'Descripcion', 'Mon', 'Cotizacion', 'Debe', 'Haber', 'Detalle', 'Cod. emisor', 'Nombre emisor', 'Usuario', 'fecha ult. mod', 'O.Compra', 'proyecto CAPEX', 'Que se compro (OC)', 'Numeros de Facturas'];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>  $filtros
     * @return list<string|int|float>
     */
    public static function filaExcelPlanoACsv(array $fila, array $filtros): array
    {
        $mostrarCc = MayorPlanoCuentaListadoFiltros::mostrarColumnaCentrocosto($filtros);
        $row = [
            $fila['empresa_id'] ?? '',
            $fila['nro_asiento_fmt'] ?? $fila['nro_asiento'] ?? '',
            $fila['fecha_fmt'] ?? '',
            $fila['cuenta_codigo'] ?? '',
            $fila['cuenta_nombre'] ?? '',
        ];
        if ($mostrarCc) {
            $row[] = $fila['centrocosto_codigo'] ?? '';
        }

        return array_merge($row, [
            $fila['moneda_abrev'] ?? '',
            $fila['cotizacion'] ?? '',
            $fila['debe'] ?? '',
            $fila['haber'] ?? '',
            $fila['descripcion'] ?? '',
            $fila['emisor'] ?? '',
            $fila['emisor_nombre'] ?? '',
            $fila['usuario'] ?? '',
            $fila['fecha_ult_mod'] ?? '',
            ((int) ($fila['nro_oc'] ?? 0) > 0) ? $fila['nro_oc'] : '',
            $fila['proyecto_capex'] ?? '',
            $fila['observacion_oc'] ?? '',
            $fila['numeros_facturas'] ?? '',
        ]);
    }

    /**
     * Escribe CSV Excel plano enriquecido (para cola/mail o disco).
     *
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     * @return array{path: string, filas: int, bytes: int}
     */
    public static function escribirCsvExcelPlano(
        MayorPlanoCuentaReporteService $reporteService,
        array $resultado,
        array $filtros,
        string $rutaAbsoluta,
    ): array {
        $dir = dirname($rutaAbsoluta);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear directorio de export: '.$dir);
        }

        $out = fopen($rutaAbsoluta, 'w');
        if ($out === false) {
            throw new \RuntimeException('No se pudo crear CSV: '.$rutaAbsoluta);
        }

        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::cabecerasExcelPlano($filtros), ';');

        $n = 0;
        foreach ($reporteService->iterarMovimientosExcelPlano($resultado, $filtros) as $fila) {
            fputcsv($out, self::filaExcelPlanoACsv($fila, $filtros), ';');
            $n++;
            if ($n % 2000 === 0) {
                fflush($out);
            }
        }

        fclose($out);
        @chmod($rutaAbsoluta, 0664);

        return [
            'path' => $rutaAbsoluta,
            'filas' => $n,
            'bytes' => (int) filesize($rutaAbsoluta),
        ];
    }
}
