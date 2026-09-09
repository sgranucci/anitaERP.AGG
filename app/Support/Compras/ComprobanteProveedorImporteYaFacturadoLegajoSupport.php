<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorImputacionApSupport;
use Illuminate\Support\Collection;

/**
 * Remanente de provisión COM descontando facturas ya cargadas en el mismo legajo.
 *
 * Equivalente de cabecera a a-compprov.c → lee_recepcion():
 *   cantidad disponible = recv_cantidad − cantfact (aplicped)
 *   _total_facturado acumula lo ya aplicado antes de comparar con la factura actual.
 *
 * Sin este descuento, un anticipo 50/50 (1ª FC anticipada + 2ª contra COM) compara
 * solo la 2ª mitad contra la COM completa y dispara un falso “fuera de tolerancia”.
 */
final class ComprobanteProveedorImporteYaFacturadoLegajoSupport
{
    /**
     * @return array{
     *     importe: float,
     *     cantidad: int,
     *     items: list<array{id: int, etiqueta: string, importe: float, signo: string}>
     * }
     */
    public static function sumarComparableEnLegajo(
        int $ordencompraId,
        ?int $excluirComprobanteId = null,
    ): array {
        $vacio = ['importe' => 0.0, 'cantidad' => 0, 'items' => []];
        if ($ordencompraId <= 0) {
            return $vacio;
        }

        $query = Comprobante_Proveedor::query()
            ->with([
                'comprobante_proveedor_conceptos.concepto_ivacompras',
                'tipotransaccion_compras:id,signo,abreviatura',
                'proveedores:id,condicioniva_id',
            ])
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

        if ($excluirComprobanteId !== null && $excluirComprobanteId > 0) {
            $query->where('id', '!=', $excluirComprobanteId);
        }

        /** @var Collection<int, Comprobante_Proveedor> $rows */
        $rows = $query->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return $vacio;
        }

        $importe = 0.0;
        $items = [];
        foreach ($rows as $cp) {
            $signo = (string) ($cp->tipotransaccion_compras->signo ?? 'S');
            $esNc = ComprobanteProveedorImputacionApSupport::esNotaCredito($signo);
            $condicionIva = isset($cp->proveedores)
                ? (int) ($cp->proveedores->condicioniva_id ?? 0)
                : null;
            if ($condicionIva !== null && $condicionIva <= 0) {
                $condicionIva = null;
            }

            $meta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
                (string) ($cp->letra ?? ''),
                $condicionIva,
                (float) ($cp->total ?? 0),
                (float) ($cp->subtotal ?? 0),
                $cp->comprobante_proveedor_conceptos ?? [],
            );
            $monto = round((float) $meta['importe'], 2);
            if ($esNc) {
                $monto = -abs($monto);
            } else {
                $monto = abs($monto);
            }
            $importe += $monto;
            $items[] = [
                'id' => (int) $cp->id,
                'etiqueta' => trim(sprintf(
                    '%s %04d-%08d',
                    $cp->letra ?: 'FC',
                    (int) ($cp->sucursal ?? 0),
                    (int) ($cp->numerocomprobante ?? 0)
                )),
                'importe' => $monto,
                'signo' => $esNc ? 'R' : 'S',
            ];
        }

        return [
            'importe' => round($importe, 2),
            'cantidad' => count($items),
            'items' => $items,
        ];
    }

    /**
     * Provisión COM aún disponible para la factura actual (no negativa).
     */
    public static function provisionDisponible(float $provisionCom, float $yaFacturadoComparable): float
    {
        return max(0.0, round($provisionCom - $yaFacturadoComparable, 2));
    }
}
