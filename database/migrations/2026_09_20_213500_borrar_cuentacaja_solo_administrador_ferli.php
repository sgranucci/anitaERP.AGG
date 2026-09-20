<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: borrar cuentas de caja solo para rol administrador.
 * Quita el permiso a Enc-admin, Enc-contaduría, Oficina (y cualquier otro).
 */
return new class extends Migration
{
    private const SLUG = 'borrar-cuentas-de-caja';

    private const ROL_ADMIN = 'administrador';

    /** Roles que tenían el permiso antes de esta migración (para down). */
    private const ROLES_RESTAURAR = [
        'administrador',
        'Enc-admin',
        'Enc-contaduría',
        'Oficina',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $adminId = (int) (DB::table('rol')->where('nombre', self::ROL_ADMIN)->value('id') ?? 0);
        if ($adminId <= 0) {
            return;
        }

        DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->where('rol_id', '<>', $adminId)
            ->delete();

        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $adminId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $adminId,
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        foreach (self::ROLES_RESTAURAR as $nombre) {
            $rolId = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
