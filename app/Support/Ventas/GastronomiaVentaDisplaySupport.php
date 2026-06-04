<?php

namespace App\Support\Ventas;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\DescuentoGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;

/**
 * Nombre de receptor en pantallas gastronomía (venta.nombre, no el cliente contable interno).
 */
final class GastronomiaVentaDisplaySupport
{
    public static function usaSnapshotReceptorEnVenta(?Venta $venta): bool
    {
        return $venta !== null && trim((string) ($venta->nombre ?? '')) !== '';
    }

    public static function nombreReceptorFactura(?Venta $venta): string
    {
        if (! $venta) {
            return '—';
        }

        if (self::usaSnapshotReceptorEnVenta($venta)) {
            return trim((string) $venta->nombre);
        }

        return trim((string) ($venta->clientes->nombre ?? '')) ?: '—';
    }

    public static function domicilioReceptorFactura(?Venta $venta): string
    {
        if (! $venta) {
            return '';
        }

        if (self::usaSnapshotReceptorEnVenta($venta)) {
            return trim((string) ($venta->domicilio ?? ''));
        }

        return trim((string) ($venta->clientes->domicilio ?? ''));
    }

    public static function documentoReceptorFactura(?Venta $venta): string
    {
        if (! $venta) {
            return '';
        }

        if (self::usaSnapshotReceptorEnVenta($venta)) {
            return trim((string) ($venta->numerodocumento ?? ''));
        }

        return trim((string) ($venta->clientes->numerodocumento ?? ''));
    }

    public static function codigoClienteMaestro(?Venta $venta): string
    {
        if (! $venta || self::usaSnapshotReceptorEnVenta($venta)) {
            return '';
        }

        return trim((string) ($venta->clientes->codigo ?? ''));
    }

    /**
     * Cliente del pie del ticket/PDF: cliente interno del descuento si aplica; si no, receptor de factura.
     */
    public static function nombreClientePie(?Venta $venta): string
    {
        if (! $venta) {
            return '—';
        }

        $cuenta = self::cuentaGastronomiaDesdeVenta($venta);
        if ($cuenta !== null && (int) ($cuenta->descuento_gastronomia_id ?? 0) > 0) {
            $nombreInterno = trim((string) ($cuenta->clienteInternoDescuento?->nombre ?? ''));
            if ($nombreInterno !== '') {
                return $nombreInterno;
            }
        }

        return self::nombreReceptorFactura($venta);
    }

    /**
     * Etiqueta de la línea de descuento (nombre del descuento gastronomía + porcentaje).
     */
    public static function etiquetaLineaDescuento(?Venta $venta, ?float $porcentajeCalculado = null): ?string
    {
        if (! $venta) {
            return null;
        }

        $descuento = self::cuentaGastronomiaDesdeVenta($venta)?->descuentoGastronomia;
        if (! $descuento instanceof DescuentoGastronomia) {
            return null;
        }

        $nombre = trim((string) ($descuento->nombre ?? ''));
        if ($nombre === '') {
            $nombre = 'Descuento';
        }

        $porcentaje = self::porcentajeDescuentoGastronomia($descuento, $venta, $porcentajeCalculado);
        if ($porcentaje === null) {
            return $nombre;
        }

        return $nombre.' '.self::formatearPorcentaje($porcentaje).'%';
    }

