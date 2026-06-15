<?php

namespace App\Support\Contable;

use App\Models\Contable\Cuentacontable_Saldo_Mes;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aplica deltas sobre cuentacontable_saldo_mes (agregado mensual por moneda origen).
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
    public static function aplicarDelta(array $contexto, float $deltaMonto): void
    {
        if (! self::observerHabilitado() || abs($deltaMonto) < 1e-9) {
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
        $deltaLocal = self::convertirMontoLocal($deltaMonto, $monedaId, $contexto['cotizacion'] ?? null);

        try {
            DB::transaction(function () use (
                $empresaId,
                $cuentaId,
                $centrocostoId,
                $anioMes,
                $monedaId,
                $deltaMonto,
                $deltaLocal,
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
                        'monto' => $deltaMonto,
                        'monto_local' => $deltaLocal,
                    ]);

                    return;
                }

                $row->monto = (float) $row->monto + $deltaMonto;
                $row->monto_local = (float) $row->monto_local + $deltaLocal;
                $row->save();
            });
        } catch (\Throwable $e) {
            Log::error('CuentacontableSaldoMesSupport delta error', [
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
                'anio_mes' => $anioMes,
                'moneda_id' => $monedaId,
                'delta' => $deltaMonto,
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
