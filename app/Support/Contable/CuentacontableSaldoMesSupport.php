<?php

namespace App\Support\Contable;

use App\Models\Contable\Cuentacontable_Saldo_Mes;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aplica deltas sobre cuentacontable_saldo_mes (agregado mensual por moneda origen).
 *
 * - monto / monto_local: neto firmado (debe +, haber −).
 * - debe / haber (+ _local): brutos del mes (para Balance SyS por períodos).
 */
class CuentacontableSaldoMesSupport
{
    public static function observerHabilitado(): bool
    {
        return (bool) config('contable.saldos_cuenta_mes.observer_habilitado', false);
    }

    public static function monedaLocalId(): int
    {
        return (int) config('contable.saldos_cuenta_mes.moneda_local_id', 1);
    }

    /**
     * Alta (+1) o baja (−1) de un movimiento firmado sobre el agregado mensual.
     *
     * @param  array{
     *   empresa_id: int|null,
     *   cuentacontable_id: int|null,
     *   centrocosto_id: int|null,
     *   fecha: mixed,
     *   moneda_id: int|null,
     *   monto: float,
     *   cotizacion: float|null
     * }  $contexto
     */
    public static function aplicarMovimiento(array $contexto, float $signedMonto, int $factor = 1): void
    {
        if (! self::observerHabilitado() || abs($signedMonto) < 1e-9 || $factor === 0) {
            return;
        }

        $factor = $factor >= 0 ? 1 : -1;
        $deltaNeto = $signedMonto * $factor;
        $deltaDebe = ($signedMonto > 0 ? $signedMonto : 0.0) * $factor;
        $deltaHaber = ($signedMonto < 0 ? abs($signedMonto) : 0.0) * $factor;

        $monedaId = (int) ($contexto['moneda_id'] ?? 0);
        $deltaLocal = self::convertirMontoLocal($signedMonto, $monedaId, $contexto['cotizacion'] ?? null) * $factor;
        $deltaDebeLocal = ($signedMonto > 0
            ? self::convertirMontoLocal($signedMonto, $monedaId, $contexto['cotizacion'] ?? null)
            : 0.0) * $factor;
        $deltaHaberLocal = ($signedMonto < 0
            ? abs(self::convertirMontoLocal($signedMonto, $monedaId, $contexto['cotizacion'] ?? null))
            : 0.0) * $factor;

        self::aplicarDeltas(
            $contexto,
            $deltaNeto,
            $deltaLocal,
            $deltaDebe,
            $deltaHaber,
            $deltaDebeLocal,
            $deltaHaberLocal,
        );
    }

    /**
     * @param  array{
     *   empresa_id: int|null,
     *   cuentacontable_id: int|null,
     *   centrocosto_id: int|null,
     *   fecha: mixed,
     *   moneda_id: int|null,
     *   monto: float,
     *   cotizacion: float|null
     * }  $contexto
     *
     * @deprecated Preferir aplicarMovimiento() para mantener debe/haber brutos.
     */
    public static function aplicarDelta(array $contexto, float $deltaMonto): void
    {
        if (abs($deltaMonto) < 1e-9) {
            return;
        }

        // Compat: tratar el delta como un movimiento completo (alta o baja neta).
        self::aplicarMovimiento($contexto, $deltaMonto, 1);
    }

