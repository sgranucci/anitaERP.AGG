<?php

namespace App\Support\Configuracion\LibroIvaDigital;

/**
 * Validaciones cruzadas previas a exportar el Libro IVA Digital.
 */
final class LibroIvaDigitalValidacionSupport
{
    /**
     * @param  array<string, mixed>  $resultado
     * @return list<string>
     */
    public static function validar(array $resultado): array
    {
        $avisos = [];

        self::validarLongitudes($resultado, $avisos);
        self::validarAlicuotasVentas($resultado, $avisos);
        self::validarTotalesIvaSimple($resultado, $avisos);
        self::validarActividades($resultado, $avisos);

        return $avisos;
    }

    /**
     * @param  list<string>  $avisos
     */
    private static function validarLongitudes(array $resultado, array &$avisos): void
    {
        $muestras = [
            ['ventas.ventas_cbte', 266, 'VENTAS_CBTE'],
            ['ventas.ventas_alicuotas', 62, 'VENTAS_ALICUOTAS'],
            ['compras.compras_cbte', 325, 'COMPRAS_CBTE'],
            ['compras.compras_alicuotas', 84, 'COMPRAS_ALICUOTAS'],
            ['anulados.ventas', 44, 'VENTAS_ANULADOS'],
            ['anulados.compras', 44, 'COMPRAS_ANULADOS'],
            ['importaciones.importacion_bienes_alicuotas', 50, 'IMPORT_BIENES_ALIC'],
            ['importaciones.importacion_servicios', 211, 'IMPORT_SERVICIOS'],
        ];

        foreach ($muestras as [$path, $len, $label]) {
            $contenido = (string) data_get($resultado, $path, '');
            if ($contenido === '') {
                continue;
            }
            $primera = preg_split('/\r\n|\n|\r/', $contenido)[0] ?? '';
            if (strlen($primera) !== $len) {
                $avisos[] = "Registro {$label}: longitud {$len} esperada, obtuvo ".strlen($primera).'.';
            }
        }
    }

    /**
     * @param  list<string>  $avisos
     */
    private static function validarAlicuotasVentas(array $resultado, array &$avisos): void
    {
        $cbte = (int) ($resultado['ventas']['resumen']['comprobantes'] ?? 0);
        $alic = (int) ($resultado['ventas']['resumen']['alicuotas'] ?? 0);
        $cbteConAlic = (int) ($resultado['ventas']['resumen']['comprobantes_con_alicuotas'] ?? 0);

        if ($cbteConAlic > 0 && $alic === 0) {
            $avisos[] = 'Hay comprobantes de venta A/M con IVA discriminado pero el archivo de alícuotas está vacío.';
        }
        if ($alic > 0 && $alic < $cbteConAlic) {
            $avisos[] = "Alícuotas ventas ({$alic}) menor que comprobantes con IVA discriminado ({$cbteConAlic}).";
        }
        if ($cbte === 0 && ($resultado['compras']['resumen']['comprobantes'] ?? 0) === 0) {
            $avisos[] = 'Sin movimientos de ventas ni compras en el período (verifique «Con/Sin movimientos» en ARCA).';
        }
    }

    /**
     * @param  list<string>  $avisos
     */
    private static function validarTotalesIvaSimple(array $resultado, array &$avisos): void
    {
        $totalIvaVentas = (float) ($resultado['ventas']['resumen']['total_iva'] ?? 0);
        $totalIvaSimple = (float) ($resultado['iva_simple']['resumen']['total_iva_debito'] ?? 0);

        if ($totalIvaVentas > 0 && $totalIvaSimple > 0) {
            $diff = abs($totalIvaVentas - $totalIvaSimple);
            if ($diff > max(1.0, $totalIvaVentas * 0.02)) {
                $avisos[] = sprintf(
                    'IVA débito ventas (%.2f) difiere del CSV IVA Simple (%.2f) en más del 2%%.',
                    $totalIvaVentas,
                    $totalIvaSimple,
                );
            }
        }
    }

    /**
     * @param  list<string>  $avisos
     */
    private static function validarActividades(array $resultado, array &$avisos): void
    {
        $sinActividad = (int) ($resultado['iva_simple']['resumen']['sin_actividad_arca'] ?? 0);
        if ($sinActividad > 0) {
            $avisos[] = "Hay {$sinActividad} agrupación(es) IVA Simple sin código de actividad ARCA (000000). Revise PV y ventas.";
        }
    }
}
