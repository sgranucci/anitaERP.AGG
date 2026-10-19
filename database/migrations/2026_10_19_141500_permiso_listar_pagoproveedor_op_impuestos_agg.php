<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: Op-impuestos consulta y descarga la orden de pago desde Documentos relacionados.
 * Caso: gsurace (OPP en la OC figuraba «Sin permiso»).
 * Solo listar (ojo y PDF). Sin crear, editar ni confirmar.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/pagoproveedor';

    private const PADRE_NOMBRE = 'Cuentas a pagar';

    private const PERMISO = 'listar-pagoproveedor';

    private const ROL = 'Op-impuestos';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolId = $this->resolverRolId();
        if ($rolId <= 0) {
            return;
        }

        $menuId = $this->menuPagoProveedorId();
        if ($menuId > 0 && ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }

        $this->invalidarCacheRol($rolId);
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

        $menuId = $this->menuPagoProveedorId();
        if ($menuId > 0) {
            DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->where('rol_id', $rolId)
                ->delete();
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->delete();
        }

        $this->invalidarCacheRol($rolId);
    }

    /**
     * Hoja bajo Cuentas a pagar. La misma URL está repetida en Caja y Finanzas.
     */
    private function menuPagoProveedorId(): int
    {
        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::PADRE_NOMBRE)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        if ($padreId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('url', self::MENU_URL)
            ->where('menu_id', $padreId)
            ->value('id') ?? 0);
    }

    private function invalidarCacheRol(int $rolId): void
    {
        SuitecrmPermiso::flushCachePermisos();
        PermisoCacheSupport::forgetRol($rolId);
    }

    private function resolverRolId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('rol')->where('nombre', 'like', 'Op-impuesto%')->orderBy('id')->value('id') ?? 0);
    }
};
