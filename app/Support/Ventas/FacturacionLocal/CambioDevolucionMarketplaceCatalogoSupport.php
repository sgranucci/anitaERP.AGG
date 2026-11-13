<?php

namespace App\Support\Ventas\FacturacionLocal;

/**
 * Canales, motivos y disposiciones del legajo marketplace.
 */
final class CambioDevolucionMarketplaceCatalogoSupport
{
    public const CANAL_TIENDANUBE = 'tiendanube';

    public const CANAL_MERCADOLIBRE = 'mercadolibre';

    public const CANAL_MANUAL = 'manual';

    /** @var array<string, string> */
    public const CANALES = [
        self::CANAL_TIENDANUBE => 'Tienda Nube',
        self::CANAL_MERCADOLIBRE => 'Mercado Libre',
        self::CANAL_MANUAL => 'Manual',
    ];

    public const TIPO_DEVOLVER = 'devolver';

    public const TIPO_REEMPLAZO = 'reemplazo';

    /** @var array<string, string> */
    public const TIPOS_LINEA = [
        self::TIPO_DEVOLVER => 'A devolver',
        self::TIPO_REEMPLAZO => 'Reemplazo',
    ];

    /**
     * @param  iterable<mixed>  $lineas  Filas con atributo/clave tipo
     */
    public static function tieneLineasTipo(iterable $lineas, string $tipo): bool
    {
        foreach ($lineas as $linea) {
            $valor = is_array($linea) ? ($linea['tipo'] ?? '') : ($linea->tipo ?? '');
            if ((string) $valor === $tipo) {
                return true;
            }
        }

        return false;
    }

    /**
     * Solo devolución/reembolso: hay líneas a devolver y ninguna de reemplazo.
     *
     * @param  iterable<mixed>  $lineas
     */
    public static function esSoloDevolucion(iterable $lineas): bool
    {
        return self::tieneLineasTipo($lineas, self::TIPO_DEVOLVER)
            && ! self::tieneLineasTipo($lineas, self::TIPO_REEMPLAZO);
    }

    /**
     * Un cambio es par por par: no se entregan más pares de los que se devuelven.
     * Un par extra es una venta y va por el POS.
     *
     * @param  iterable<mixed>  $lineas  Todas las líneas del legajo (devolver y reemplazo)
     */
    public static function errorExcesoReemplazo(iterable $lineas): ?string
    {
        $reemplazo = 0.0;
        $devolver = 0.0;
        foreach ($lineas as $linea) {
            $tipo = (string) (is_array($linea) ? ($linea['tipo'] ?? '') : ($linea->tipo ?? ''));
            $articuloId = (int) (is_array($linea) ? ($linea['articulo_id'] ?? 0) : ($linea->articulo_id ?? 0));
            if ($articuloId <= 0) {
                continue;
            }
            $cantidad = (float) (is_array($linea) ? ($linea['cantidad'] ?? 1) : ($linea->cantidad ?? 1));
            if ($tipo === self::TIPO_REEMPLAZO) {
                $reemplazo += $cantidad;
            } elseif ($tipo === self::TIPO_DEVOLVER) {
                $devolver += $cantidad;
            }
        }
        if ($reemplazo <= $devolver + 0.0001) {
            return null;
        }

        return 'El legajo tiene '.self::cantidadTexto($reemplazo).' par(es) de reemplazo y '
            .self::cantidadTexto($devolver).' a devolver. Un cambio es par por par: '
            .'si corregís un precio, editá la línea existente; si el cliente devuelve otro par, '
            .'agregalo con «Traer artículos de la factura». Un par extra se vende por el POS.';
    }

    private static function cantidadTexto(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 2, ',', ''), '0'), ',');
    }

    /** @var array<string, string> */
    public const MOTIVOS = [
        'cambio_talle' => 'Cambio de talle',
        'defecto' => 'Defecto / falla',
        'arrepentimiento' => 'Arrepentimiento',
        'otro' => 'Otro',
    ];

    /** @var array<string, string> */
    public const DISPOSICIONES = [
        'reventa' => 'Reventa',
        'deterioro' => 'Deterioro',
        'no_recibido' => 'No recibido',
    ];
}
