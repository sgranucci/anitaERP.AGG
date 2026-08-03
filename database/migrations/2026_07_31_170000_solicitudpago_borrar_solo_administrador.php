<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Borrar SP solo para rol administrador.
 * El resto de roles anula con Suspender.
 */
return new class extends Migration
{
    private const SLUG = 'borrar-solicitud-pago';

    private const ROL_ADMIN = 'administrador';

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $adminRolId = (int) (DB::table('rol')->where('nombre', self::ROL_ADMIN)->value('id') ?? 0);

        DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->when($adminRolId > 0, fn ($q) => $q->where('rol_id', '!=', $adminRolId))
            ->delete();

        if ($adminRolId > 0
            && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $adminRolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $adminRolId,
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No restaura asignaciones previas a otros roles.
        SuitecrmPermiso::flushCachePermisos();
    }
};
