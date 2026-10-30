<?php

namespace App\Observers\Contable;

use App\Models\Contable\Asiento;
use App\Support\Contable\CuentacontableSaldoMesSupport;

/**
 * Antes de borrar un asiento, elimina cada línea vía Eloquent para que
 * Asiento_MovimientoObserver actualice cuentacontable_saldo_mes.
 *
 * La FK asiento_movimiento.asiento_id es RESTRICT (sin CASCADE): MySQL no
 * borra las líneas en silencio; deben eliminarse aquí primero.
 *
 * Si cambia la fecha o la empresa, el saldo mensual tiene que seguir a la
 * cabecera. El observer de la línea no puede hacerlo: al guardar, la cabecera
 * ya está actualizada y la reversa caería en el mes nuevo.
 */
class AsientoObserver
{
    /**
     * Laravel resuelve un observer nuevo por evento. Si esto no es estático,
     * updated() no ve lo que guardó updating() y el saldo mensual no sigue a la fecha.
     *
     * @var array<int, array{fecha: mixed, empresa_id: int}>
     */
    private static array $cabeceraAnterior = [];

    public function updating(Asiento $asiento): void
    {
        if (! $asiento->isDirty('fecha') && ! $asiento->isDirty('empresa_id')) {
            return;
        }

        $id = (int) $asiento->getKey();
        if ($id <= 0) {
            return;
        }

        self::$cabeceraAnterior[$id] = [
            'fecha' => $asiento->getOriginal('fecha'),
            'empresa_id' => (int) $asiento->getOriginal('empresa_id'),
        ];
    }

    public function updated(Asiento $asiento): void
    {
        $id = (int) $asiento->getKey();
        if (! isset(self::$cabeceraAnterior[$id])) {
            return;
        }

        $anterior = self::$cabeceraAnterior[$id];
        unset(self::$cabeceraAnterior[$id]);

        if (! CuentacontableSaldoMesSupport::observerHabilitado()) {
            return;
        }

        $fechaNueva = $asiento->fecha;
        $empresaNueva = (int) $asiento->empresa_id;
        $mismoMes = CuentacontableSaldoMesSupport::anioMesDesdeFecha($anterior['fecha'])
            === CuentacontableSaldoMesSupport::anioMesDesdeFecha($fechaNueva);
        $mismaEmpresa = (int) $anterior['empresa_id'] === $empresaNueva;

        if ($mismoMes && $mismaEmpresa) {
            return;
        }

        if ($anterior['fecha'] === null || $anterior['fecha'] === '' || $fechaNueva === null || $fechaNueva === '') {
            return;
        }

        foreach ($asiento->asiento_movimientos()->get() as $movimiento) {
            $base = CuentacontableSaldoMesSupport::contextoDesdeMovimiento($movimiento, $asiento);
            $viejo = $base;
            $viejo['fecha'] = $anterior['fecha'];
            $viejo['empresa_id'] = $anterior['empresa_id'];
            $nuevo = $base;
            $nuevo['fecha'] = $fechaNueva;
            $nuevo['empresa_id'] = $empresaNueva;

            $monto = (float) $movimiento->monto;
            CuentacontableSaldoMesSupport::aplicarMovimiento($viejo, $monto, -1);
            CuentacontableSaldoMesSupport::aplicarMovimiento($nuevo, $monto, 1);
        }
    }

    public function deleting(Asiento $asiento): void
    {
        foreach ($asiento->asiento_movimientos()->get() as $movimiento) {
            $movimiento->delete();
        }
    }
}
