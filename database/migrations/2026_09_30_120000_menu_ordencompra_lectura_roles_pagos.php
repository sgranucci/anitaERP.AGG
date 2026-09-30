<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Roles de pagos: menú Órdenes de compra y permiso de listado, sin alta ni edición.
 * Sirve para consultar la OC desde la proyección de pagos.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/ordencompra';

    private const SLUG = 'listar-ordencompra';

    public function up(): void
    {
        $rolIds = $this->rolesPagos();
        if ($rolIds === []) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        foreach ($rolIds as $rolId) {
            if ($permisoId > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }

            if ($menuId > 0 && ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
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
        $rolIds = $this->rolesPagos();
        if ($rolIds === []) {
            return;
        }

        // Op-Pagos ya tenía listar-ordencompra por la migración de consulta en factura.
        $conservar = DB::table('rol')
            ->where('nombre', 'Op-Pagos')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $quitar = array_values(array_diff($rolIds, $conservar));

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId > 0 && $quitar !== []) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $quitar)
                ->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * Enc-pagos, Op-Pagos y equivalentes. Sin administrador: ya tiene el ABM completo.
     *
     * @return list<int>
     */
    private function rolesPagos(): array
    {
        return DB::table('rol')
            ->where('nombre', 'like', '%pagos%')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
};