    /**
     * @param  array{
     *   empresa_id: int|null,
     *   cuentacontable_id: int|null,
     *   centrocosto_id: int|null,
     *   fecha: mixed,
     *   moneda_id: int|null,
     *   monto?: float,
     *   cotizacion?: float|null
     * }  $contexto
     */
    public static function aplicarDeltas(
        array $contexto,
        float $deltaNeto,
        float $deltaLocal,
        float $deltaDebe,
        float $deltaHaber,
        float $deltaDebeLocal,
        float $deltaHaberLocal,
    ): void {
        if (! self::observerHabilitado()) {
            return;
        }

        if (
            abs($deltaNeto) < 1e-9
            && abs($deltaLocal) < 1e-9
            && abs($deltaDebe) < 1e-9
            && abs($deltaHaber) < 1e-9
            && abs($deltaDebeLocal) < 1e-9
            && abs($deltaHaberLocal) < 1e-9
        ) {
            return;
        }

        $empresaId = (int) ($contexto['empresa_id'] ?? 0);
        $cuentaId = (int) ($contexto['cuentacontable_id'] ?? 0);
        $monedaId = (int) ($contexto['moneda_id'] ?? 0);
        $anioMes = self::anioMesDesdeFecha($contexto['fecha'] ?? null);

        if ($empresaId <= 0 || $cuentaId <= 0 || $monedaId <= 0 || $anioMes <= 0) {
            return;
        }

        $centrocostoId = self::normalizarCentrocostoId($contexto['centrocosto_id'] ?? null);

        try {
            DB::transaction(function () use (
                $empresaId,
                $cuentaId,
                $centrocostoId,
                $anioMes,
                $monedaId,
                $deltaNeto,
                $deltaLocal,
                $deltaDebe,
                $deltaHaber,
                $deltaDebeLocal,
                $deltaHaberLocal,
            ) {
                $query = Cuentacontable_Saldo_Mes::query()
                    ->where('empresa_id', $empresaId)
                    ->where('cuentacontable_id', $cuentaId)
                    ->where('anio_mes', $anioMes)
                    ->where('moneda_id', $monedaId);

                if ($centrocostoId === null) {
                    $query->whereNull('centrocosto_id');
                } else {
                    $query->where('centrocosto_id', $centrocostoId);
                }

                $row = $query->lockForUpdate()->first();

                if ($row === null) {
                    Cuentacontable_Saldo_Mes::create([
                        'empresa_id' => $empresaId,
                        'cuentacontable_id' => $cuentaId,
                        'centrocosto_id' => $centrocostoId,
                        'anio_mes' => $anioMes,
                        'moneda_id' => $monedaId,
                        'debe' => $deltaDebe,
                        'haber' => $deltaHaber,
                        'debe_local' => $deltaDebeLocal,
                        'haber_local' => $deltaHaberLocal,
                        'monto' => $deltaNeto,
                        'monto_local' => $deltaLocal,
                    ]);

                    return;
                }

                $row->debe = (float) $row->debe + $deltaDebe;
                $row->haber = (float) $row->haber + $deltaHaber;
                $row->debe_local = (float) $row->debe_local + $deltaDebeLocal;
                $row->haber_local = (float) $row->haber_local + $deltaHaberLocal;
                $row->monto = (float) $row->monto + $deltaNeto;
                $row->monto_local = (float) $row->monto_local + $deltaLocal;
                $row->save();
            });
        } catch (\Throwable $e) {
            Log::error('CuentacontableSaldoMesSupport delta error', [
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
                'anio_mes' => $anioMes,
                'moneda_id' => $monedaId,
                'delta' => $deltaNeto,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{
     *   empresa_id: int|null,
     *   cuentacontable_id: int|null,
     *   centrocosto_id: int|null,
     *   fecha: mixed,
     *   moneda_id: int|null,
     *   monto: float,
     *   cotizacion: float|null
     * }
     */
    public static function contextoDesdeMovimiento(object $movimiento, ?object $asiento = null): array
    {
        $asiento = $asiento ?? ($movimiento->asientos ?? null);

        return [
            'empresa_id' => $asiento->empresa_id ?? null,
            'cuentacontable_id' => $movimiento->cuentacontable_id ?? null,
            'centrocosto_id' => $movimiento->centrocosto_id ?? null,
            'fecha' => $asiento->fecha ?? null,
            'moneda_id' => $movimiento->moneda_id ?? null,
            'monto' => (float) ($movimiento->monto ?? 0),
            'cotizacion' => isset($movimiento->cotizacion) ? (float) $movimiento->cotizacion : null,
        ];
    }

    public static function anioMesDesdeFecha(mixed $fecha): int
    {
        if ($fecha === null || $fecha === '') {
            return 0;
        }

        return (int) Carbon::parse($fecha)->format('Ym');
    }

    public static function normalizarCentrocostoId(mixed $centrocostoId): ?int
    {
        if ($centrocostoId === null || $centrocostoId === '' || $centrocostoId === 0 || $centrocostoId === '0') {
            return null;
        }

        return (int) $centrocostoId;
    }

    public static function convertirMontoLocal(float $monto, int $monedaId, mixed $cotizacion): float
    {
        if ($monto === 0.0) {
            return 0.0;
        }

        $monedaLocalId = self::monedaLocalId();
        if ($monedaId === $monedaLocalId) {
            return $monto;
        }

        $coef = calculaCoeficienteMoneda($monedaLocalId, $monedaId, $cotizacion ?? 1.0);

        return $monto * $coef;
    }
}
