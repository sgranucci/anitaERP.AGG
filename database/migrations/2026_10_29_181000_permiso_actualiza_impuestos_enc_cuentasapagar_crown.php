<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: Enc-cuentasapagar puede editar la solapa Datos impuestos del ABM de proveedores
 * (permiso actualiza-impuestos). jcofre y quien comparte ese rol.
 */
return new class extends Migration
{
    private const SLUG = 'actualiza-impuestos';

    private const ROL = 'Enc-cuentasapagar';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
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

        PermisoCacheSupport::forgetRol($rolId);
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }

        DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->where('rol_id', $rolId)
            ->delete();

        PermisoCacheSupport::forgetRol($rolId);
        SuitecrmPermiso::flushCachePermisos();
    }
};
