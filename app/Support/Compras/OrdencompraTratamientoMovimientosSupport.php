<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorEstados;

/**
 * Regla: no cambiar tratamiento NO ANTICIPADA ↔ ANTICIPADA si la OC ya tiene
 * recepción (COM) o factura (comprobante proveedor) asociada, en el ERP o en Anita.
 *
 * Sin excepciones: el tratamiento define cómo se contabilizó cada documento
 * (FA a anticipos, COM contra anticipos o provisión). Cambiarlo después deja
 * documentos ya asentados con la lógica del otro tratamiento.
 * Los documentos anulados no cuentan.
 */
final class OrdencompraTratamientoMovimientosSupport
{
    public static function normalizar(?string $tratamiento): string
    {
        return strtoupper(trim((string) $tratamiento));
    }

    public static function esTratamientoAnticipado(?string $tratamiento): bool
    {
        $t = self::normalizar($tratamiento);

        return $t === 'ANTICIPADA' || $t === '2' || $t === 'S';
    }

    /**
     * Solo ERP (rápido, para armar el formulario).
     */
    public static function hayMovimientosQueBloqueanCambio(int $ordencompraId): bool
    {
        if ($ordencompraId <= 0) {
            return false;
        }

        $hayRecepcion = Recepcion_Proveedor::query()
            ->where('ordencompra_id', $ordencompraId)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhere('estado', '!=', RecepcionProveedorEstados::ANULADA);
            })
            ->exists();
        if ($hayRecepcion) {
            return true;
        }

        return Comprobante_Proveedor::query()
            ->where('ordencompra_id', $ordencompraId)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', [ComprobanteProveedorEstados::ANULADO]);
            })
            ->exists();
    }

    /**
     * @return array{bloqueado: bool}
     */
    public static function estadoEdicion(int $ordencompraId): array
    {
        return ['bloqueado' => self::hayMovimientosQueBloqueanCambio($ordencompraId)];
    }

    public static function mensajeBloqueoCambio(): string
    {
        return 'No se puede cambiar el tratamiento entre NO ANTICIPADA y ANTICIPADA '
            .'porque la orden de compra ya tiene recepción o factura asociada.';
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function assertPuedeCambiarTratamiento(
        int $ordencompraId,
        ?string $tratamientoActual,
        ?string $tratamientoNuevo,
        int $numeroOrdencompra = 0,
    ): void {
        if (self::esTratamientoAnticipado($tratamientoActual) === self::esTratamientoAnticipado($tratamientoNuevo)) {
            return;
        }

        if (self::hayMovimientosQueBloqueanCambio($ordencompraId)) {
            throw new \InvalidArgumentException(self::mensajeBloqueoCambio());
        }

        if (OrdencompraAplicpedAnitaSupport::tieneComprobantes($numeroOrdencompra)) {
            throw new \InvalidArgumentException(
                self::mensajeBloqueoCambio().' (comprobantes aplicados en Anita).'
            );
        }
    }
}
