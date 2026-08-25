<?php

namespace App\Support\Ventas;

/**
 * Numeración y vencimiento de facturas Villafranca (El Bierzo).
 *
 * - No Reparto 101 (factura dividida): copia el número de la FAC de Bierzo.
 * - Reparto 101 (solo Villa): avanza compemis FAC A sucursal 1 en /usr2/villafranca
 *   y emite con sucursal 15 (PV de prueba).
 * - Vencimiento: siempre la fecha de la factura.
 */
final class VillafrancaFacturacionSupport
{
    public const TIPOEXPRESO_REPARTO_101 = '4';

    public static function esReparto101($pedido): bool
    {
        $tipo = is_object($pedido)
            ? ($pedido->transportes->tipoexpreso ?? '')
            : '';

        return (string) $tipo === self::TIPOEXPRESO_REPARTO_101;
    }

    public static function sucursalNumeradorPropio(): string
    {
        $sucursal = trim((string) config('facturacion.VILLAFRANCA_NUMERADOR_SUCURSAL', '1'));

        return $sucursal !== '' ? $sucursal : '1';
    }

    public static function pathSistema(): string
    {
        return PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA;
    }

    public static function debeForzarVencimientoFechaFactura(bool $grabaComprobanteDividido, $puntoventa = null): bool
    {
        if ($grabaComprobanteDividido) {
            return true;
        }

        $puntoventaId = is_object($puntoventa)
            ? (int) ($puntoventa->id ?? 0)
            : (int) $puntoventa;

        return PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision($puntoventaId);
    }

    /**
     * @param  list<array{fechavencimiento:mixed,total:mixed}>  $cuotas
     * @return list<array{fechavencimiento:mixed,total:mixed}>
     */
    public static function aplicarVencimientoFechaFactura(array $cuotas, $fechaFactura): array
    {
        foreach ($cuotas as $i => $cuota) {
            $cuotas[$i]['fechavencimiento'] = $fechaFactura;
        }

        return $cuotas;
    }
}
