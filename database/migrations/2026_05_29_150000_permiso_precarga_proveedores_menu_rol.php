<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'compras/precarga_comprobante_proveedor';

    /** Mismos permisos que Enc-pagos / Op-Compras para precarga. */
    private const REFERENCIA_ROL = 'Enc-pagos';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $refRolId = (int) (DB::table('rol')->where('nombre', self::REFERENCIA_ROL)->value('id') ?? 0);
        if ($refRolId <= 0) {
            return;
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', [
                'crear-precarga-proveedores',
                'listar-precarga-proveedores',
                'editar-precarga-proveedores',
                'actualizar-precarga-proveedores',
                'borrar-precarga-proveedores',
            ])
            ->pluck('id')
            ->all();

        if ($permisoIds === []) {
            return;
        }

        $refPermisoIds = DB::table('permiso_rol')
            ->join('permiso', 'permiso.id', '=', 'permiso_rol.permiso_id')
            ->where('permiso_rol.rol_id', $refRolId)
            ->whereIn('permiso.id', $permisoIds)
            ->pluck('permiso.id')
            ->all();

        if ($refPermisoIds === []) {
            return;
        }

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', $menuId)
            ->pluck('rol_id')
            ->unique()
            ->all();

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            foreach ($refPermisoIds as $permisoId) {
                $permisoId = (int) $permisoId;
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
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
        if ($menuId <= 0) {
            return;
        }

        $refRolId = (int) (DB::table('rol')->where('nombre', self::REFERENCIA_ROL)->value('id') ?? 0);
        if ($refRolId <= 0) {
            return;
        }

        $permisoIds = DB::table('permiso_rol')
            ->join('permiso', 'permiso.id', '=', 'permiso_rol.permiso_id')
            ->where('permiso_rol.rol_id', $refRolId)
            ->whereIn('permiso.slug', [
                'crear-precarga-proveedores',
                'listar-precarga-proveedores',
                'editar-precarga-proveedores',
                'actualizar-precarga-proveedores',
                'borrar-precarga-proveedores',
            ])
            ->pluck('permiso.id')
            ->all();

        if ($permisoIds === []) {
            return;
        }

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', $menuId)
            ->where('rol_id', '!=', $refRolId)
            ->pluck('rol_id')
            ->unique()
            ->all();

        foreach ($rolIds as $rolId) {
            DB::table('permiso_rol')
                ->where('rol_id', (int) $rolId)
                ->whereIn('permiso_id', $permisoIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
