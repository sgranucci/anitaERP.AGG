<?php

namespace App\Support\Ventas;

final class FacturaPdfPaginacionSupport
{
    public const ITEMS_ULTIMA_ADMIN = 16;

    public const ITEMS_ANTERIOR_ADMIN = 20;

    public const ITEMS_ULTIMA_POS = 16;

    public const ITEMS_ANTERIOR_POS = 22;

    public const ITEMS_ULTIMA_REMITO = 20;

    public const ITEMS_ANTERIOR_REMITO = 26;

    public const ITEMS_ULTIMA_PEDIDO = 18;

    public const ITEMS_ANTERIOR_PEDIDO = 18;

    public const ITEMS_ULTIMA_REMITO_HORIZONTAL = 16;

    public const ITEMS_ANTERIOR_REMITO_HORIZONTAL = 18;

    /**
     * Parte los renglones para que el pie (totales + QR/CAE o CAI) quede en la última hoja
     * junto con ítems. Si entran todos con el pie, una sola página.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<list<array<string, mixed>>>
     */
    public static function paginas(array $items, string $tipo = 'admin'): array
    {
        [$ultima, $anterior] = match ($tipo) {
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
        if ($n <= $ultima) {
            return [$items];
        }

        $paginas = [];
        $offset = 0;
        $restan = $n;
        while ($restan > $ultima) {
            $take = min($anterior, $restan);
            if ($restan - $take === 0 && $take > $ultima) {
                $take = $restan - $ultima;
            }
            if ($take < 1) {
                break;
            }
            $paginas[] = array_slice($items, $offset, $take);
            $offset += $take;
            $restan -= $take;
        }
        $paginas[] = array_slice($items, $offset);

        return $paginas;
    }
}
