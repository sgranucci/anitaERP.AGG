<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;

/**
 * Letra, código AFIP y título del PDF: manda el comprobante grabado (FAC/FCE + letra),
 * no el nombre del ABM tipotransaccion (puede quedar FCE aunque el código sea FAC).
 */
final class FacturaPdfIdentificacionSupport
{
    /**
     * @param  object{
     *     codigo?: string|null,
     *     codigo_afip?: int|string|null,
     *     tipotransacciones?: object|null
     * }  $venta
     * @return array{letra: string, codigo_afip: int, codigo_afip_pad: string, nombre: string, es_fce: bool}
     */
    public static function desdeVenta(object $venta): array
    {
        $codigoVenta = (string) ($venta->codigo ?? '');
        $letra = LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta($codigoVenta);
        $codigoAlmacenado = $venta->tipotransacciones->codigo ?? 0;
        $codigoAfip = (int) ($venta->codigo_afip ?? 0);
        if ($codigoAfip <= 0) {
            $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                $codigoAlmacenado,
                $codigoVenta
            );
        }
        if ($codigoAfip >= 200 && ! TipotransaccionCodigoAfipSupport::codigoVentaEsFce($codigoVenta)) {
            $codigoAfip -= 200;
        }
        if ($codigoAfip > 0 && $codigoAfip < 200 && TipotransaccionCodigoAfipSupport::codigoVentaEsFce($codigoVenta)) {
            $codigoAfip += 200;
        }

        $esFce = TipotransaccionCodigoAfipSupport::codigoVentaEsFce($codigoVenta) || $codigoAfip >= 200;

        return [
            'letra' => $letra,
            'codigo_afip' => $codigoAfip,
            'codigo_afip_pad' => str_pad((string) $codigoAfip, 3, '0', STR_PAD_LEFT),
            'nombre' => self::nombre($codigoVenta, $codigoAfip, $esFce),
            'es_fce' => $esFce,
        ];
    }

    public static function nombre(string $codigoVenta, int $codigoAfip, bool $esFce): string
    {
        $prefijo = strtoupper(trim(explode(' ', $codigoVenta, 2)[0] ?? ''));
        if ($prefijo === 'NCE' || $prefijo === 'NCD' || in_array($codigoAfip, [3, 8, 13, 53, 203, 208], true)) {
            return $esFce ? 'NOTA DE CREDITO ELECTRONICA' : 'NOTA DE CREDITO';
        }
        if ($prefijo === 'DCE' || $prefijo === 'NDD' || in_array($codigoAfip, [2, 7, 12, 52, 202, 207], true)) {
            return $esFce ? 'NOTA DE DEBITO ELECTRONICA' : 'NOTA DE DEBITO';
        }

        return $esFce ? 'FACTURA DE CREDITO ELECTRONICA' : 'FACTURA';
    }
}
