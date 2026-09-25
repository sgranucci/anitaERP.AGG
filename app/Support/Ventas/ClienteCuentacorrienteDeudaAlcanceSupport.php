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
        $query->where(function (Builder $alcance) {
            $alcance->where(function (Builder $deudaVenta) {
                $deudaVenta
                    ->whereRaw(SqlDialectSupport::sqlSinCobranzaClienteCc())
                    ->whereHas('ventas', function (Builder $venta) {
                        $venta->where(function (Builder $tipos) {
                            foreach (self::PREFIJOS_VENTA as $prefijo) {
                                $tipos->orWhere('codigo', 'like', $prefijo.'%');
                            }
                        });
                    });
            })->orWhereHas('cobranzas.tipotransaccioncajas', function (Builder $tipo) {
                $tipo->whereRaw('UPPER(TRIM(abreviatura)) = ?', ['COA']);
            });
        });
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
