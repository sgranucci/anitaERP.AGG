<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enc-logistica: visibilidad de requisiciones como jefe de sector (CC de origen),
 * no como compras operativo (todas las empresas / todos los CC).
 */
return new class extends Migration
{
    private const ROL_ENC_LOGISTICA = 'Enc-logistica';

    private const PERMISO_QUITAR = 'usuario-requisicion-compras';

    private const PERMISO_AGREGAR = 'usuario-requisicion-resto';

    public function up(): void
    {
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL_ENC_LOGISTICA)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $permisoComprasId = (int) (DB::table('permiso')->where('slug', self::PERMISO_QUITAR)->value('id') ?? 0);
        if ($permisoComprasId > 0) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoComprasId)
                ->where('rol_id', $rolId)
                ->delete();
        }

        $permisoRestoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_AGREGAR)->value('id') ?? 0);
        if ($permisoRestoId > 0) {
            DB::table('permiso_rol')->updateOrInsert(
                ['permiso_id' => $permisoRestoId, 'rol_id' => $rolId],
                []
            );
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL_ENC_LOGISTICA)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $permisoRestoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_AGREGAR)->value('id') ?? 0);
        if ($permisoRestoId > 0) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoRestoId)
                ->where('rol_id', $rolId)
                ->delete();
        }

        $permisoComprasId = (int) (DB::table('permiso')->where('slug', self::PERMISO_QUITAR)->value('id') ?? 0);
        if ($permisoComprasId > 0) {
            DB::table('permiso_rol')->updateOrInsert(
                ['permiso_id' => $permisoComprasId, 'rol_id' => $rolId],
                []
            );
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
