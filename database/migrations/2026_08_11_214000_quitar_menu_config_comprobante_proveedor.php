<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quita «Config. comprobante proveedor» del menú lateral: se accede desde el botón
 * Configuración del listado Compras → Comprobantes proveedor.
 * Conserva los permisos, reasignados al menú del proceso.
 */
return new class extends Migration
{
    private const URL_CONFIG = 'compras/configuracion-comprobante-proveedor';

    private const URL_PROCESO = 'compras/comprobante-proveedor';

    /** @var list<string> */
    private const SLUGS = [
        'editar-configuracion-comprobante-proveedor',
        'actualizar-configuracion-comprobante-proveedor',
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
        $menuProcesoId = (int) (DB::table('menu')->where('url', self::URL_PROCESO)->value('id') ?? 0);
        if ($menuProcesoId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::URL_CONFIG)->value('id') ?? 0);
        if ($menuId === 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $menuProcesoId)->max('orden') ?? 0) + 1;
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $menuProcesoId,
                'nombre' => 'Config. comprobante proveedor',
                'url' => self::URL_CONFIG,
                'orden' => $orden,
                'icono' => 'fa-cog',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->whereIn('slug', self::SLUGS)->update([
            'menu_id' => $menuId,
            'updated_at' => now(),
        ]);

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', $menuProcesoId)
            ->pluck('rol_id');
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', (int) $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => (int) $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
