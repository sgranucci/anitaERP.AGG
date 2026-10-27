<?php

namespace App\Support\Compras;

use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Support\Database\EloquentAuditDeleteSupport;
use RuntimeException;

/**
 * OPA hermana que guarda el pago de más de una OPP (mismo número, sin caja ni asiento).
 * Vive y muere con su OPP: se borra al revertirla o anularla, salvo que ya esté aplicada.
 */
final class PagoproveedorOpaSobranteSupport
{
    public static function hija(Pagoproveedor $opp): ?Pagoproveedor
    {
        if ((int) $opp->id <= 0) {
            return null;
        }

        return Pagoproveedor::query()
            ->where('pagoproveedor_origen_id', (int) $opp->id)
            ->where('tipocomprobante', 'OPA')
            ->first();
    }

    public static function assertSinAplicar(Pagoproveedor $opa): void
    {
        $aplicada = Proveedor_Cuentacorriente_Aplicacion::query()
            ->where('pagoproveedor_id', (int) $opa->id)
            ->exists();
        if ($aplicada || (int) ($opa->caja_movimiento_id ?? 0) > 0 || (int) ($opa->asiento_id ?? 0) > 0) {
            throw new RuntimeException(
                'La OPA '.$opa->sucursal.'-'.$opa->numerotransaccion
                .' del sobrante ya tiene aplicaciones, caja o asiento. Desaplíquela antes de rehacer, revertir o anular la OPP.'
            );
        }
    }

    /**
     * Borra la OPA y su renglón de cuenta corriente en el ERP. Anita lo borra el caller.
     */
    public static function borrarLocal(Pagoproveedor $opa): void
    {
        self::assertSinAplicar($opa);
        EloquentAuditDeleteSupport::each(
            Proveedor_Cuentacorriente::query()->where('pagoproveedor_id', (int) $opa->id)
        );
        EloquentAuditDeleteSupport::each(
            Pagoproveedor_Estado::query()->where('pagoproveedor_id', (int) $opa->id)
        );
        $opa->delete();
    }
}
