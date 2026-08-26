<?php

namespace App\Support\Ventas;

/**
 * Numeración y vencimiento de facturas Villafranca (El Bierzo).
 *
 * - No Reparto 101 (factura dividida): copia el número de la FAC de Bierzo.
 * - Reparto 101 (solo Villa): avanza compemis FAC A sucursal 1 en /usr2/villafranca
 *   y emite con sucursal 15 (PV de prueba). En pendmae del remito graba
 *   penm_ref_* = FAC / letra / sucursal de emisión / número de esa factura.
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

    /**
     * Referencia de la factura Villafranca para pendmae.penm_ref_* del remito.
     * Sucursal = PV de emisión (15), no la del numerador (1).
     *
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    public static function referenciaPendmaeDesdeFactura(
        string $tipo,
        string $letra,
        $sucursalEmision,
        int $numero
    ): array {
        $tipo = strtoupper(substr(trim($tipo), 0, 3));
        $letra = strtoupper(substr(trim($letra), 0, 1));

        return [
            'tipo' => $tipo !== '' ? $tipo : 'FAC',
            'letra' => $letra !== '' ? $letra : 'A',
            'sucursal' => (int) $sucursalEmision,
            'nro' => $numero,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $ref
     * @return array<string, mixed>
     */
    public static function aplicarReferenciaPendmae(array $data, array $ref): array
    {
        $data['penm_ref_tipo'] = $ref['tipo'];
        $data['penm_ref_letra'] = $ref['letra'];
        $data['penm_ref_sucursal'] = $ref['sucursal'];
        $data['penm_ref_nro'] = $ref['nro'];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    public static function referenciaPendmaeDesdeRequest(array $request): array
    {
        $tipo = strtoupper(substr(trim((string) ($request['penm_ref_tipo'] ?? '')), 0, 3));
        $letra = strtoupper(substr(trim((string) ($request['penm_ref_letra'] ?? '')), 0, 1));

        return [
            'tipo' => $tipo !== '' ? $tipo : ' ',
            'letra' => $letra !== '' ? $letra : ' ',
            'sucursal' => (int) ($request['penm_ref_sucursal'] ?? 0),
            'nro' => (int) ($request['penm_ref_nro'] ?? 0),
        ];
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
