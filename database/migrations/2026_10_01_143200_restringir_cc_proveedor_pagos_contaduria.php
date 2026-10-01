<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: la cuenta corriente desde la ficha de proveedores queda solo
 * para pagos y contaduría. El botón y el listado usan
 * listar-cuentacorriente-proveedor.
 */
return new class extends Migration
{
    private const PERMISO = 'listar-cuentacorriente-proveedor';

    /** @var list<string> */
    private const ROLES = [
        'Enc-pagos',
        'Op-Pagos',
        'Enc-contaduría',
        'Op-contaduria',
        'Sup-contaduria',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $rolIds = $this->resolverRolIds();
        $afectados = DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->pluck('rol_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->when($rolIds !== [], function ($q) use ($rolIds) {
                $q->whereNotIn('rol_id', $rolIds);
            })
            ->delete();

        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        $this->invalidarCache(array_values(array_unique(array_merge($afectados, $rolIds))));
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $restaurar = [
            'Enc-admin',
            'Enc-compras',
            'Enc-finanzas',
            'Enc-impuestos',
            'Op-Compras',
            'Op-impuestos',
        ];

        $rolIds = DB::table('rol')->whereIn('nombre', $restaurar)->pluck('id')->map(static fn ($id) => (int) $id)->all();
        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        $this->invalidarCache($rolIds);
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = DB::table('rol')
            ->where(function ($q) {
                $q->whereIn('nombre', self::ROLES)
                    ->orWhere('nombre', 'like', '%pagos%')
                    ->orWhere('nombre', 'like', '%contadur%');
            })
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function invalidarCache(array $rolIds): void
    {
        SuitecrmPermiso::flushCachePermisos();
        foreach ($rolIds as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
    }
};
