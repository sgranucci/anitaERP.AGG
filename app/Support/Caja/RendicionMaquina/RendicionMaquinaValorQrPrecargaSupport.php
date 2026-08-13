<?php

declare(strict_types=1);

namespace App\Support\Caja\RendicionMaquina;

/**
 * Precarga del valor TotalCoin QR Máquinas en turno mañana:
 * drop QR rodillo (neto WIGOS) + impuesto QR.
 *
 * Distingue TOTAL COIN MAQUINAS de TOTAL COIN CAJA y de M0QR (QR Máquinas).
 */
final class RendicionMaquinaValorQrPrecargaSupport
{
    /**
     * @param  array<string, float|int|string>  $inputs
     */
    public static function montoDesdeInputs(array $inputs): float
    {
        $drop = self::input($inputs, 'dropqr_rodillo');
        $impuesto = self::input($inputs, 'impuesto_qr');

        return round($drop + $impuesto, 2);
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    public static function esTotalCoinQrMaquinas(array $linea): bool
    {
        $texto = mb_strtolower(trim(implode(' ', [
            (string) ($linea['nombre'] ?? ''),
            (string) ($linea['descripcion_operaciones'] ?? ''),
            (string) ($linea['nombre_maestro'] ?? ''),
        ])));
        if ($texto === '') {
            return false;
        }

        $esTotalCoin = str_contains($texto, 'totalcoin') || str_contains($texto, 'total coin');
        $esMaquina = str_contains($texto, 'maquin');

        return $esTotalCoin && $esMaquina;
    }

    /**
     * @param  array<string, float|int|string>  $inputs
     * @param  list<array<string, mixed>>  $valores
     * @return list<array{cuentacaja_id: int, monto: float}>
     */
    public static function lineasPrecarga(array $inputs, array $valores): array
    {
        $monto = self::montoDesdeInputs($inputs);
        $out = [];
        foreach ($valores as $linea) {
            if (! self::esTotalCoinQrMaquinas($linea)) {
                continue;
            }
            $id = (int) ($linea['cuentacaja_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'cuentacaja_id' => $id,
                'monto' => $monto,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, float|int|string>  $inputs
     */
    private static function input(array $inputs, string $clave): float
    {
        if (array_key_exists($clave, $inputs)) {
            return (float) $inputs[$clave];
        }
        $ruta = 'inputs.'.$clave;
        if (array_key_exists($ruta, $inputs)) {
            return (float) $inputs[$ruta];
        }

        return 0.0;
    }
}
