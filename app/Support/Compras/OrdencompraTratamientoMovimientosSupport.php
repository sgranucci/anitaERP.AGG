<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Support\Facades\Schema;

/**
 * Regla: no cambiar tratamiento NO ANTICIPADA ↔ ANTICIPADA si la OC ya tiene
 * recepción (COM) o factura (comprobante proveedor) asociada.
 *
 * Excepción: pasar a ANTICIPADA cuando el anticipo ya está contabilizado sin COM
 * y hay como máximo una recepción (la COM del total, caso 50/50). Con varias
 * recepciones el anticipo se restaría de cada COM, así que el cambio sigue bloqueado.
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

    public static function hayMovimientosQueBloqueanCambio(int $ordencompraId): bool
    {
        if ($ordencompraId <= 0) {
            return false;
        }

        if (Recepcion_Proveedor::query()->where('ordencompra_id', $ordencompraId)->exists()) {
            return true;
        }

        return Comprobante_Proveedor::query()->where('ordencompra_id', $ordencompraId)->exists();
    }

    /**
     * El formulario puede ofrecer ANTICIPADA aunque ya haya documentos,
     * solo en el caso del anticipo contabilizado sin COM y una sola recepción.
     *
     * @return array{bloqueado: bool, permite_pasar_a_anticipada: bool}
     */
    public static function estadoEdicion(int $ordencompraId, ?string $tratamientoActual): array
    {
        $hayMovimientos = self::hayMovimientosQueBloqueanCambio($ordencompraId);
        $permite = $hayMovimientos && self::permitePasarAAnticipada(
            $tratamientoActual,
            'ANTICIPADA',
            self::cantidadRecepciones($ordencompraId),
            self::cantidadFacturasContabilizadasSinCom($ordencompraId),
        );

        return [
            'bloqueado' => $hayMovimientos && ! $permite,
            'permite_pasar_a_anticipada' => $permite,
        ];
    }

    /**
     * True solo al pasar de no anticipada a anticipada, con a lo sumo una COM
     * y al menos una factura contabilizada que no está imputada a recepción.
     */
    public static function permitePasarAAnticipada(
        ?string $tratamientoActual,
        ?string $tratamientoNuevo,
        int $cantidadRecepciones,
        int $cantidadFacturasContabilizadasSinCom,
    ): bool {
        if (self::esTratamientoAnticipado($tratamientoActual) || ! self::esTratamientoAnticipado($tratamientoNuevo)) {
            return false;
        }
        if (self::normalizar($tratamientoActual) === self::normalizar($tratamientoNuevo)) {
            return false;
        }

        return $cantidadRecepciones <= 1 && $cantidadFacturasContabilizadasSinCom > 0;
    }

    public static function mensajeBloqueoCambio(): string
    {
        return 'No se puede cambiar el tratamiento entre NO ANTICIPADA y ANTICIPADA '
            .'porque la orden de compra ya tiene recepción o factura asociada.';
    }

    public static function mensajePermitePasarAAnticipada(): string
    {
        return 'Se puede pasar a ANTICIPADA: hay un anticipo contabilizado sin COM y una sola recepción.';
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function assertPuedeCambiarTratamiento(
        int $ordencompraId,
        ?string $tratamientoActual,
        ?string $tratamientoNuevo,
    ): void {
        if (self::normalizar($tratamientoActual) === self::normalizar($tratamientoNuevo)) {
            return;
        }

        if (! self::hayMovimientosQueBloqueanCambio($ordencompraId)) {
            return;
        }

        if (self::permitePasarAAnticipada(
            $tratamientoActual,
            $tratamientoNuevo,
            self::cantidadRecepciones($ordencompraId),
            self::cantidadFacturasContabilizadasSinCom($ordencompraId),
        )) {
            return;
        }

        throw new \InvalidArgumentException(self::mensajeBloqueoCambio());
    }

    public static function cantidadRecepciones(int $ordencompraId): int
    {
        if ($ordencompraId <= 0) {
            return 0;
        }

        return (int) Recepcion_Proveedor::query()->where('ordencompra_id', $ordencompraId)->count();
    }

    public static function cantidadFacturasContabilizadasSinCom(int $ordencompraId): int
    {
        if ($ordencompraId <= 0) {
            return 0;
        }

        $query = Comprobante_Proveedor::query()
            ->where('ordencompra_id', $ordencompraId)
            ->where(function ($q) {
                $q->where('estado', ComprobanteProveedorEstados::CONTABILIZADO)
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('asiento_id')->where('asiento_id', '>', 0);
                    });
            })
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', [ComprobanteProveedorEstados::ANULADO]);
            });

        if (Schema::hasTable('comprobante_proveedor_recepcion')) {
            $query->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('comprobante_proveedor_recepcion')
                    ->whereColumn(
                        'comprobante_proveedor_recepcion.comprobante_proveedor_id',
                        'comprobante_proveedor.id'
                    );
            });
        }

        return (int) $query->count();
    }
}
