<?php

namespace App\Support\Configuracion\LibroIvaDigital;

use App\Models\Compras\Comprobante_Proveedor_Concepto;

/**
 * Mapeo concepto_ivacompra → columnas Libro IVA Digital compras / IVA Simple.
 */
final class LibroIvaDigitalConceptoIvacompraSupport
{
    /**
     * @return array{
     *     neto_gravado: float,
     *     iva: float,
     *     exento: float,
     *     no_integra: float,
     *     perc_iva: float,
     *     perc_iibb: float,
     *     perc_municipal: float,
     *     perc_nacional: float,
     *     imp_interno: float,
     *     alicuotas: array<string, array{neto: float, iva: float, tasa: float, concepto_iva_simple: int}>
     * }
     */
    public static function desglosarComprobante(iterable $conceptos, string $letra): array
    {
        $resultado = [
            'neto_gravado' => 0.0,
            'iva' => 0.0,
            'exento' => 0.0,
            'no_integra' => 0.0,
            'perc_iva' => 0.0,
            'perc_iibb' => 0.0,
            'perc_municipal' => 0.0,
            'perc_nacional' => 0.0,
            'imp_interno' => 0.0,
            'alicuotas' => [],
        ];

        foreach ($conceptos as $concepto) {
            if (! $concepto instanceof Comprobante_Proveedor_Concepto) {
                continue;
            }
            self::acumularConcepto($resultado, $concepto);
        }

        $filasAlicuota = [];
        foreach ($resultado['alicuotas'] as $tasaKey => $row) {
            if (($row['neto'] ?? 0) <= 0 && ($row['iva'] ?? 0) <= 0) {
                continue;
            }
            $filasAlicuota[] = [
                'neto' => (float) ($row['neto'] ?? 0),
                'iva' => (float) ($row['iva'] ?? 0),
                'tasa' => (float) ($row['tasa'] ?? 0),
                'codigo_lid' => LibroIvaDigitalMapeosSupport::codigoAlicuotaLid((float) ($row['tasa'] ?? 0)),
                'concepto_iva_simple' => (int) ($row['concepto_iva_simple'] ?? 1),
            ];
        }

        $cantidad = in_array(strtoupper($letra), ['B', 'C'], true) ? 0 : count($filasAlicuota);
        $credito = array_sum(array_column($filasAlicuota, 'iva'));

        $resultado['alicuotas'] = $cantidad > 0 ? $filasAlicuota : [];
        $resultado['cantidad_alicuotas'] = $cantidad;
        $resultado['credito_computable'] = $cantidad > 0 ? $credito : 0.0;

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private static function acumularConcepto(array &$resultado, Comprobante_Proveedor_Concepto $concepto): void
    {
        $ci = $concepto->concepto_ivacompras;
        if ($ci === null) {
            return;
        }

        $monto = abs((float) $concepto->monto);
        if ($monto <= 0) {
            return;
        }

        $tipo = strtoupper((string) ($ci->tipoconcepto ?? ''));
        $tasa = (float) ($ci->impuestos->valor ?? 0);
        $nombre = (string) ($ci->nombre ?? '');
        $conceptoIvaSimple = self::conceptoIvaSimpleDesdeNombre($nombre);

        switch ($tipo) {
            case 'G':
                $key = (string) round($tasa, 3);
                $resultado['alicuotas'][$key]['neto'] = ($resultado['alicuotas'][$key]['neto'] ?? 0) + $monto;
                $resultado['alicuotas'][$key]['tasa'] = $tasa;
                $resultado['alicuotas'][$key]['concepto_iva_simple'] = $conceptoIvaSimple;
                $resultado['neto_gravado'] += $monto;
                break;
            case 'E':
                $resultado['exento'] += $monto;
                break;
            case 'N':
                $resultado['no_integra'] += $monto;
                break;
            case 'I':
                $key = (string) round($tasa, 3);
                $resultado['alicuotas'][$key]['iva'] = ($resultado['alicuotas'][$key]['iva'] ?? 0) + $monto;
                $resultado['alicuotas'][$key]['tasa'] = $tasa;
                $resultado['alicuotas'][$key]['concepto_iva_simple'] = $conceptoIvaSimple;
                $resultado['iva'] += $monto;
                break;
            case 'P':
                $resultado['perc_iva'] += $monto;
                break;
            case 'B':
            case 'S':
                $resultado['perc_iibb'] += $monto;
                break;
            case 'M':
                $resultado['perc_municipal'] += $monto;
                break;
            case 'A':
                $resultado['perc_nacional'] += $monto;
                break;
            case 'T':
                $resultado['imp_interno'] += $monto;
                break;
        }
    }

    public static function conceptoIvaSimpleDesdeNombre(string $nombre): int
    {
        $n = strtolower($nombre);
        if (str_contains($n, 'locacion') || str_contains($n, 'locación')) {
            return 2;
        }
        if (str_contains($n, 'servic')) {
            return 3;
        }
        if (str_contains($n, 'bien de uso') || str_contains($n, 'bienes de uso') || str_contains($n, 'inversion')) {
            return 4;
        }

        return 1;
    }
}
