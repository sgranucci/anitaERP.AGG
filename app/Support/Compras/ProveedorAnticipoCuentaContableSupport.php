<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;

/**
 * Cuenta de anticipos a proveedores (clave global `pago.anticipo_proveedor`).
 *
 * Con cuenta configurada, la OP adelantada imputa el Debe a esa cuenta en lugar de a
 * proveedores; al aplicar ese anticipo contra la factura hace falta el contraasiento
 * Debe proveedores / Haber anticipos. Sin cuenta configurada el anticipo ya nació en la
 * cuenta de proveedores y la aplicación no reclasifica nada.
 */
final class ProveedorAnticipoCuentaContableSupport
{
    public static function cuentaAnticipoId(int $empresaId): ?int
    {
        if ($empresaId <= 0) {
            return null;
        }

        return CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::PAGO_ANTICIPO_PROVEEDOR
        );
    }

    /**
     * Crédito de cuenta corriente nacido de una OP y no de un comprobante:
     * OPA adelantada o sobrepago de OPP. Una NC siempre trae comprobante.
     */
    public static function esCreditoAnticipo(Proveedor_Cuentacorriente $credito): bool
    {
        return (float) $credito->total < 0
            && (int) ($credito->pagoproveedor_id ?? 0) > 0
            && (int) ($credito->comprobante_proveedor_id ?? 0) <= 0;
    }

    /**
     * Cuenta del lado crédito en el asiento de aplicación: la de anticipos cuando el
     * crédito es un anticipo y la empresa la tiene configurada; null cuando sigue
     * correspondiendo la cuenta de proveedores.
     */
    public static function cuentaParaCreditoAplicado(Proveedor_Cuentacorriente $credito): ?int
    {
        if (! self::esCreditoAnticipo($credito)) {
            return null;
        }

        return self::cuentaAnticipoId((int) $credito->empresa_id);
    }
}
