<?php

namespace App\Support\Caja\Estacionamiento;

final class EstacionamientoModuloSupport
{
    public static function habilitado(): bool
    {
        return (bool) config('estacionamiento.habilitado', false);
    }

    public static function assertHabilitado(): void
    {
        if (! self::habilitado()) {
            abort(404);
        }
    }

    /**
     * Oculta menús de estacionamiento en el aside cuando el módulo no aplica al entorno.
     *
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
            if (self::esItemEstacionamiento($item)) {
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
    private static function esItemEstacionamiento(array $item): bool
    {
        $url = (string) ($item['url'] ?? '');

        if (str_starts_with($url, 'caja/estacionamiento')) {
            return true;
        }

        return ($item['nombre'] ?? '') === 'Estacionamiento' && $url === '#';
    }
}
