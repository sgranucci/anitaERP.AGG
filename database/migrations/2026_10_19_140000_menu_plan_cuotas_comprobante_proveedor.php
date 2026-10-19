<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'compras/comprobante-proveedor/plan-cuotas';

    private const SLUG = 'editar-plan-cuotas-comprobante-proveedor';

    public function up(): void
    {
        $hermanoId = (int) (DB::table('menu')->where('url', 'compras/comprobante-proveedor')->value('id') ?? 0);
        $padreId = $hermanoId > 0
            ? (int) (DB::table('menu')->where('id', $hermanoId)->value('menu_id') ?? 0)
            : 110;
        if ($padreId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => 'Plan de cuotas impagas',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-calendar',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Editar plan de cuotas de comprobante proveedor',
                'slug' => self::SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('permiso')
            ->join('permiso_rol', 'permiso_rol.permiso_id', '=', 'permiso.id')
            ->where('permiso.slug', 'actualizar-comprobante-proveedor')
            ->pluck('permiso_rol.rol_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }
};
