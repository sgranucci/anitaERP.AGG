<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Etiquetas y columnas del listado de ventas (factura) según instalación (`EMPRESA`).
 *
 * Configurar en `config/cliente.php` por case:
 *  - listado_etiqueta_transporte / listado_etiqueta_transporte_plural
 *  - listado_etiqueta_cantidad / listado_abreviatura_cantidad
 *  - listado_columnas_cantidad: `detalle` (caja + unidades + cantidad) | `cantidad` (solo cantidad)
 *
 * Defaults: Bierzo = detalle + Reparto/Kilos; Ferli = cantidad + Transporte/Pares; resto = cantidad + Transporte/Cantidad.
 */
final class VentasListadoEtiquetasSupport
{
    /** Cajas + unidades + columna de cantidad (El Bierzo). */
    public const COLUMNAS_DETALLE = 'detalle';

    /** Solo la columna de cantidad (Ferli = pares; resto = cantidad). */
    public const COLUMNAS_CANTIDAD = 'cantidad';

    public static function etiquetaTransporte(): string
    {
        return self::desdeConfig('listado_etiqueta_transporte', self::defaultTransporte());
    }

    public static function etiquetaTransportePlural(): string
    {
        return self::desdeConfig('listado_etiqueta_transporte_plural', self::defaultTransportePlural());
    }

    public static function etiquetaCantidad(): string
    {
        return self::desdeConfig('listado_etiqueta_cantidad', self::defaultCantidad());
    }

    /** Sufijo en subtotales: "kg", "pares", etc. Vacío = sin sufijo. */
    public static function abreviaturaCantidad(): string
    {
        $cfg = config('cliente.listado_abreviatura_cantidad', null);
        if ($cfg !== null) {
            return trim((string) $cfg);
        }

        return self::defaultAbreviaturaCantidad();
    }

    /**
     * Modo de columnas de cantidades en el listado.
     *
     * @return self::COLUMNAS_DETALLE|self::COLUMNAS_CANTIDAD
     */
    public static function columnasCantidadModo(): string
    {
        $modo = strtolower(trim((string) config('cliente.listado_columnas_cantidad', '')));
        if ($modo === self::COLUMNAS_DETALLE || $modo === self::COLUMNAS_CANTIDAD) {
            return $modo;
        }

        return self::defaultColumnasCantidadModo();
    }

    public static function muestraCajaUnidad(): bool
    {
        return self::columnasCantidadModo() === self::COLUMNAS_DETALLE;
    }

    /** Cantidad de columnas numéricas de mercadería (1 ó 3). */
    public static function cantidadColumnasMercaderia(): int
    {
        return self::muestraCajaUnidad() ? 3 : 1;
    }

    /**
     * Columnas fijas antes de mercadería: ID, Fecha, Comprobante, Cliente, Empresa.
     */
    public static function colspanAntesMercaderia(): int
    {
        return 5;
    }

    /**
     * Total de columnas de la tabla (sin acciones).
     * fijas + mercadería + transporte + moneda + total.
     */
    public static function colspanTablaSinAcciones(): int
    {
        return self::colspanAntesMercaderia() + self::cantidadColumnasMercaderia() + 3;
    }

    public static function colspanTablaConAcciones(): int
    {
        return self::colspanTablaSinAcciones() + 1;
    }

    public static function sinTransporte(): string
    {
        return 'Sin '.mb_strtolower(self::etiquetaTransporte());
    }

    public static function porTransporte(): string
    {
        return 'Por '.mb_strtolower(self::etiquetaTransporte());
    }

    public static function numeroTransporteLabel(): string
    {
        return 'Nº '.mb_strtolower(self::etiquetaTransporte());
    }

    public static function formatoCantidadConAbreviatura(float $valor): string
    {
        $txt = PedidoListadoSupport::formatearTotal($valor);
        $abrev = self::abreviaturaCantidad();

        return $abrev !== '' ? $txt.' '.$abrev : $txt;
    }

    private static function desdeConfig(string $clave, string $default): string
    {
        $valor = trim((string) config('cliente.'.$clave, ''));

        return $valor !== '' ? $valor : $default;
    }

    private static function defaultTransporte(): string
    {
        return EntornoEmpresaSupport::esElBierzo() ? 'Reparto' : 'Transporte';
    }

    private static function defaultTransportePlural(): string
    {
        return EntornoEmpresaSupport::esElBierzo() ? 'Repartos' : 'Transportes';
    }

    private static function defaultCantidad(): string
    {
        if (EntornoEmpresaSupport::esFerli()) {
            return 'Pares';
        }
        if (EntornoEmpresaSupport::esElBierzo()) {
            return 'Kilos';
        }

        return 'Cantidad';
    }

    private static function defaultAbreviaturaCantidad(): string
    {
        if (EntornoEmpresaSupport::esFerli()) {
            return 'pares';
        }
        if (EntornoEmpresaSupport::esElBierzo()) {
            return 'kg';
        }

        return '';
    }

    private static function defaultColumnasCantidadModo(): string
    {
        return EntornoEmpresaSupport::esElBierzo()
            ? self::COLUMNAS_DETALLE
            : self::COLUMNAS_CANTIDAD;
    }
}
