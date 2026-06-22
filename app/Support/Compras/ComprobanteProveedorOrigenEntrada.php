<?php

namespace App\Support\Compras;

/**
 * Origen de alta del comprobante de proveedor (independiente de modo_carga stock/contable).
 */
final class ComprobanteProveedorOrigenEntrada
{
    /** Datos desde precarga del agente/API (AGG u otro cliente). */
    public const PRECARGA = 'PRECARGA';

    /** Alta vinculada a orden de compra sin precarga previa. */
    public const ORDENCOMPRA = 'ORDENCOMPRA';

    /** Alta manual: sin precarga ni OC obligatoria al inicio. */
    public const MANUAL = 'MANUAL';

    /** Precarga generada por el modelo IA propio (PDF). */
    public const PDF_IA = 'PDF_IA';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::PRECARGA,
            self::ORDENCOMPRA,
            self::MANUAL,
            self::PDF_IA,
        ];
    }

    public static function etiqueta(string $origen): string
    {
        return match ($origen) {
            self::PRECARGA => 'Desde precarga (agente/API)',
            self::ORDENCOMPRA => 'Desde orden de compra',
            self::MANUAL => 'Sin OC (manual)',
            self::PDF_IA => 'PDF — modelo IA Anita',
            default => $origen,
        };
    }

    /**
     * Determina el origen al crear (precarga gana si ambos FK presentes).
     */
    public static function resolver(
        ?int $precargaComprobanteProveedorId,
        ?int $ordencompraId,
        ?string $origenForzado = null,
    ): string {
        if ($origenForzado && in_array($origenForzado, self::todos(), true)) {
            return $origenForzado;
        }

        if ($precargaComprobanteProveedorId) {
            return self::PRECARGA;
        }

        if ($ordencompraId) {
            return self::ORDENCOMPRA;
        }

        return self::MANUAL;
    }
}
