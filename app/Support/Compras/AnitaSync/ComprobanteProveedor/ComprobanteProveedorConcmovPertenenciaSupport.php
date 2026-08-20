<?php

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor;

/**
 * Separa líneas concmov de un nro. interno compartido: cuáles son de la factura ERP
 * y cuáles hay que dejar (otra factura Anita). Nunca borra por nro. interno solo.
 */
final class ComprobanteProveedorConcmovPertenenciaSupport
{
    public const TOLERANCIA_IMPORTE = 0.02;

    /**
     * @param  list<array{concepto: int, importe: float}>  $lineasErp
     * @param  list<array{concepto: int, importe: float}>  $lineasConcmov
     * @return array{
     *     ok: bool,
     *     error: ?string,
     *     de_erp: list<array{concepto: int, importe: float}>,
     *     de_otras: list<array{concepto: int, importe: float}>,
     *     erp_sin_concmov: list<array{concepto: int, importe: float}>
     * }
     */
    public static function particionar(array $lineasErp, array $lineasConcmov): array
    {
        $usadas = [];
        $deErp = [];
        $erpSinConcmov = [];

        foreach ($lineasErp as $erp) {
            $concepto = (int) ($erp['concepto'] ?? 0);
            $importe = (float) ($erp['importe'] ?? 0);
            $candidatas = [];
            foreach ($lineasConcmov as $idx => $anita) {
                if (isset($usadas[$idx])) {
                    continue;
                }
                if ((int) ($anita['concepto'] ?? 0) !== $concepto) {
                    continue;
                }
                if (! self::importesIguales((float) ($anita['importe'] ?? 0), $importe)) {
                    continue;
                }
                $candidatas[] = $idx;
            }

            if (count($candidatas) > 1) {
                return [
                    'ok' => false,
                    'error' => 'Ambiguo: concepto '.$concepto.' importe '.$importe
                        .' coincide con más de una línea concmov; no se borra nada.',
                    'de_erp' => [],
                    'de_otras' => [],
                    'erp_sin_concmov' => [],
                ];
            }

            if ($candidatas === []) {
                $erpSinConcmov[] = ['concepto' => $concepto, 'importe' => $importe];

                continue;
            }

            $idx = $candidatas[0];
            $usadas[$idx] = true;
            $deErp[] = [
                'concepto' => (int) $lineasConcmov[$idx]['concepto'],
                'importe' => (float) $lineasConcmov[$idx]['importe'],
            ];
        }

        $deOtras = [];
        foreach ($lineasConcmov as $idx => $anita) {
            if (isset($usadas[$idx])) {
                continue;
            }
            $deOtras[] = [
                'concepto' => (int) ($anita['concepto'] ?? 0),
                'importe' => (float) ($anita['importe'] ?? 0),
            ];
        }

        return [
            'ok' => true,
            'error' => null,
            'de_erp' => $deErp,
            'de_otras' => $deOtras,
            'erp_sin_concmov' => $erpSinConcmov,
        ];
    }

    public static function importesIguales(float $a, float $b): bool
    {
        return abs($a - $b) <= self::TOLERANCIA_IMPORTE;
    }

    public static function sqlImporte(float $importe): string
    {
        $texto = number_format($importe, 4, '.', '');
        $texto = rtrim(rtrim($texto, '0'), '.');

        return $texto === '' || $texto === '-' ? '0' : $texto;
    }

    public static function whereBorrarLinea(int $nroInterno, int $concepto, float $importe): string
    {
        return " WHERE concv_nro_interno = '".$nroInterno."'"
            ." AND concv_concepto = '".$concepto."'"
            ." AND concv_importe = '".self::sqlImporte($importe)."' ";
    }

    public static function calcularSiguienteInterno(int $ultimoNumerador, int $maxCompra, int $maxPromov): int
    {
        return max($ultimoNumerador, $maxCompra, $maxPromov, 0) + 1;
    }
}
