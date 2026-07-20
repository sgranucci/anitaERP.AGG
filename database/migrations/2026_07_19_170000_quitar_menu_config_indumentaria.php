<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quita la opción de menú "Configuración indumentaria": ahora se accede desde un botón
 * del ABM de Prendas. Se conservan los permisos (ver/editar-configuracion-indumentaria),
 * reasignados al menú de Prendas para que sigan apareciendo en la administración de roles.
 */
return new class extends Migration
{
    private const URL_CONFIG = 'sueldos/indumentaria/configuracion';

    private const URL_PRENDA = 'sueldos/prenda';

    private const SLUGS = ['ver-configuracion-indumentaria', 'editar-configuracion-indumentaria'];

    public function up(): void
    {
        $menuPrendaId = (int) (DB::table('menu')->where('url', self::URL_PRENDA)->value('id') ?? 0);
        if ($menuPrendaId > 0) {
            DB::table('permiso')->whereIn('slug', self::SLUGS)->update(['menu_id' => $menuPrendaId, 'updated_at' => now()]);
        }

        $menuConfigId = (int) (DB::table('menu')->where('url', self::URL_CONFIG)->value('id') ?? 0);
        if ($menuConfigId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuConfigId)->delete();
            DB::table('menu')->where('id', $menuConfigId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $moduloId = (int) (DB::table('menu')->where('nombre', 'Módulo Sueldos y Jornales')->where('menu_id', 0)->value('id') ?? 0);
        if ($moduloId === 0) {
            return;
        }
        $submenuId = (int) (DB::table('menu')->where('nombre', 'Indumentaria')->where('menu_id', $moduloId)->value('id') ?? 0);
        if ($submenuId === 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::URL_CONFIG)->value('id') ?? 0);
        if ($menuId === 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => 'Configuración indumentaria', 'url' => self::URL_CONFIG, 'menu_id' => $submenuId,
                'orden' => $orden, 'icono' => 'fa-cogs', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->whereIn('slug', self::SLUGS)->update(['menu_id' => $menuId, 'updated_at' => now()]);

        $rolIds = DB::table('rol')->where('nombre', 'administrador')->orWhere('nombre', 'like', '%apital%umano%')->pluck('id');
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => (int) $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
