<?php

namespace App\Support\Compras;

use Illuminate\Database\Eloquent\Builder;

/**
 * Una validación de abono solo vale si sigue existiendo la COM o la factura.
 * Si se borró el documento (borrador de recepción, etc.) el registro queda huérfano
 * y no puede bloquear el envío a Cuentas a pagar.
 */
final class ContratoValidacionAbonoDocumentoSupport
{
    public static function esHuerfana(
        ?int $recepcionId,
        bool $recepcionExiste,
        ?int $comprobanteId,
        bool $comprobanteExiste,
    ): bool {
        $rp = (int) ($recepcionId ?? 0);
        $cp = (int) ($comprobanteId ?? 0);
        if ($rp > 0) {
            return ! $recepcionExiste;
        }
        if ($cp > 0) {
            return ! $comprobanteExiste;
        }

        return true;
    }

    public static function filtrarVivas(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where(function (Builder $q2) {
                $q2->whereNotNull('recepcion_proveedor_id')
                    ->where('recepcion_proveedor_id', '>', 0)
                    ->whereExists(function ($s) {
                        $s->selectRaw('1')
                            ->from('recepcion_proveedor')
                            ->whereColumn(
                                'recepcion_proveedor.id',
                                'contrato_validacion_abono.recepcion_proveedor_id'
                            );
                    });
            })->orWhere(function (Builder $q2) {
                $q2->whereNotNull('comprobante_proveedor_id')
                    ->where('comprobante_proveedor_id', '>', 0)
                    ->whereExists(function ($s) {
                        $s->selectRaw('1')
                            ->from('comprobante_proveedor')
                            ->whereColumn(
                                'comprobante_proveedor.id',
                                'contrato_validacion_abono.comprobante_proveedor_id'
                            );
                    });
            });
        });
    }
}
