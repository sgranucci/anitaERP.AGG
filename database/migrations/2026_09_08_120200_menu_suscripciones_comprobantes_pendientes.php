<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú "Comprobantes pendientes" bajo el submenu Suscripciones.
 */
return new class extends Migration
{
    private const URL = 'compras/suscripciones/comprobantes-pendientes';

    private const URL_HERMANO = 'compras/suscripciones/conciliacion';

    public function up(): void
    {
        $submenu = DB::table('menu')
            ->where('nombre', 'Suscripciones')
            ->where('url', '#')
            ->first();
        if (! $submenu) {
            return;
        }

        $hermano = DB::table('menu')->where('url', self::URL_HERMANO)->first();
        $orden = $hermano ? (int) $hermano->orden + 1 : 6;

        // Empuja hermanos posteriores.
        DB::table('menu')
            ->where('menu_id', $submenu->id)
            ->where('orden', '>=', $orden)
            ->where('url', '!=', self::URL)
            ->update(['orden' => DB::raw('orden + 1'), 'updated_at' => now()]);

        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        $payload = [
            'nombre' => 'Comprobantes pendientes',
            'url' => self::URL,
            'menu_id' => (int) $submenu->id,
            'orden' => $orden,
            'icono' => 'fa-file-text-o',
            'updated_at' => now(),
        ];
        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update($payload);
        } else {
            $menuId = (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
        }

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', $submenu->id)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->all();

        // Si el submenu no tiene roles, heredar del listado.
        if ($rolIds === []) {
            $listadoId = (int) (DB::table('menu')->where('url', 'compras/suscripciones')->value('id') ?? 0);
            if ($listadoId > 0) {
                $rolIds = DB::table('menu_rol')
                    ->where('menu_id', $listadoId)
                    ->pluck('rol_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->all();
            }
        }

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }
};
