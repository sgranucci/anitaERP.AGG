<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor_Cuentacorriente;

/**
 * Signo de una fila al aplicar en OP.
 *
 * Factura / ND (CC > 0) suman al desembolso y a las bases de retención.
 * NC (CC < 0 con comprobante) restan del desembolso y de las bases.
 * OPA / anticipo (CC < 0 nacido de un pago, sin comprobante) restan del
 * desembolso pero no entran a retenciones: ya se retuvo al generarlas.
 */
final class PagoproveedorAplicacionLadoSupport
{
    public static function signo(Proveedor_Cuentacorriente $cc): int
    {
        return (float) $cc->total < 0 ? -1 : 1;
    }

    public static function esCredito(Proveedor_Cuentacorriente $cc): bool
    {
        return self::signo($cc) < 0;
    }

    public static function esOpa(Proveedor_Cuentacorriente $cc): bool
    {
        return ProveedorAnticipoCuentaContableSupport::esCreditoAnticipo($cc);
    }

    /**
     * Las OPA no vuelven a calcular Ganancias / IVA / SUSS / IIBB.
     */
    public static function afectaRetenciones(Proveedor_Cuentacorriente $cc): bool
    {
        return ! self::esOpa($cc);
    }

    /**
     * Importes firmados al aplicar un monto de OP sobre esta CC.
     *
     * Factura / ND (CC > 0): la OP abre Haber y consume la deuda con aplicación negativa.
     * NC / OPA (CC < 0): la OP abre Debe y consume el crédito con aplicación positiva.
     * Si la OP también abriera Haber sobre una NC, el listado duplicaría el crédito.
     *
     * @return array{total_fila_pago: float, apl_documento: float, apl_fila_pago: float}
     */
    public static function importesAplicacionOp(Proveedor_Cuentacorriente $cc, float $monto): array
    {
        $monto = round(abs($monto), 4);
        $signo = self::signo($cc);

        return [
            'total_fila_pago' => round(-$signo * $monto, 4),
            'apl_documento' => round(-$signo * $monto, 4),
            'apl_fila_pago' => round($signo * $monto, 4),
        ];
    }
}
