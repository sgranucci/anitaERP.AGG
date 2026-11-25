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
    public static function desglosarComprobante(
        iterable $conceptos,
        string $letra,
        string $abreviaturaTipo = '',
    ): array {
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
            'otros' => 0.0,
            'alicuotas' => [],
            '_abreviatura_tipo' => $abreviaturaTipo,
        ];

        foreach ($conceptos as $concepto) {
            if (! $concepto instanceof Comprobante_Proveedor_Concepto) {
                continue;
            }
            self::acumularConcepto($resultado, $concepto);
        }
        unset($resultado['_abreviatura_tipo']);

        // Conservar signo por alícuota (p. ej. Edesur +27 % / −21 %): abs() por bucket
        // inflaba neto/IVA y rompía total vs suma. El TXT admite importe negativo.
        $filasAlicuota = [];
        foreach ($resultado['alicuotas'] as $tasaKey => $row) {
            $neto = LibroIvaDigitalComprasImportesSupport::importeNeteado((float) ($row['neto'] ?? 0));
            $iva = LibroIvaDigitalComprasImportesSupport::importeNeteado((float) ($row['iva'] ?? 0));
            if (abs($neto) < 0.0001 && abs($iva) < 0.0001) {
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
        $resultado['neto_gravado'] = LibroIvaDigitalComprasImportesSupport::importeNeteado((float) $resultado['neto_gravado']);
        $resultado['iva'] = LibroIvaDigitalComprasImportesSupport::importeNeteado((float) $resultado['iva']);
        $resultado['perc_iva'] = LibroIvaDigitalComprasImportesSupport::importeNeteado((float) $resultado['perc_iva']);
        $resultado['perc_iibb'] = LibroIvaDigitalComprasImportesSupport::importeNeteado((float) $resultado['perc_iibb']);
        $resultado['perc_municipal'] = LibroIvaDigitalComprasImportesSupport::importeNeteado((float) $resultado['perc_municipal']);
        $resultado['perc_nacional'] = LibroIvaDigitalComprasImportesSupport::importeNeteado((float) $resultado['perc_nacional']);
        $resultado['imp_interno'] = LibroIvaDigitalComprasImportesSupport::importeNeteado((float) $resultado['imp_interno']);
        $resultado['otros'] = LibroIvaDigitalComprasImportesSupport::importeNeteado((float) ($resultado['otros'] ?? 0));

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

        $ci = LibroIvaDigitalComprasConceptoClasificacionSupport::maestroInformado($ci);
        $clas = LibroIvaDigitalComprasConceptoClasificacionSupport::clasificar($ci);
        $destino = $clas['destino'];
        if ($destino === '') {
            return;
        }
        $tasa = $clas['tasa'];
        $nombre = (string) ($ci->nombre ?? '');
        $abrevTipo = (string) ($resultado['_abreviatura_tipo'] ?? '');
        $conceptoIvaSimple = self::resolverConceptoIvaSimple($nombre, $abrevTipo);
        $key = (string) round($tasa, 3);

        switch ($destino) {
            case 'gravado':
                $resultado['alicuotas'][$key]['neto'] = ($resultado['alicuotas'][$key]['neto'] ?? 0) + $monto;
                $resultado['alicuotas'][$key]['tasa'] = $tasa;
                $resultado['alicuotas'][$key]['concepto_iva_simple'] = self::preferirConceptoIvaSimple(
                    (int) ($resultado['alicuotas'][$key]['concepto_iva_simple'] ?? 0),
                    $conceptoIvaSimple,
                );
                $resultado['neto_gravado'] += $monto;
                break;
            case 'exento':
                $resultado['exento'] += $monto;
                break;
            case 'no_integra':
                $resultado['no_integra'] += $monto;
                break;
            case 'iva':
                $resultado['alicuotas'][$key]['iva'] = ($resultado['alicuotas'][$key]['iva'] ?? 0) + $monto;
                $resultado['alicuotas'][$key]['tasa'] = $tasa;
                $resultado['alicuotas'][$key]['concepto_iva_simple'] = self::preferirConceptoIvaSimple(
                    (int) ($resultado['alicuotas'][$key]['concepto_iva_simple'] ?? 0),
                    $conceptoIvaSimple,
                );
                $resultado['iva'] += $monto;
                break;
            case 'perc_iva':
                $resultado['perc_iva'] += $monto;
                break;
            case 'perc_iibb':
                $resultado['perc_iibb'] += $monto;
                break;
            case 'perc_municipal':
                $resultado['perc_municipal'] += $monto;
                break;
            case 'perc_nacional':
                $resultado['perc_nacional'] += $monto;
                break;
            case 'imp_interno':
                $resultado['imp_interno'] += $monto;
                break;
            case 'otros':
                $resultado['otros'] += $monto;
                break;
        }
    }

    /**
     * 1=Bienes, 2=Locaciones, 3=Servicios, 4=Bienes de uso (ARCA IVA Simple).
     * Prioriza la familia Anita del tipo (…B/…S/…L/…U) y si no, el nombre del concepto.
     */
    public static function resolverConceptoIvaSimple(string $nombreConcepto, string $abreviaturaTipo = ''): int
    {
        $desdeTipo = self::conceptoIvaSimpleDesdeAbreviaturaTipo($abreviaturaTipo);
        if ($desdeTipo !== null) {
            return $desdeTipo;
        }

        return self::conceptoIvaSimpleDesdeNombre($nombreConcepto);
    }

    /**
     * Anita Crown: FDB/FIB/FNB/…B = bienes; …S = servicios; …L = locaciones; …U = bienes de uso.
     */
    public static function conceptoIvaSimpleDesdeAbreviaturaTipo(string $abreviaturaTipo): ?int
    {
        $abrev = strtoupper(preg_replace('/[^A-Z]/', '', $abreviaturaTipo) ?? '');
        if (strlen($abrev) < 2) {
            return null;
        }

        // Prefijos de familia compra (FAC genérico no tipifica).
        if (! preg_match('/^(FD|FI|FN|FP|CD|CI|CN|CP|DD|DI|DN|DP)/', $abrev)) {
            return null;
        }

        return match (substr($abrev, -1)) {
            'B' => 1,
            'L' => 2,
            'S' => 3,
            'U' => 4,
            default => null,
        };
    }

    public static function conceptoIvaSimpleDesdeNombre(string $nombre): int
    {
        $n = strtolower(str_replace(['.', '_'], [' ', ' '], $nombre));
        $n = preg_replace('/\s+/', ' ', $n) ?? $n;

        // Bienes de uso antes que "bienes" genérico (Bs.Uso / Bs.de Uso / inversión).
        if (preg_match('/\b(bien(?:es)?\s+de\s+uso|bs\s+(?:de\s+)?uso|inversi[oó]n)/u', $n)) {
            return 4;
        }
        // Locaciones: no confundir con "Bs.Loc" (bienes locales).
        if (preg_match('/\blocaci[oó]n|\balquiler\b|(?<!\bbs\s)\bloc(?:aciones?)?\b/u', $n)) {
            return 2;
        }
        // Serv / Servic / Servicios (tras normalizar "Serv.Dir" → "serv dir").
        if (preg_match('/\bserv(?:ic(?:io)?s?)?\b/u', $n)) {
            return 3;
        }

        return 1;
    }

    /** Si ya hay un concepto distinto de bienes, no lo pisa un gravado genérico (concepto 1). */
    public static function preferirConceptoIvaSimple(int $actual, int $candidato): int
    {
        if ($actual <= 0) {
            return $candidato > 0 ? $candidato : 1;
        }
        if ($candidato <= 0 || $candidato === 1) {
            return $actual;
        }
        if ($actual === 1) {
            return $candidato;
        }

        return $actual;
    }
}
