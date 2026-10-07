<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: Control de Gestión consulta la orden de pago desde Documentos de la OC.
 * Solo listar (ojo y PDF). Sin crear, editar ni confirmar.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/pagoproveedor';

    private const PADRE_NOMBRE = 'Cuentas a pagar';

    private const PERMISO = 'listar-pagoproveedor';

    /** @var list<string> */
    private const ROLES = [
        'Enc-Control de Gestión',
        'Op-Control de Gestión',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolIds = $this->resolverRolIds();
        if ($rolIds === []) {
            return;
        }

        $menuId = $this->menuPagoProveedorId();
        if ($menuId > 0) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        $this->invalidarCacheRoles($rolIds);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolIds = $this->resolverRolIds();
        if ($rolIds === []) {
            return;
        }

        $menuId = $this->menuPagoProveedorId();
        if ($menuId > 0) {
            DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        $this->invalidarCacheRoles($rolIds);
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
            if ($id <= 0) {
                $like = str_starts_with($nombre, 'Enc-')
                    ? 'Enc-Control de Gesti%'
                    : 'Op-Control de Gesti%';
                $id = (int) (DB::table('rol')->where('nombre', 'like', $like)->orderBy('id')->value('id') ?? 0);
            }
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
};
