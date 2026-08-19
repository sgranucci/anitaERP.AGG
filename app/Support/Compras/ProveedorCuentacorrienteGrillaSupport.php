<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor_Cuentacorriente;

final class ProveedorCuentacorrienteGrillaSupport
{
    public static function etiquetaComprobante(Proveedor_Cuentacorriente $fila): string
    {
        if ((int) ($fila->comprobante_proveedor_id ?? 0) > 0 && $fila->comprobante_proveedores) {
            $comprobante = $fila->comprobante_proveedores;
            $tipo = $comprobante->tipotransaccion_compras->nombre ?? 'Comprobante';

            return trim($tipo.' '.$comprobante->letra.$comprobante->sucursal.'-'.$comprobante->numerocomprobante);
        }

        if ((int) ($fila->pagoproveedor_id ?? 0) > 0 && $fila->pagoproveedores) {
            return $fila->pagoproveedores->etiquetaComprobante();
        }

        return 'Movimiento #'.(int) $fila->id;
    }

    public static function saldoPendiente(float $total, ?float $aplicado): float
    {
        $aplicadoSum = (float) ($aplicado ?? 0);

        if ($total >= 0) {
            return max(0, $total + $aplicadoSum);
        }

        return min(0, $total + $aplicadoSum);
    }

    public static function saldoPendienteAbsoluto(float $total, ?float $aplicado): float
    {
        return abs(self::saldoPendiente($total, $aplicado));
    }

    /**
     * @return array{tipo: string, id: int, titulo: string}|null
     */
    public static function destinoImpresion(Proveedor_Cuentacorriente $fila): ?array
    {
        if ((int) ($fila->comprobante_proveedor_id ?? 0) <= 0 || ! $fila->comprobante_proveedores) {
            return null;
        }

        if (ComprobanteProveedorArchivoPathSupport::referenciaPdfPrecarga($fila->comprobante_proveedores) === null) {
            return null;
        }

        return [
            'tipo' => 'comprobante_proveedor',
            'id' => (int) $fila->comprobante_proveedor_id,
            'titulo' => 'Ver PDF original de la precarga',
        ];
    }

    public static function urlImpresion(Proveedor_Cuentacorriente $fila): ?string
    {
        $destino = self::destinoImpresion($fila);
        if ($destino === null) {
            return null;
        }

        return route('comprobante_proveedor_factura_pdf', [
            'id' => $destino['id'],
            'inline' => 1,
        ]);
    }

    public static function puedeImprimirComprobante(Proveedor_Cuentacorriente $fila): bool
    {
        if (self::destinoImpresion($fila) === null) {
            return false;
        }

        return can('editar-comprobante-proveedor', false)
            || can('listar-comprobante-proveedor', false)
            || can('listar-cuentacorriente-proveedor', false);
    }
}
