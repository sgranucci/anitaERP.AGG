<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * IVA ventas: el menú ya estaba para Op-impuestos, pero faltaba el permiso
 * listar-iva-ventas (can() bloqueaba el ingreso). Caso: gsurace / Op-impuestos.
 */
return new class extends Migration
{
    private const PERMISO_SLUG = 'listar-iva-ventas';

    private const ROL = 'Op-impuestos';

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }

        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }

        DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->delete();
        SuitecrmPermiso::flushCachePermisos();
    }
};
