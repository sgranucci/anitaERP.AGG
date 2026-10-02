<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: Op-Finanzas vuelve a ver la cuenta corriente desde la ficha de
 * proveedores (Martina Vallejos, Lucas Romero, Laila Bertani, Yanina Gonzalez).
 * El botón y el listado usan listar-cuentacorriente-proveedor.
 */
return new class extends Migration
{
    private const PERMISO = 'listar-cuentacorriente-proveedor';

    private const ROL = 'Op-Finanzas';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        $rolId = $this->resolverRolId();
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }

        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }

        $this->invalidarCache($rolId);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        $rolId = $this->resolverRolId();
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }

        DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->where('rol_id', $rolId)
            ->delete();

        $this->invalidarCache($rolId);
    }

    private function resolverRolId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('rol')->where('nombre', 'like', 'Op-Finanz%')->orderBy('id')->value('id') ?? 0);
    }

    private function invalidarCache(int $rolId): void
    {
        SuitecrmPermiso::flushCachePermisos();
        PermisoCacheSupport::forgetRol($rolId);
    }
};
