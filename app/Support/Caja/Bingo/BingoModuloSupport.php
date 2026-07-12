<?php

namespace App\Support\Caja\Bingo;

final class BingoModuloSupport
{
    public static function habilitado(): bool
    {
        return (bool) config('bingo.habilitado', false);
    }

    public static function assertHabilitado(): void
    {
        if (! self::habilitado()) {
            abort(404);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $menus
     * @return array<int, array<string, mixed>>
     */
    public static function filtrarMenuAside(array $menus): array
    {
        if (self::habilitado()) {
            return $menus;
        }

        return self::filtrarMenuRecursivo($menus);
    }

    /**
     * @param  array<int, array<string, mixed>>  $menus
     * @return array<int, array<string, mixed>>
     */
    private static function filtrarMenuRecursivo(array $menus): array
    {
        $filtrados = [];

        foreach ($menus as $item) {
            if (self::esItemBingo($item)) {
                continue;
            }

            if (! empty($item['submenu']) && is_array($item['submenu'])) {
                $item['submenu'] = self::filtrarMenuRecursivo($item['submenu']);
                if (($item['url'] ?? '') === '#' && $item['submenu'] === []) {
                    continue;
                }
            }

            $filtrados[] = $item;
        }

        return $filtrados;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function esItemBingo(array $item): bool
    {
        $url = (string) ($item['url'] ?? '');

        if (str_starts_with($url, 'caja/bingo')) {
            return true;
        }

        return ($item['nombre'] ?? '') === 'Bingo' && $url === '#';
    }
}
