<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: modificar una orden de pago (actualizar) queda solo para administrador.
 * Editar (abrir la orden) no se toca. Ante un error, la corrección es revertir.
 *
 * Roles que tenían actualizar antes de este corte: Enc-pagos y Op-Pagos.
 */
return new class extends Migration
{
    private const SLUG = 'actualizar-pagoproveedor';

    private const ROL_ADMIN = 'administrador';

    /** @var list<string> */
    private const ROLES_PREVIOS = [
        'Enc-pagos',
        'Op-Pagos',
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

        $adminRolId = (int) (DB::table('rol')->where('nombre', self::ROL_ADMIN)->value('id') ?? 0);
        $afectados = DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::transaction(function () use ($permisoId, $adminRolId): void {
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
        });

        $this->invalidarCacheRoles($afectados);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $rolIds = [];
        DB::transaction(function () use ($permisoId, &$rolIds): void {
            foreach (self::ROLES_PREVIOS as $nombre) {
                $rolId = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
                if ($rolId <= 0) {
                    continue;
                }
                $rolIds[] = $rolId;
                $existe = DB::table('permiso_rol')
                    ->where('permiso_id', $permisoId)
                    ->where('rol_id', $rolId)
                    ->exists();
                if (! $existe) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        });

        $this->invalidarCacheRoles($rolIds);
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function invalidarCacheRoles(array $rolIds): void
    {
        SuitecrmPermiso::flushCachePermisos();
        foreach (array_values(array_unique($rolIds)) as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
    }
};
