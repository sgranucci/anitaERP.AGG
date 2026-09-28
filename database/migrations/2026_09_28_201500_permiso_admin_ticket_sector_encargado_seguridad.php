<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: el encargado de Seguridad ve, en Carga de tickets, los tickets
 * emitidos por usuarios del mismo centro de costo (permiso ya usado en Capital Humano).
 */
return new class extends Migration
{
    private const SLUG = 'admin-ticket-sector';

    /** @var list<string> */
    private const ROLES = [
        'enc-SEGURIDAD',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        $this->invalidarCacheRoles($rolIds);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        $rolIds = $this->resolverRolIds();
        if ($permisoId <= 0 || $rolIds === []) {
            return;
        }

        DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->whereIn('rol_id', $rolIds)
            ->delete();

        $this->invalidarCacheRoles($rolIds);
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function invalidarCacheRoles(array $rolIds): void
    {
        SuitecrmPermiso::flushCachePermisos();
        foreach ($rolIds as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
};
