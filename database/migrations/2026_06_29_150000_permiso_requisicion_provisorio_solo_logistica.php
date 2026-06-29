<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Modo provisorio de requisiciones: solo logística (+ administrador).
 * Quita guardar/confirmar provisorio del resto de roles que lo heredaron vía crear-requisicion.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ROLES_CON_PROVISORIO = [
        'Enc-logistica',
        'op-Logistica',
        'administrador',
    ];

    /** @var list<string> */
    private const PERMISOS_PROVISORIO = [
        'guardar-requisicion-provisorio',
        'confirmar-requisicion',
    ];

    public function up(): void
    {
        $rolIdsPermitidos = $this->rolIdsPorNombres(self::ROLES_CON_PROVISORIO);
        if ($rolIdsPermitidos === []) {
            return;
        }

        foreach (self::PERMISOS_PROVISORIO as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }

            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereNotIn('rol_id', $rolIdsPermitidos)
                ->delete();

            foreach ($rolIdsPermitidos as $rolId) {
                if ($rolId <= 0) {
                    continue;
                }
                DB::table('permiso_rol')->updateOrInsert(
                    ['permiso_id' => $permisoId, 'rol_id' => $rolId],
                    []
                );
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $refPermisoId = (int) (DB::table('permiso')->where('slug', 'crear-requisicion')->value('id') ?? 0);
        if ($refPermisoId <= 0) {
            return;
        }

        $rolIds = DB::table('permiso_rol')
            ->where('permiso_id', $refPermisoId)
            ->pluck('rol_id')
            ->unique()
            ->all();

        foreach (self::PERMISOS_PROVISORIO as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }

            foreach ($rolIds as $rolId) {
                DB::table('permiso_rol')->updateOrInsert(
                    ['permiso_id' => $permisoId, 'rol_id' => $rolId],
                    []
                );
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @param list<string> $nombres @return list<int> */
    private function rolIdsPorNombres(array $nombres): array
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
