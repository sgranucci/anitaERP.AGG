<?php

namespace App\Support\Ventas;

final class FacturaPdfPaginacionSupport
{
    public const ITEMS_ULTIMA_ADMIN = 16;

    public const ITEMS_ANTERIOR_ADMIN = 20;

    public const ITEMS_ULTIMA_POS = 16;

    public const ITEMS_ANTERIOR_POS = 22;

    public const ITEMS_ULTIMA_REMITO = 16;

    public const ITEMS_ANTERIOR_REMITO = 26;

    public const ITEMS_ULTIMA_PEDIDO = 18;

    public const ITEMS_ANTERIOR_PEDIDO = 18;

    public const ITEMS_ULTIMA_REMITO_HORIZONTAL = 16;

    public const ITEMS_ANTERIOR_REMITO_HORIZONTAL = 18;

    /**
     * Parte renglones por capacidad de hoja “llena”. El pie puede bajar a la
     * siguiente página si no entra; no se reserva una primera hoja casi vacía.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<list<array<string, mixed>>>
     */
    public static function paginas(array $items, string $tipo = 'admin'): array
    {
        [, $anterior] = match ($tipo) {
            'pos' => [self::ITEMS_ULTIMA_POS, self::ITEMS_ANTERIOR_POS],
            'remito' => [self::ITEMS_ULTIMA_REMITO, self::ITEMS_ANTERIOR_REMITO],
            'pedido' => [self::ITEMS_ULTIMA_PEDIDO, self::ITEMS_ANTERIOR_PEDIDO],
            'remito_horizontal' => [self::ITEMS_ULTIMA_REMITO_HORIZONTAL, self::ITEMS_ANTERIOR_REMITO_HORIZONTAL],
            default => [self::ITEMS_ULTIMA_ADMIN, self::ITEMS_ANTERIOR_ADMIN],
        };

        $items = array_values($items);
        $n = count($items);
        if ($n === 0) {
            return [[]];
        }
        // Si entra en una hoja “llena” (sin reservar el pie), no partir: eso dejaba
        // 1–4 renglones + “Continúa…” y el resto en la página siguiente.
        if ($n <= $anterior) {
            return [$items];
        }

        $paginas = [];
        $offset = 0;
        $restan = $n;
        while ($restan > $anterior) {
            $paginas[] = array_slice($items, $offset, $anterior);
            $offset += $anterior;
            $restan -= $anterior;
        }
        $paginas[] = array_slice($items, $offset);

        return $paginas;
    }
}
