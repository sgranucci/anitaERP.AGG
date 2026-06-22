<?php

namespace App\Support\Stock;

use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;

final class MovimientoStockCuadreContableSupport
{
    public static function tolerancia(): float
    {
        return 0.02;
    }

    /**
     * @param  array{total_movimiento: float, total_debe: float, total_haber: float}  $preview
     */
    public static function assertPreview(array $preview): void
    {
        $tol = self::tolerancia();
        $totalMov = (float) ($preview['total_movimiento'] ?? 0);
        $totalDebe = (float) ($preview['total_debe'] ?? 0);
        $totalHaber = (float) ($preview['total_haber'] ?? 0);

        if (abs($totalDebe - $totalHaber) >= $tol) {
            throw new \RuntimeException(
                'El asiento contable está desbalanceado. Debe '
                .number_format($totalDebe, 2, ',', '.')
                .' vs haber '.number_format($totalHaber, 2, ',', '.').'.'
            );
        }

        if (abs($totalMov - $totalDebe) >= $tol) {
            throw new \RuntimeException(
                'La contabilidad no suma lo mismo que el movimiento. Total movimiento '
                .number_format($totalMov, 2, ',', '.')
                .', asiento debe '.number_format($totalDebe, 2, ',', '.').'.'
            );
        }
    }

    public static function assertPersistido(
        int $asientoId,
        array $preview,
        Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository
    ): void {
        if ($asientoId <= 0) {
            throw new \RuntimeException('Cuadre contable: asiento sin identificador tras la grabación.');
        }

        $movimientos = $asientoMovimientoRepository->leeAsientoMovimiento($asientoId);
        $totales = RecepcionProveedorCuadreContableSupport::totalesDesdeMovimientos($movimientos);

        $tol = self::tolerancia();
        $debeEsperado = round((float) ($preview['total_debe'] ?? 0), 2);
        $haberEsperado = round((float) ($preview['total_haber'] ?? 0), 2);

        if (abs($totales['debe'] - $debeEsperado) >= $tol || abs($totales['haber'] - $haberEsperado) >= $tol) {
            throw new \RuntimeException(
                'El asiento grabado (id '.$asientoId.') no coincide con el movimiento.'
            );
        }
    }
}
