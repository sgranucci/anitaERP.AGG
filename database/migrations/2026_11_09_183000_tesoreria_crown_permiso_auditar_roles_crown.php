<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asigna el permiso de auditor de caja Crown a Enc-contaduría
 * (quien ya ve la bandeja de avisos y es el rol operativo con usuario activo).
 */
return new class extends Migration
{
    private const SLUG = 'auditar-tesoreria-crown-caja';

    private const ROLES = [
        'Enc-contaduría',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        $permisoId = DB::table('permiso')->where('slug', self::SLUG)->value('id');
        if (! $permisoId) {
            return;
        }
        foreach (DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id') as $rolId) {
            if (DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                continue;
            }
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        $permisoId = DB::table('permiso')->where('slug', self::SLUG)->value('id');
        if (! $permisoId) {
            return;
        }
        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id');
        DB::table('permiso_rol')->where('permiso_id', $permisoId)->whereIn('rol_id', $rolIds)->delete();
        SuitecrmPermiso::flushCachePermisos();
    }
};
