<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Comprobante;
use App\Models\Compras\Requisicion;

/**
 * Cadena inversa OP → facturas aplicadas → OC / requisición / COM + URLs de consulta y PDF.
 */
final class PagoproveedorDocumentosRelacionadosSupport
{
    /**
     * @return array{
     *   pagoproveedor_id: int,
     *   etiqueta_op: string,
     *   filas: list<array<string, mixed>>
     * }
     */
    public static function armar(Pagoproveedor $pago): array
    {
        $aplicaciones = Pagoproveedor_Comprobante::query()
            ->where('pagoproveedor_id', (int) $pago->id)
            ->with([
                'monedas:id,abreviatura',
                'proveedor_cuentacorrientes.monedas:id,abreviatura',
                'proveedor_cuentacorrientes.pagoproveedores',
                'proveedor_cuentacorrientes.comprobante_proveedor_cuotas',
                'proveedor_cuentacorrientes.comprobante_proveedores.tipotransaccion_compras',
                'proveedor_cuentacorrientes.comprobante_proveedores.comprobante_proveedor_cuotas',
                'proveedor_cuentacorrientes.comprobante_proveedores.ordencompras.requisiciones',
                'proveedor_cuentacorrientes.comprobante_proveedores.recepcion_proveedores',
            ])
            ->orderBy('id')
            ->get();

        $filas = [];
        foreach ($aplicaciones as $apl) {
            $fila = self::filaDesdeAplicacion($apl);
            if ($fila !== null) {
                $filas[] = $fila;
            }
        }

        return [
            'pagoproveedor_id' => (int) $pago->id,
            'etiqueta_op' => $pago->etiquetaComprobante(),
            'filas' => $filas,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function filaDesdeAplicacion(Pagoproveedor_Comprobante $apl): ?array
    {
        $cc = $apl->proveedor_cuentacorrientes;
        if ($cc === null) {
            return null;
        }

        $comp = $cc->comprobante_proveedores;
        $monto = abs((float) $apl->montoaplicado);
        $moneda = (string) ($apl->monedas?->abreviatura ?? $cc->monedas?->abreviatura ?? '');

        $fila = [
            'pagoproveedor_comprobante_id' => (int) $apl->id,
            'proveedor_cuentacorriente_id' => (int) $cc->id,
            'etiqueta' => ProveedorCuentacorrienteGrillaSupport::etiquetaComprobanteAbreviado($cc),
            'fecha' => optional($cc->fecha)->format('d/m/Y') ?: '',
            'monto_aplicado' => $monto,
            'monto_aplicado_fmt' => number_format($monto, 2, ',', '.').($moneda !== '' ? ' '.$moneda : ''),
            'es_opa' => PagoproveedorAplicacionLadoSupport::esOpa($cc),
            'factura' => null,
            'ordencompra' => null,
            'requisicion' => null,
            'coms' => [],
        ];

        if ($comp instanceof Comprobante_Proveedor) {
            $fila['factura'] = CircuitoComprasDocumentosRelacionadosSupport::bloqueFactura($comp);
            $oc = $comp->ordencompras;
            if ($oc instanceof Ordencompra) {
                $fila['ordencompra'] = CircuitoComprasDocumentosRelacionadosSupport::bloqueOrdencompra($oc);
                $req = $oc->requisiciones;
                if ($req instanceof Requisicion) {
                    $fila['requisicion'] = CircuitoComprasDocumentosRelacionadosSupport::bloqueRequisicion($req);
                }
            }
            $fila['coms'] = CircuitoComprasDocumentosRelacionadosSupport::bloquesComDesdeColeccion(
                $comp->recepcion_proveedores
            );
        }

        return $fila;
    }
}
