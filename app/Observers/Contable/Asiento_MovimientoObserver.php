<?php

namespace App\Observers\Contable;

use App\Models\Contable\Asiento_Movimiento;
use App\Support\Contable\CuentacontableSaldoMesSupport;

/**
 * Mantiene on-line la tabla cuentacontable_saldo_mes ante cambios en asiento_movimiento.
 *
 * Reglas:
 *  - asiento_movimiento.monto ya viene firmado (debe positivo, haber negativo).
 *  - El mes se toma de asiento.fecha (no del timestamp del movimiento).
 *  - Se agrega por (empresa, cuenta, centro de costo, YYYYMM, moneda origen).
 *  - monto_local acumula el equivalente en moneda local (config contable).
 *  - debe/haber (+ _local) acumulan brutos del mes (Balance SyS por períodos).
 *
 * Activación: CONTABLE_SALDOS_CUENTA_MES_OBSERVER=true en .env
 *
 * Limitaciones conocidas (igual que articulo_saldo_deposito):
 *  - Borrados masivos vía DB::table o cascade FK no disparan eventos Eloquent.
 *  - Re-sincronización masiva desde Anita debe correr con observer deshabilitado
 *    y luego contable:reconstruir-saldos-cuenta-mes.
 */
class Asiento_MovimientoObserver
{
    public function created(Asiento_Movimiento $movimiento): void
    {
        if (! CuentacontableSaldoMesSupport::observerHabilitado()) {
            return;
        }

        $movimiento->loadMissing('asientos');
        $contexto = CuentacontableSaldoMesSupport::contextoDesdeMovimiento($movimiento);
        CuentacontableSaldoMesSupport::aplicarMovimiento($contexto, (float) $movimiento->monto, 1);
    }

    public function updated(Asiento_Movimiento $movimiento): void
    {
        if (! CuentacontableSaldoMesSupport::observerHabilitado()) {
            return;
        }

        $original = $movimiento->getOriginal();
        $movimiento->loadMissing('asientos');

        $contextoAnt = [
            'empresa_id' => $this->empresaIdDesdeAsientoId((int) ($original['asiento_id'] ?? 0)),
            'cuentacontable_id' => $original['cuentacontable_id'] ?? null,
            'centrocosto_id' => $original['centrocosto_id'] ?? null,
            'fecha' => $this->fechaAsientoDesdeId((int) ($original['asiento_id'] ?? 0)),
            'moneda_id' => $original['moneda_id'] ?? null,
            'monto' => isset($original['monto']) ? (float) $original['monto'] : 0.0,
            'cotizacion' => isset($original['cotizacion']) ? (float) $original['cotizacion'] : null,
        ];
        $contextoNew = CuentacontableSaldoMesSupport::contextoDesdeMovimiento($movimiento);

        // Siempre revierte el movimiento anterior y aplica el nuevo (mantiene debe/haber brutos).
        CuentacontableSaldoMesSupport::aplicarMovimiento($contextoAnt, (float) ($original['monto'] ?? 0), -1);
        CuentacontableSaldoMesSupport::aplicarMovimiento($contextoNew, (float) $movimiento->monto, 1);
    }

    public function deleted(Asiento_Movimiento $movimiento): void
    {
        if (! CuentacontableSaldoMesSupport::observerHabilitado()) {
            return;
        }

        $movimiento->loadMissing('asientos');
        $contexto = CuentacontableSaldoMesSupport::contextoDesdeMovimiento($movimiento);
        CuentacontableSaldoMesSupport::aplicarMovimiento($contexto, (float) $movimiento->monto, -1);
    }

    public function restored(Asiento_Movimiento $movimiento): void
    {
        $this->created($movimiento);
    }

    private function empresaIdDesdeAsientoId(int $asientoId): ?int
    {
        if ($asientoId <= 0) {
            return null;
        }

        $asiento = \App\Models\Contable\Asiento::query()->find($asientoId);

        return $asiento?->empresa_id;
    }

    private function fechaAsientoDesdeId(int $asientoId): mixed
    {
        if ($asientoId <= 0) {
            return null;
        }

        $asiento = \App\Models\Contable\Asiento::query()->find($asientoId);

        return $asiento?->fecha;
    }
}
