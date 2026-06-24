<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente_Cuentacorriente;

final class ClienteCuentacorrienteGrillaSupport
{
    public static function etiquetaComprobante(Cliente_Cuentacorriente $fila): string
    {
        if ((int) ($fila->cobranza_id ?? 0) > 0 && $fila->cobranzas) {
            return (string) ($fila->cobranzas->detalle ?? 'Cobranza');
        }

        if ((int) ($fila->venta_id ?? 0) > 0 && $fila->ventas) {
            return (string) ($fila->ventas->codigo ?? 'Comprobante');
        }

        return 'Movimiento #' . (int) $fila->id;
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
    public static function destinoImpresion(Cliente_Cuentacorriente $fila): ?array
    {
        if ((int) ($fila->venta_id ?? 0) > 0) {
            return [
                'tipo' => 'venta',
                'id' => (int) $fila->venta_id,
                'titulo' => 'Imprimir comprobante de venta',
            ];
        }

        if ((int) ($fila->cobranza_id ?? 0) > 0) {
            return [
                'tipo' => 'cobranza',
                'id' => (int) $fila->cobranza_id,
                'titulo' => 'Imprimir comprobante de cobranza',
            ];
        }

        return null;
    }

    public static function urlImpresion(Cliente_Cuentacorriente $fila): ?string
    {
        $destino = self::destinoImpresion($fila);
        if ($destino === null) {
            return null;
        }

        if ($destino['tipo'] === 'venta') {
            return route('lista_una_factura', ['id' => $destino['id']]);
        }

        return route('listar_una_cobranza', ['id' => $destino['id']]);
    }

    public static function puedeImprimirComprobante(Cliente_Cuentacorriente $fila): bool
    {
        $destino = self::destinoImpresion($fila);
        if ($destino === null) {
            return false;
        }

        if ($destino['tipo'] === 'venta') {
            return can('listar-factura', false);
        }

        return can('listar-cobranza', false);
    }
}
