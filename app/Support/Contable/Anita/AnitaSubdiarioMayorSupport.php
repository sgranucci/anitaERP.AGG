<?php

declare(strict_types=1);

namespace App\Support\Contable\Anita;

/**
 * Convención Anita subdiario → Debe/Haber en mayor analítico.
 *
 * {@see MayorPlanoCuentaProcesador} (l-mayor.c) es la implementación de referencia del mayor por cuenta.
 */
final class AnitaSubdiarioMayorSupport
{
    /**
     * Lado opuesto al tipo de movimiento (contrapartida).
     */
    public static function dhContrapartida(string $tipoMovimiento): string
    {
        return self::normalizarDh($tipoMovimiento) === 'D' ? 'H' : 'D';
    }

    /**
     * D/H de una cuenta imputada en una línea de subdiario.
     *
     * @param  'D'|'H'|string  $tipoMovimiento  Lado de subd_cuenta (subd_tipo_mov)
     */
    public static function dhParaCuenta(string $tipoMovimiento, int $cuentaImputada, int $subdCuenta): string
    {
        $mov = self::normalizarDh($tipoMovimiento);

        return $cuentaImputada === $subdCuenta ? $mov : self::dhContrapartida($mov);
    }

    /**
     * Expande una línea de subdiario en hasta dos imputaciones (cuenta + contrapartida).
     *
     * Cuando subd_contrapartida = subd_cuenta (ej. cancelación de caución, reemplazo de cheque)
     * la línea genera igual las dos piernas: l-mayor.c hace las dos pasadas sin excluir la
     * igualdad y el resumen ctamov de Anita graba D y H sobre la misma cuenta.
     *
     * @return list<array{cuenta: int, dh: string, importe: float, lado: 'cuenta'|'contrapartida'}>
     */
    public static function imputacionesLineaSubdiario(object $linea): array
    {
        $importe = abs((float) ($linea->subd_importe ?? 0));
        if ($importe < 0.001) {
            return [];
        }

        $tipoMov = self::normalizarDh((string) ($linea->subd_tipo_mov ?? 'D'));
        $cuenta = (int) ($linea->subd_cuenta ?? 0);
        $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
        $out = [];

        if ($cuenta > 0) {
            $out[] = [
                'cuenta' => $cuenta,
                'dh' => $tipoMov,
                'importe' => $importe,
                'lado' => 'cuenta',
            ];
        }

        if ($contrapartida > 0) {
            $out[] = [
                'cuenta' => $contrapartida,
                'dh' => self::dhContrapartida($tipoMov),
                'importe' => $importe,
                'lado' => 'contrapartida',
            ];
        }

        return $out;
    }

    /**
     * @return array{cuenta: int, dh: string, importe: float}|null
     */
    public static function imputacionLineaCtamov(object $linea): ?array
    {
        $cuenta = (int) ($linea->ctav_cuenta ?? 0);
        $importe = abs((float) ($linea->ctav_importe ?? 0));
        $dh = self::normalizarDh((string) ($linea->ctav_d_h ?? ''));

        if ($cuenta <= 0 || $importe < 0.001 || ! in_array($dh, ['D', 'H'], true)) {
            return null;
        }

        return [
            'cuenta' => $cuenta,
            'dh' => $dh,
            'importe' => $importe,
        ];
    }

    /**
     * @return array{debe: float|null, haber: float|null, neto_haber: float}
     */
    public static function debeHaberDesdeDh(string $dh, float $importe): array
    {
        $dh = self::normalizarDh($dh);
        $importe = round($importe, 2);

        return [
            'debe' => $dh === 'D' ? $importe : null,
            'haber' => $dh === 'H' ? $importe : null,
            'neto_haber' => $dh === 'H' ? $importe : -$importe,
        ];
    }

    /**
     * @return 'D'|'H'
     */
    private static function normalizarDh(string $dh): string
    {
        $dh = strtoupper(trim($dh));

        return $dh === 'H' ? 'H' : 'D';
    }
}
