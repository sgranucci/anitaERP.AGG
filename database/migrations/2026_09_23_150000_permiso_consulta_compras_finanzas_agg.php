<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: Finanzas (mbmendez Enc / mvallejos Op y resto) consulta OC, facturas y pagos
 * a proveedores. Mismo criterio que Contaduría / Control de Gestión:
 * listar + editar en OC y facturas (sin alta/escritura); pagos solo listar (índice).
 * Deuda / ficha y sábana ya estaban asignados a estos roles.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ROLES = [
        'Enc-finanzas',
        'Op-Finanzas',
    ];

    /**
     * Menú hoja + permisos de consulta (sin crear/actualizar/borrar).
     *
     * @var list<array{url: string, permisos: list<string>}>
     */
    private const MENUS = [
        [
            'url' => 'compras/ordencompra',
            'permisos' => [
                'listar-ordencompra',
                'editar-ordencompra',
            ],
        ],
        [
            'url' => 'compras/comprobante-proveedor',
            'permisos' => [
                'listar-comprobante-proveedor',
                'editar-comprobante-proveedor',
            ],
        ],
        [
            'url' => 'compras/pagoproveedor',
            'permisos' => [
                'listar-pagoproveedor',
            ],
        ],
    ];

    /** Sin sector_legajocompra_id el index de OC queda vacío (1=0). */
    private const PERMISO_TODOS_SECTORES = 'listar-todos-sector-legajo-compra';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolIds = $this->resolverRolIds();
        if ($rolIds === []) {
            return;
        }

        foreach (self::MENUS as $menuDef) {
            $menuId = (int) (DB::table('menu')->where('url', $menuDef['url'])->value('id') ?? 0);
            if ($menuId > 0) {
                foreach ($rolIds as $rolId) {
                    $this->asegurarMenuRol($menuId, $rolId);
                }
            }

            foreach ($menuDef['permisos'] as $slug) {
                $this->asegurarPermisoRol($slug, $rolIds);
            }
        }

        $this->asegurarPermisoRol(self::PERMISO_TODOS_SECTORES, $rolIds);

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

        $slugs = [self::PERMISO_TODOS_SECTORES];
        foreach (self::MENUS as $menuDef) {
            $menuId = (int) (DB::table('menu')->where('url', $menuDef['url'])->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')
                    ->where('menu_id', $menuId)
                    ->whereIn('rol_id', $rolIds)
                    ->delete();
            }
            foreach ($menuDef['permisos'] as $slug) {
                $slugs[] = $slug;
            }
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', array_values(array_unique($slugs)))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($permisoIds !== []) {
            DB::table('permiso_rol')
                ->whereIn('permiso_id', $permisoIds)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        $this->invalidarCacheRoles($rolIds);
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asegurarPermisoRol(string $slug, array $rolIds): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
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
                    ? 'Enc-finanz%'
                    : 'Op-Finanz%';
                $id = (int) (DB::table('rol')->where('nombre', 'like', $like)->orderBy('id')->value('id') ?? 0);
            }
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
};
