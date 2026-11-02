<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: editar un ingreso/egreso ya cargado queda en contaduría.
 * Finanzas, pagos y capital humano siguen creando y revirtiendo.
 * Si el movimiento quedó mal, se revierte y se vuelve a ingresar.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const SLUGS = [
        'editar-ingresos-egresos-caja',
        'actualizar-ingresos-egresos-caja',
    ];

    /** @var list<string> */
    private const ROLES_FUERA = [
        'Enc-finanzas',
        'Op-Finanzas',
        'Enc-pagos',
        'Op-Pagos',
        'opcont-capitalhumano',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $permisoIds = $this->permisoIds();
        $rolIds = $this->rolIdsFuera();
        if ($permisoIds === [] || $rolIds === []) {
            return;
        }

        DB::table('permiso_rol')
            ->whereIn('permiso_id', $permisoIds)
            ->whereIn('rol_id', $rolIds)
            ->delete();

        $this->invalidar($rolIds);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $permisoIds = $this->permisoIds();
        $rolIds = $this->rolIdsFuera();
        foreach ($permisoIds as $permisoId) {
            foreach ($rolIds as $rolId) {
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
        }

        $this->invalidar($rolIds);
    }

    /**
     * @return list<int>
     */
    private function permisoIds(): array
    {
        return DB::table('permiso')
            ->whereIn('slug', self::SLUGS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function rolIdsFuera(): array
    {
        return DB::table('rol')
            ->whereIn('nombre', self::ROLES_FUERA)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function invalidar(array $rolIds): void
    {
        SuitecrmPermiso::flushCachePermisos();
        foreach ($rolIds as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
    }
};
