<?php

namespace App\Support\Sueldos;

use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;

final class SueldosAsientoCuadreSupport
{
    public static function tolerancia(): float
    {
        return 0.02;
    }

    /**
     * @param  array{total_debe: float, total_haber: float}  $preview
     */
    public static function assertPreview(array $preview): void
    {
        $debe = round((float) ($preview['total_debe'] ?? 0), 2);
        $haber = round((float) ($preview['total_haber'] ?? 0), 2);
        if (abs($debe - $haber) >= self::tolerancia()) {
            throw new \RuntimeException(
                'El asiento de sueldos está desbalanceado. Debe '
                .number_format($debe, 2, ',', '.')
                .' vs haber '.number_format($haber, 2, ',', '.').'.'
            );
        }
        if ($debe < 0.01) {
            throw new \RuntimeException('El asiento de sueldos no tiene importes para contabilizar.');
        }
    }

    /**
     * @param  array{total_debe: float, total_haber: float}  $preview
     */
    public static function assertPersistido(
        int $asientoId,
        array $preview,
        Asiento_MovimientoRepositoryInterface $movimientos
    ): void {
        if ($asientoId <= 0) {
            throw new \RuntimeException('Cuadre contable: asiento sin identificador tras la grabación.');
        }

        $filas = $movimientos->leeAsientoMovimiento($asientoId);
        $debe = 0.0;
        $haber = 0.0;
        foreach ($filas as $fila) {
            $monto = (float) ($fila->monto ?? 0);
            if ($monto > 0) {
                $debe += $monto;
            } elseif ($monto < 0) {
                $haber += abs($monto);
            }
        }

        $tol = self::tolerancia();
        $debeEsperado = round((float) ($preview['total_debe'] ?? 0), 2);
        $haberEsperado = round((float) ($preview['total_haber'] ?? 0), 2);
        if (abs(round($debe, 2) - $debeEsperado) >= $tol || abs(round($haber, 2) - $haberEsperado) >= $tol) {
            throw new \RuntimeException(
                'El asiento grabado (id '.$asientoId.') no coincide con el preview de sueldos.'
            );
        }
    }
}
