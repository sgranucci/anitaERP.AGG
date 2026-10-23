<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente_Cuentacorriente;

final class ClienteCuentacorrienteGrillaSupport
{
    public const PREFIJO_LEYENDA_AJUSTE = 'Ajuste saldo CC ';

    public static function etiquetaComprobante(Cliente_Cuentacorriente $fila): string
    {
        $leyenda = trim((string) ($fila->leyenda ?? ''));
        if ($leyenda !== '' && str_starts_with($leyenda, self::PREFIJO_LEYENDA_AJUSTE)
            && (int) ($fila->venta_id ?? 0) === 0
            && (int) ($fila->cobranza_id ?? 0) === 0) {
            return $leyenda;
        }

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
     * Tope al aplicar un crédito sobre un comprobante de deuda.
     * Si la factura ya está saldada, devuelve 0: el crédito queda abierto
     * y sigue en la deuda. Aplicarlo igual deja la ficha por debajo de la deuda.
     */
    public static function montoAplicableSobreDeuda(float $totalDeuda, float $aplicado, float $importeCredito): float
    {
        $pendiente = self::saldoPendiente($totalDeuda, $aplicado);
        if ($pendiente <= 0) {
            return 0.0;
        }

        return round(min(abs($importeCredito), $pendiente), 2);
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

    /**
     * Destino de edición del movimiento (cobranza o factura). Prioriza cobranza.
     *
     * @return array{tipo: string, id: int, titulo: string, route: string}|null
     */
    public static function destinoEdicion(Cliente_Cuentacorriente $fila): ?array
    {
        if ((int) ($fila->cobranza_id ?? 0) > 0) {
            return [
                'tipo' => 'cobranza',
                'id' => (int) $fila->cobranza_id,
                'titulo' => 'Editar '.self::etiquetaComprobante($fila),
                'route' => 'editar_cobranza',
            ];
        }

        if ((int) ($fila->venta_id ?? 0) > 0) {
            return [
                'tipo' => 'venta',
                'id' => (int) $fila->venta_id,
                'titulo' => 'Editar '.self::etiquetaComprobante($fila),
                'route' => 'editar_factura',
            ];
        }

        return null;
    }

    public static function urlEdicion(Cliente_Cuentacorriente $fila): ?string
    {
        $destino = self::destinoEdicion($fila);
        if ($destino === null) {
            return null;
        }

        return route($destino['route'], ['id' => $destino['id']]);
    }

    public static function puedeEditarComprobante(Cliente_Cuentacorriente $fila): bool
    {
        $destino = self::destinoEdicion($fila);
        if ($destino === null) {
            return false;
        }

        if ($destino['tipo'] === 'cobranza') {
            return can('editar-cobranza', false) || can('listar-cobranza', false);
        }

        return can('editar-factura', false) || can('listar-factura', false);
    }
}
