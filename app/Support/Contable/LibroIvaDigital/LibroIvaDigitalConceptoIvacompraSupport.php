<?php

namespace App\Support\Contable\LibroIvaDigital;

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
            $neto = LibroIvaDigitalComprasImportesSupport::absolutoInformable((float) ($row['neto'] ?? 0));
            $iva = LibroIvaDigitalComprasImportesSupport::absolutoInformable((float) ($row['iva'] ?? 0));
            if ($neto <= 0 && $iva <= 0) {
                continue;
            }
            $filasAlicuota[] = [
                'neto' => $neto,
                'iva' => $iva,
                'tasa' => (float) ($row['tasa'] ?? 0),
                'codigo_lid' => LibroIvaDigitalMapeosSupport::codigoAlicuotaLid((float) ($row['tasa'] ?? 0)),
                'concepto_iva_simple' => (int) ($row['concepto_iva_simple'] ?? 1),
            ];
        }
        $resultado['exento'] = LibroIvaDigitalComprasImportesSupport::importeNeteado((float) $resultado['exento']);
        $resultado['no_integra'] = LibroIvaDigitalComprasImportesSupport::importeNeteado((float) $resultado['no_integra']);
        $resultado['neto_gravado'] = LibroIvaDigitalComprasImportesSupport::absolutoInformable((float) $resultado['neto_gravado']);
        $resultado['iva'] = LibroIvaDigitalComprasImportesSupport::absolutoInformable((float) $resultado['iva']);
        $resultado['perc_iva'] = LibroIvaDigitalComprasImportesSupport::absolutoInformable((float) $resultado['perc_iva']);
        $resultado['perc_iibb'] = LibroIvaDigitalComprasImportesSupport::absolutoInformable((float) $resultado['perc_iibb']);
        $resultado['perc_municipal'] = LibroIvaDigitalComprasImportesSupport::absolutoInformable((float) $resultado['perc_municipal']);
        $resultado['perc_nacional'] = LibroIvaDigitalComprasImportesSupport::absolutoInformable((float) $resultado['perc_nacional']);
        $resultado['imp_interno'] = LibroIvaDigitalComprasImportesSupport::absolutoInformable((float) $resultado['imp_interno']);

        $esC = strtoupper($letra) === 'C';
        $cantidad = $esC ? 0 : count($filasAlicuota);
        $credito = array_sum(array_column($filasAlicuota, 'iva'));

        $resultado['alicuotas'] = $cantidad > 0 ? $filasAlicuota : [];
        $resultado['cantidad_alicuotas'] = $cantidad;
        $resultado['credito_computable'] = $cantidad > 0 ? $credito : 0.0;
        if ($esC) {
            // Tipo C (monotributo): el libro no tiene columna propia; el tipo 011-016
            // lo identifica. No mezclar el total en exento / no gravado.
            $resultado['exento'] = 0.0;
            $resultado['no_integra'] = 0.0;
            $resultado['neto_gravado'] = 0.0;
            $resultado['iva'] = 0.0;
        }

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

        $monto = (float) $concepto->monto;
        if (abs($monto) < 0.0001) {
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
                if (! isset($resultado['alicuotas'][$key]['concepto_iva_simple'])) {
                    $resultado['alicuotas'][$key]['concepto_iva_simple'] = $conceptoIvaSimple;
                }
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
