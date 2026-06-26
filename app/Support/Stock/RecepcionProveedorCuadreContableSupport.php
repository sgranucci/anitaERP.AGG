<?php

namespace App\Support\Stock;

use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Valida que el importe de la recepción coincida con el asiento contable (debe = haber = total recepción).
 */
final class RecepcionProveedorCuadreContableSupport
{
    public static function tolerancia(): float
    {
        $tol = (float) config('recepcion_proveedor.tolerancia_cuadre_contable', 0.02);

        return $tol > 0 ? $tol : 0.02;
    }

    public static function importeContableEsCero(float $total): bool
    {
        return abs($total) < self::tolerancia();
    }

    /**
     * @param  array{
     *   total_recepcion: float,
     *   total_debe: float,
     *   total_haber: float,
     *   numerorecepcion?: ?string
     * }  $preview
     */
    public static function assertPreview(array $preview): void
    {
        self::assertTotales(
            (float) ($preview['total_recepcion'] ?? 0),
            (float) ($preview['total_debe'] ?? 0),
            (float) ($preview['total_haber'] ?? 0),
            isset($preview['numerorecepcion']) ? (string) $preview['numerorecepcion'] : null
        );
    }

    public static function assertTotales(
        float $totalRecepcion,
        float $totalDebe,
        float $totalHaber,
        ?string $numerorecepcion = null
    ): void {
        $tol = self::tolerancia();
        $ref = $numerorecepcion !== null && $numerorecepcion !== ''
            ? " (recepción {$numerorecepcion})"
            : '';

        if (abs($totalDebe - $totalHaber) >= $tol) {
            throw new \RuntimeException(
                'No se puede confirmar: el asiento contable está desbalanceado'
                .$ref.'. Debe '.self::formatoImporte($totalDebe)
                .' vs haber '.self::formatoImporte($totalHaber).'.'
            );
        }

        if (abs($totalRecepcion - $totalDebe) >= $tol) {
            throw new \RuntimeException(
                'No se puede confirmar: la contabilidad no suma lo mismo que la recepción'
                .$ref.'. Total recepción '.self::formatoImporte($totalRecepcion)
                .', asiento debe '.self::formatoImporte($totalDebe)
                .', haber '.self::formatoImporte($totalHaber).'.'
            );
        }
    }

    /**
     * @param  array{total_debe: float, total_haber: float}  $preview
     */
    public static function assertPersistido(
        int $asientoId,
        array $preview,
        Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository
    ): void {
        if ($asientoId <= 0) {
            throw new \RuntimeException('Cuadre contable: asiento sin identificador tras la grabación.');
        }

        $movimientos = $asientoMovimientoRepository->leeAsientoMovimiento($asientoId);
        $totales = self::totalesDesdeMovimientos($movimientos);

        $tol = self::tolerancia();
        $debeEsperado = round((float) ($preview['total_debe'] ?? 0), 2);
        $haberEsperado = round((float) ($preview['total_haber'] ?? 0), 2);

        if (abs($totales['debe'] - $debeEsperado) >= $tol || abs($totales['haber'] - $haberEsperado) >= $tol) {
            throw new \RuntimeException(
                'No se puede confirmar: el asiento grabado (id '.$asientoId.') no coincide con la recepción. '
                .'Persistido debe '.self::formatoImporte($totales['debe'])
                .' / haber '.self::formatoImporte($totales['haber'])
                .'; esperado debe '.self::formatoImporte($debeEsperado)
                .' / haber '.self::formatoImporte($haberEsperado).'.'
            );
        }
    }

    /**
     * @param  Collection<int, object>|iterable<int, object>  $movimientos
     * @return array{debe: float, haber: float}
     */
    public static function totalesDesdeMovimientos(iterable $movimientos): array
    {
        $debe = 0.0;
        $haber = 0.0;

        foreach ($movimientos as $movimiento) {
            $monto = (float) ($movimiento->monto ?? 0);
            if ($monto > 0) {
                $debe += $monto;
            } elseif ($monto < 0) {
                $haber += abs($monto);
            }
        }

        return [
            'debe' => round($debe, 2),
            'haber' => round($haber, 2),
        ];
    }

    private static function formatoImporte(float $importe): string
    {
        return number_format($importe, 2, ',', '.');
    }
}
