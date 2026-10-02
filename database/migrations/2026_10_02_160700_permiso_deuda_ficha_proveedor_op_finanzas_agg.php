<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: Op-Finanzas ve el reporte Deuda / ficha de proveedores
 * (Cuentas a pagar → Reportes). Martina Vallejos, Lucas Romero,
 * Laila Bertani y Yanina Gonzalez.
 */
return new class extends Migration
{
    private const PERMISO = 'listar-proveedor-cuentacorriente-reporte';

    private const MENU_URL = 'compras/proveedor-cuentacorriente-reporte';

    private const ROL = 'Op-Finanzas';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolId = $this->resolverRolId();
        if ($rolId <= 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0 && ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
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

        $rolId = $this->resolverRolId();
        if ($rolId <= 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->where('rol_id', $rolId)
                ->delete();
        }

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
