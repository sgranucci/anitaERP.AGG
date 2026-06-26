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
            Permiso::query()
                ->where('menu_id', $menu->id)
                ->get()
                ->each(function (Permiso $permiso): void {
                    $permiso->menu_id = null;
                    $permiso->save();
                });

            $menu->delete();
        });

        SuitecrmPermiso::flushCachePermisos();
    }
}
