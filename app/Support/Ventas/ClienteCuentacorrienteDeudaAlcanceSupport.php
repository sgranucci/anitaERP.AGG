<?php

namespace App\Support\Ventas;

use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Que entra en el modo deuda de cuenta corriente de clientes.
 *
 * Solo comprobantes de deuda/credito comercial pendiente:
 * facturas (FA*), notas de credito (NC* y CIM), notas de debito (ND* y DIM),
 * ticket factura (TFC) y cobros adelantados (COA).
 *
 * No entran COB, AJU, APA, RIN, remitos ni otros movimientos de aplicacion.
 * Las filas de CC con cobranza_id (imputación de cobro sobre una FAC) tampoco:
 * la deuda se reduce por aplicaciones, no listando esa fila negativa.
 */
final class ClienteCuentacorrienteDeudaAlcanceSupport
{
    /**
     * Prefijos de venta.codigo (primeros caracteres antes del espacio).
     *
     * @var list<string>
     */
    public const PREFIJOS_VENTA = [
        'FA',  // FAC, FAE, FAR, FAF, FAI, FAJ, FAN, FAS, FCR…
        'NC',  // NCA…NCR
        'ND',  // NDA…NDV
        'COA',
        'TFC',
        'CIM',
        'DIM',
    ];

    /**
     * Restringe el query a filas que cuentan como deuda.
     *
     * @param  Builder<\App\Models\Ventas\Cliente_Cuentacorriente>  $query
     */
    public static function aplicar(Builder $query): void
    {
        $query->whereRaw(self::sql('cliente_cuentacorriente'));
    }

    /**
     * Un ajuste de cuenta corriente no tiene factura ni COA, así que no entra.
     */
    public static function sql(string $alias = 'cliente_cuentacorriente'): string
    {
        $likes = [];
        foreach (self::PREFIJOS_VENTA as $prefijo) {
            $likes[] = "v.codigo LIKE '".$prefijo."%'";
        }

        $sinCobranza = SqlDialectSupport::sqlSinCobranzaClienteCc($alias.'.cobranza_id');

        return '('
            .'('.$sinCobranza
            .' AND EXISTS (SELECT 1 FROM venta v WHERE v.id = '.$alias.'.venta_id AND ('
            .implode(' OR ', $likes).')))'
            .' OR EXISTS (SELECT 1 FROM cobranza cob'
            .' INNER JOIN tipotransaccion_caja ttc ON ttc.id = cob.tipotransaccion_caja_id'
            .' WHERE cob.id = '.$alias.'.cobranza_id AND UPPER(TRIM(ttc.abreviatura)) = \'COA\')'
            .')';
    }

    public static function esComprobanteDeuda(?string $codigoVenta, ?string $abreviaturaCobranza = null): bool
    {
        $codigo = strtoupper(trim((string) $codigoVenta));
        if ($codigo !== '') {
            foreach (self::PREFIJOS_VENTA as $prefijo) {
                if (str_starts_with($codigo, $prefijo)) {
                    return true;
                }
            }
        }

        return strtoupper(trim((string) $abreviaturaCobranza)) === 'COA';
    }
}