    /**
     * ID de orden Waitry asociada a la venta (emisión gastronomía o cuenta importada).
     */
    public static function waitryOrderId(?Venta $venta): ?int
    {
        $emision = self::emisionWaitryDesdeVenta($venta);
        if ($emision === null) {
            return null;
        }

        $id = (int) ($emision->waitry_order_id ?? 0);
        if ($id > 0) {
            return $id;
        }

        $id = (int) ($emision->cuenta?->waitry_order_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Código alfanumérico del papelito Waitry (tótem / comanda).
     */
    public static function waitryDisplayId(?Venta $venta): ?string
    {
        $emision = self::emisionWaitryDesdeVenta($venta);
        $displayId = trim((string) ($emision?->cuenta?->waitry_display_id ?? ''));

        return $displayId !== '' ? $displayId : null;
    }

    /**
     * Línea para ticket/PDF cuando la factura proviene de una cuenta Waitry.
     * Solo muestra el código alfanumérico del papelito (no el orderId numérico).
     */
    public static function lineaOrdenWaitry(?Venta $venta): ?string
    {
        $displayId = self::waitryDisplayId($venta);
        if ($displayId === null) {
            return null;
        }

        return 'Papelito Waitry: '.$displayId;
    }

    /**
     * Reemplaza "Descuento Gral." / "Descuento" en conceptos de pie del PDF por la etiqueta gastronomía.
     *
     * @param  iterable<int, mixed>  $conceptosTotales
     * @return list<array<string, mixed>>
     */
    public static function aplicarEtiquetaDescuentoEnConceptosTotales(Venta $venta, iterable $conceptosTotales): array
    {
        $etiqueta = self::etiquetaLineaDescuento($venta);
        if ($etiqueta === null) {
            return self::normalizarConceptosTotales($conceptosTotales);
        }

        $resultado = [];
        foreach ($conceptosTotales as $item) {
            $fila = is_array($item) ? $item : (method_exists($item, 'toArray') ? $item->toArray() : (array) $item);
            $concepto = (string) ($fila['concepto'] ?? '');
            if (self::esConceptoDescuentoPie($concepto)) {
                $fila['concepto'] = $etiqueta;
            }
            $resultado[] = $fila;
        }

        return $resultado;
    }

    private static function cuentaGastronomiaDesdeVenta(Venta $venta): ?CuentaGastronomia
    {
        $emision = self::emisionWaitryDesdeVenta($venta);
        $cuenta = $emision?->cuenta;
        if ($cuenta instanceof CuentaGastronomia) {
            $cuenta->loadMissing('descuentoGastronomia', 'clienteInternoDescuento');

            return $cuenta;
        }

        return null;
    }

    private static function emisionWaitryDesdeVenta(?Venta $venta): ?VentaGastronomiaEmision
    {
        if (! $venta) {
            return null;
        }

        if ($venta->relationLoaded('gastronomiaEmision')) {
            $emision = $venta->gastronomiaEmision;
            if ($emision !== null && ! $emision->relationLoaded('cuenta')) {
                $emision->loadMissing('cuenta');
            }

            return $emision;
        }

        $venta->loadMissing('gastronomiaEmision.cuenta');

        return $venta->gastronomiaEmision;
    }

    private static function porcentajeDescuentoGastronomia(
        DescuentoGastronomia $descuento,
        Venta $venta,
        ?float $porcentajeCalculado,
    ): ?float {
        if ($descuento->tipovalor === DescuentoGastronomia::TIPO_PORCENTAJE) {
            return (float) $descuento->valor;
        }

        if ($porcentajeCalculado !== null && $porcentajeCalculado > 0.) {
            return $porcentajeCalculado;
        }

        $descuentoVenta = (float) ($venta->descuento ?? 0);

        return $descuentoVenta > 0. ? $descuentoVenta : null;
    }

    private static function formatearPorcentaje(float $porcentaje): string
    {
        $redondeado = round($porcentaje, 2);

        return abs($redondeado - round($redondeado)) < 0.001
            ? (string) (int) round($redondeado)
            : number_format($redondeado, 2, '.', '');
    }

    private static function esConceptoDescuentoPie(string $concepto): bool
    {
        $normalizado = strtolower(trim($concepto));

        return str_contains($normalizado, 'descuento gral')
            || $normalizado === 'descuento';
    }

    /**
     * @param  iterable<int, mixed>  $conceptosTotales
     * @return list<array<string, mixed>>
     */
    private static function normalizarConceptosTotales(iterable $conceptosTotales): array
    {
        $resultado = [];
        foreach ($conceptosTotales as $item) {
            $resultado[] = is_array($item) ? $item : (method_exists($item, 'toArray') ? $item->toArray() : (array) $item);
        }

        return $resultado;
    }
}
