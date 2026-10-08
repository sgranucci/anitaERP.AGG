<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Cuándo y cómo se parte una factura de El Bierzo entre Bierzo y Villafranca.
 * Misma regla en pedido, remito y mostrador: manda el tipo de expreso del transporte.
 */
final class ElBierzoDivisionFacturaSupport
{
    /**
     * @return array{reparto101: bool, porcentaje: float, extra: float, tasa: float}|null
     */
    public static function plan(object $cliente, string $tipoExpreso, string $codigoTipo): ?array
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return null;
        }

        $tipoExpreso = (string) $tipoExpreso;
        if ($tipoExpreso !== '3' && $tipoExpreso !== '4') {
            return null;
        }

        $codigoTipo = (string) $codigoTipo;
        if ($codigoTipo !== '001' && $codigoTipo !== '201') {
            return null;
        }

        $reparto101 = $tipoExpreso === '4';
        $tieneCoeficiente = self::clienteTieneCoeficiente($cliente);
        if (! $reparto101 && ! $tieneCoeficiente) {
            return null;
        }

        if ($reparto101) {
            return [
                'reparto101' => true,
                'porcentaje' => 100.0,
                'extra' => (float) config('facturacion.COEFICIENTE_EXTRA_REPARTO_101'),
                'tasa' => $tieneCoeficiente ? (float) $cliente->coeficientes->tasa : 0.0,
            ];
        }

        return [
            'reparto101' => false,
            'porcentaje' => (float) $cliente->coeficientes->porcentajedivision,
            'extra' => (float) ($cliente->coeficienteextra ?? 0),
            'tasa' => (float) $cliente->coeficientes->tasa,
        ];
    }

    public static function clienteTieneCoeficiente(object $cliente): bool
    {
        if (method_exists($cliente, 'loadMissing')) {
            $cliente->loadMissing('coeficientes');
        }

        return isset($cliente->coeficientes) && $cliente->coeficientes !== null;
    }

    public static function coeficienteLinea(?string $divideArticulo, float $porcentaje): float
    {
        if ($divideArticulo === 'NO DIVIDE') {
            return 0.0;
        }

        return $porcentaje;
    }

    public static function cantidadLado(float $cantidad, float $coeficienteLinea, bool $ladoVillafranca): float
    {
        if ($ladoVillafranca) {
            return VillafrancaFacturacionSupport::redondearCantidadDivision(
                $cantidad * $coeficienteLinea / 100.
            );
        }

        return VillafrancaFacturacionSupport::redondearCantidadDivision(
            $cantidad * ((100. - $coeficienteLinea) / 100.)
        );
    }

    public static function precioLado(float $precio, float $extra, bool $ladoVillafranca): float
    {
        if ($ladoVillafranca && $extra != 0.0) {
            return $precio * $extra;
        }

        return $precio;
    }

    /**
     * @return array{cantidad: float, pieza: float, caja: float, precio: float, omitir: bool}
     */
    public static function partirLinea(
        float $cantidad,
        float $pieza,
        float $caja,
        float $precio,
        ?string $divideArticulo,
        float $porcentaje,
        float $extra,
        bool $ladoVillafranca
    ): array {
        $coeficienteLinea = self::coeficienteLinea($divideArticulo, $porcentaje);
        $cantidad = self::cantidadLado($cantidad, $coeficienteLinea, $ladoVillafranca);
        $pieza = self::cantidadLado($pieza, $coeficienteLinea, $ladoVillafranca);
        $caja = self::cantidadLado($caja, $coeficienteLinea, $ladoVillafranca);
        $precio = self::precioLado($precio, $extra, $ladoVillafranca);

        return [
            'cantidad' => $cantidad,
            'pieza' => $pieza,
            'caja' => $caja,
            'precio' => $precio,
            'omitir' => abs($cantidad) < 0.00001 && abs($pieza) < 0.00001 && abs($caja) < 0.00001,
        ];
    }
}
