<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

/**
 * Qué jurisdicción IIBB se percibe en una venta.
 *
 * CABA (901) y Buenos Aires (902) se evalúan siempre por padrón, aunque la
 * entrega sea otra provincia. El resto (Córdoba, Entre Ríos, Misiones, Santa
 * Fe, Tucumán, etc.) solo si coincide con la provincia de entrega: lugar de
 * entrega del remito/pedido, o domicilio del CRUD de cliente si no hay uno.
 */
final class PercepcionIibbJurisdiccionEntregaSupport
{
    public const JURISDICCION_CABA = 901;

    public const JURISDICCION_BUENOS_AIRES = 902;

    /** @var list<int> */
    public const JURISDICCIONES_SIN_FILTRO_ENTREGA = [
        self::JURISDICCION_CABA,
        self::JURISDICCION_BUENOS_AIRES,
    ];

    public static function esCabaOBuenosAires(int $jurisdiccion): bool
    {
        return in_array($jurisdiccion, self::JURISDICCIONES_SIN_FILTRO_ENTREGA, true);
    }

    public static function corresponde(int $jurisdiccionPadron, mixed $jurisdiccionEntrega): bool
    {
        if (self::esCabaOBuenosAires($jurisdiccionPadron)) {
            return true;
        }

        if ($jurisdiccionEntrega === null || $jurisdiccionEntrega === '') {
            return false;
        }

        return $jurisdiccionPadron === (int) $jurisdiccionEntrega;
    }
}
