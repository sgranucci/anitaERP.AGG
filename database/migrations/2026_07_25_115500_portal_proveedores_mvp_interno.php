<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'compras/portal-proveedores';

    /** @var array<string,string> */
    private const PERMISOS = [
        'listar-portal-proveedores' => 'Usar portal interno de proveedores',
        'cargar-portal-proveedores' => 'Presentar facturas desde portal de proveedores',
    ];

    public function up(): void
    {
        $referencia = DB::table('menu')
            ->where('url', 'compras/precarga_comprobante_proveedor')
            ->first();

        if (! $referencia) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $datosMenu = [
            'menu_id' => (int) $referencia->menu_id,
            'nombre' => 'Portal de proveedores (prueba)',
            'icono' => 'fa-cloud-upload',
            'updated_at' => now(),
        ];

        if ($menuId <= 0) {
            $menuId = (int) DB::table('menu')->insertGetId($datosMenu + [
                'url' => self::MENU_URL,
                'orden' => (int) (DB::table('menu')
                    ->where('menu_id', (int) $referencia->menu_id)
                    ->max('orden') ?? 0) + 1,
                'created_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update($datosMenu);
        }

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', (int) $referencia->id)
            ->pluck('rol_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($adminId > 0) {
            $rolIds[] = $adminId;
        }
        $rolIds = array_values(array_unique(array_filter($rolIds)));

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        foreach (self::PERMISOS as $slug => $nombre) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            $datosPermiso = [
                'nombre' => $nombre,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ];

            if ($permisoId <= 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId($datosPermiso + [
                    'slug' => $slug,
                    'created_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update($datosPermiso);
            }

            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')
                    ->where('permiso_id', $permisoId)
                    ->where('rol_id', $rolId)
                    ->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoIds = DB::table('permiso')
            ->whereIn('slug', array_keys(self::PERMISOS))
            ->pluck('id')
            ->all();

        if ($permisoIds !== []) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
