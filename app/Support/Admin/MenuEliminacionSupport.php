<?php

namespace App\Support\Admin;

use App\Models\Admin\Menu;
use App\Models\Admin\Permiso;
use App\Support\SuitecrmPermiso;
use Illuminate\Support\Facades\DB;

final class MenuEliminacionSupport
{
    /**
     * Elimina un ítem de menú desvinculando permisos y validando submenús.
     *
     * @throws \RuntimeException Si el menú tiene submenús.
     */
    public static function eliminar(int $menuId): void
    {
        $menu = Menu::findOrFail($menuId);

        if (Menu::query()->where('menu_id', $menuId)->exists()) {
            throw new \RuntimeException(
                'No se puede eliminar el menú porque tiene submenús. Elimine o reubique los submenús primero.'
            );
        }

        DB::transaction(function () use ($menu): void {
            self::desvincularPermisosYEliminar($menu);
        });

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * Elimina varios ítems. Incluye automáticamente los submenús de cada seleccionado
     * y borra de las hojas hacia la raíz.
     *
     * @param  list<int|string>  $ids
     */
    public static function eliminarVarios(array $ids): int
    {
        $seleccionados = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id) => $id > 0
        )));

        if ($seleccionados === []) {
            throw new \RuntimeException('No hay menús seleccionados.');
        }

        $filas = Menu::query()->get(['id', 'menu_id']);
        $hijosPorPadre = [];
        foreach ($filas as $fila) {
            $hijosPorPadre[(int) $fila->menu_id][] = (int) $fila->id;
        }

        $aBorrar = [];
        $cola = $seleccionados;
        while ($cola !== []) {
            $id = (int) array_pop($cola);
            if (isset($aBorrar[$id])) {
                continue;
            }
            $aBorrar[$id] = true;
            foreach ($hijosPorPadre[$id] ?? [] as $hijoId) {
                $cola[] = $hijoId;
            }
        }

        $idsBorrar = array_keys($aBorrar);
        $profundidad = [];
        foreach ($filas as $fila) {
            $profundidad[(int) $fila->id] = 0;
        }
        $cambio = true;
        while ($cambio) {
            $cambio = false;
            foreach ($filas as $fila) {
                $id = (int) $fila->id;
                $padre = (int) $fila->menu_id;
                if ($padre > 0 && isset($profundidad[$padre])) {
                    $nueva = $profundidad[$padre] + 1;
                    if ($nueva > $profundidad[$id]) {
                        $profundidad[$id] = $nueva;
                        $cambio = true;
                    }
                }
            }
        }

        usort($idsBorrar, static function ($a, $b) use ($profundidad): int {
            $a = (int) $a;
            $b = (int) $b;

            return ($profundidad[$b] ?? 0) <=> ($profundidad[$a] ?? 0);
        });

        $borrados = 0;
        DB::transaction(function () use ($idsBorrar, &$borrados): void {
            $idsSet = array_fill_keys($idsBorrar, true);
            foreach ($idsBorrar as $menuId) {
                $menu = Menu::query()->find($menuId);
                if (! $menu) {
                    continue;
                }

                $hijosAjenos = Menu::query()
                    ->where('menu_id', $menuId)
                    ->get()
                    ->contains(static function (Menu $hijo) use ($idsSet): bool {
                        return ! isset($idsSet[(int) $hijo->id]);
                    });

                if ($hijosAjenos) {
                    throw new \RuntimeException(
                        'No se puede eliminar «'.$menu->nombre.'» porque tiene submenús que no están en la selección.'
                    );
                }

                self::desvincularPermisosYEliminar($menu);
                $borrados++;
            }
        });

        SuitecrmPermiso::flushCachePermisos();

        return $borrados;
    }

    private static function desvincularPermisosYEliminar(Menu $menu): void
    {
        Permiso::query()
            ->where('menu_id', $menu->id)
            ->get()
            ->each(function (Permiso $permiso): void {
                $permiso->menu_id = null;
                $permiso->save();
            });

        $menu->delete();
    }
}
