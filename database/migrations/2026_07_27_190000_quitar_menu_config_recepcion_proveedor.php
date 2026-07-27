<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quita «Recepción proveedores» del menú Configuración: se accede desde el botón
 * Configuración del listado Stock → Recepción proveedores.
 * Conserva los permisos, reasignados al menú del proceso.
 */
return new class extends Migration
{
    private const URL_CONFIG = 'configuracion/recepcion-proveedor';

    private const URL_PROCESO = 'stock/recepcion-proveedor';

    /** @var list<string> */
    private const SLUGS = [
        'editar-configuracion-recepcion-proveedor',
        'actualizar-configuracion-recepcion-proveedor',
    ];

    public function up(): void
    {
        $menuProcesoId = (int) (DB::table('menu')->where('url', self::URL_PROCESO)->value('id') ?? 0);
        if ($menuProcesoId > 0) {
            DB::table('permiso')->whereIn('slug', self::SLUGS)->update([
                'menu_id' => $menuProcesoId,
                'updated_at' => now(),
            ]);
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
        $padreId = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($padreId === 0) {
            $padreId = (int) (DB::table('menu')
                ->where('nombre', 'Configuración')
                ->where('url', '#')
                ->value('id') ?? 33);
        }

        $menuId = (int) (DB::table('menu')->where('url', self::URL_CONFIG)->value('id') ?? 0);
        if ($menuId === 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => 'Recepción proveedores',
                'url' => self::URL_CONFIG,
                'orden' => $orden,
                'icono' => 'fa-truck',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->whereIn('slug', self::SLUGS)->update([
            'menu_id' => $menuId,
            'updated_at' => now(),
        ]);

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', (int) (DB::table('menu')->where('url', self::URL_PROCESO)->value('id') ?? 0))
            ->pluck('rol_id');
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', (int) $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => (int) $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
