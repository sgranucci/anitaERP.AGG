<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enc-logistica opera el circuito de requisiciones de todos los sectores
 * (informe, listado y cumplimiento), no solo las de su CC de origen.
 * Vuelve a usuario-requisicion-compras, igual que op-Logistica.
 */
return new class extends Migration
{
    private const ROL_ENC_LOGISTICA = 'Enc-logistica';

    private const PERMISO_QUITAR = 'usuario-requisicion-resto';

    private const PERMISO_AGREGAR = 'usuario-requisicion-compras';

    public function up(): void
    {
        $this->intercambiarPermisos(self::PERMISO_QUITAR, self::PERMISO_AGREGAR);
    }

    public function down(): void
    {
        $this->intercambiarPermisos(self::PERMISO_AGREGAR, self::PERMISO_QUITAR);
    }

    private function intercambiarPermisos(string $quitar, string $agregar): void
    {
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL_ENC_LOGISTICA)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $permisoQuitarId = (int) (DB::table('permiso')->where('slug', $quitar)->value('id') ?? 0);
        if ($permisoQuitarId > 0) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoQuitarId)
                ->where('rol_id', $rolId)
                ->delete();
        }

        $permisoAgregarId = (int) (DB::table('permiso')->where('slug', $agregar)->value('id') ?? 0);
        if ($permisoAgregarId > 0) {
            DB::table('permiso_rol')->updateOrInsert(
                ['permiso_id' => $permisoAgregarId, 'rol_id' => $rolId],
                []
            );
        }

        SuitecrmPermiso::flushCachePermisos();
        PermisoCacheSupport::forgetRol($rolId);
    }
};
