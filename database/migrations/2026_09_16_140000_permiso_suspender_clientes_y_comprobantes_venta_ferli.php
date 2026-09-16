<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: Laura (rol Ventas) no podía cambiar estado de clientes ni ver
 * Comprobantes de venta. Faltaban permiso suspender-clientes y permisos listar/crear/
 * editar/actualizar-factura, y menu_rol del ítem ventas/factura.
 */
return new class extends Migration
{
    private const MENU_CLIENTE_URL = 'ventas/cliente';

    private const MENU_FACTURA_URL = 'ventas/factura';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS_FACTURA = [
        ['nombre' => 'Lista factura', 'slug' => 'listar-factura'],
        ['nombre' => 'Ingresa factura', 'slug' => 'crear-factura'],
        ['nombre' => 'Edita factura', 'slug' => 'editar-factura'],
        ['nombre' => 'Actualiza factura', 'slug' => 'actualizar-factura'],
    ];

    private const PERMISO_SUSPENDER = [
        'nombre' => 'Suspender clientes',
        'slug' => 'suspender-clientes',
    ];

    /** Roles operativos de ventas (incluye Laura). */
    /** @var list<string> */
    private const ROLES_VENTAS = [
        'administrador',
        'Enc-admin',
        'Admin-ventas',
        'Ventas',
        'Oficina',
        'Enc-contaduría',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $rolIds = $this->resolverRolIds(self::ROLES_VENTAS);
        if ($rolIds === []) {
            return;
        }

        $menuClienteId = (int) (DB::table('menu')->where('url', self::MENU_CLIENTE_URL)->value('id') ?? 0);
        $menuFacturaId = (int) (DB::table('menu')->where('url', self::MENU_FACTURA_URL)->value('id') ?? 0);

        if ($menuClienteId > 0) {
            $permisoSuspenderId = $this->upsertPermiso(
                self::PERMISO_SUSPENDER['nombre'],
                self::PERMISO_SUSPENDER['slug'],
                $menuClienteId
            );
            $this->asignarPermisoRoles($permisoSuspenderId, $rolIds);
            $this->asignarRolesMenu($menuClienteId, $rolIds);
        }

        if ($menuFacturaId > 0) {
            $this->asignarRolesMenu($menuFacturaId, $rolIds);

            $padreId = (int) (DB::table('menu')->where('id', $menuFacturaId)->value('menu_id') ?? 0);
            if ($padreId > 0) {
                $this->asignarRolesMenu($padreId, $rolIds);
            }

            foreach (self::PERMISOS_FACTURA as $perm) {
                $permisoId = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuFacturaId);
                $this->asignarPermisoRoles($permisoId, $rolIds);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $rolIds = $this->resolverRolIds(self::ROLES_VENTAS);
        if ($rolIds === []) {
            return;
        }

        $slugs = array_merge(
            [self::PERMISO_SUSPENDER['slug']],
            array_column(self::PERMISOS_FACTURA, 'slug')
        );
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        if ($permisoIds !== []) {
            DB::table('permiso_rol')
                ->whereIn('permiso_id', $permisoIds)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        $menuFacturaId = (int) (DB::table('menu')->where('url', self::MENU_FACTURA_URL)->value('id') ?? 0);
        if ($menuFacturaId > 0) {
            // Solo quitar Ventas / Admin-ventas (los otros roles ya tenían el menú antes).
            $rolesSoloAgregados = $this->resolverRolIds(['Ventas', 'Admin-ventas']);
            if ($rolesSoloAgregados !== []) {
                DB::table('menu_rol')
                    ->where('menu_id', $menuFacturaId)
                    ->whereIn('rol_id', $rolesSoloAgregados)
                    ->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $existente = DB::table('permiso')->where('slug', $slug)->first();
        if ($existente) {
            DB::table('permiso')->where('id', $existente->id)->update([
                'nombre' => $nombre,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);

            return (int) $existente->id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param list<int> $rolIds */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            DB::table('menu_rol')->updateOrInsert(['menu_id' => $menuId, 'rol_id' => $rolId], []);
        }
    }

    /** @param list<int> $rolIds */
    private function asignarPermisoRoles(int $permisoId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            DB::table('permiso_rol')->updateOrInsert(['permiso_id' => $permisoId, 'rol_id' => $rolId], []);
        }
    }

    /** @param list<string> $nombres @return list<int> */
    private function resolverRolIds(array $nombres): array
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
