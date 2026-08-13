<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El bypass de cierre contable queda solo para administrador.
 * Enc-contaduría / Op-contaduria deben usar apertura programada.
 */
return new class extends Migration
{
    private const PERMISO_SLUG = 'operar-periodo-cerrado-contable';

    /** @var list<string> */
    private const ROLES_QUITAR = [
        'Enc-contaduría',
        'Op-contaduria',
    ];

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $rolIdsQuitar = $this->resolverRolIds(self::ROLES_QUITAR);
        if ($rolIdsQuitar !== []) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIdsQuitar)
                ->delete();
        }

        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($adminId > 0
            && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $adminId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $adminId,
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        // Restaura el alcance original de la migración 2026_06_13_131000 (Enc-contaduría).
        foreach ($this->resolverRolIds(['Enc-contaduría']) as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
};
