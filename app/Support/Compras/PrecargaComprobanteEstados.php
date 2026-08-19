<?php

namespace App\Support\Compras;

/**
 * Estados de precarga de comprobante de proveedor.
 */
final class PrecargaComprobanteEstados
{
    public const PENDIENTE = 'PENDIENTE';

    /** Ya tiene comprobante generado / asignado (no figura en el index por defecto). */
    public const GENERADA = 'GENERADA';

    /** Factura ya cargada en Anita (nativo u otro origen); no se genera comprobante ERP. */
    public const CARGADA_ANITA = 'CARGADA_ANITA';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::PENDIENTE,
            self::GENERADA,
            self::CARGADA_ANITA,
        ];
    }

    public static function etiqueta(string $estado): string
    {
        return match ($estado) {
            self::PENDIENTE => 'Pendientes',
            self::GENERADA => 'Generadas',
            self::CARGADA_ANITA => 'Ya cargadas en Anita',
            default => $estado,
        };
    }

    public static function etiquetaRegistro(string $estado): string
    {
        return match ($estado) {
            self::PENDIENTE => 'PENDIENTE',
            self::GENERADA => 'GENERADA',
            self::CARGADA_ANITA => 'Ya cargada en Anita',
            default => $estado,
        };
    }

    public static function puedeGenerarComprobante(string $estado): bool
    {
        return $estado === self::PENDIENTE;
    }

    public static function puedeMarcarCargadaAnita(string $estado): bool
    {
        return $estado === self::PENDIENTE;
    }
}
