<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú Picking pedidos (stock) — solo Calzados Ferli.
 */
return new class extends Migration
{
    private const MENU_URL = 'stock/picking-pedido';

    private const PERMISO_LISTAR = 'listar-reporte-picking-pedido';

    private const PERMISO_FACTURAR = 'facturar-picking-pedido';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Admin-ventas',
        'Enc-admin',
        'Ventas',
        'Deposito',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $padreId = (int) (DB::table('menu')
            ->where('nombre', 'Reportes Stock')
            ->where('url', '#')
            ->value('id') ?? 46);

        if ($padreId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Picking pedidos', $padreId, $orden, 'fa-clipboard-list');

        $rolIds = $this->resolverRolIds(self::ROLES);
        $this->asignarRolesMenu($menuId, $rolIds);

        $permisoListar = $this->upsertPermiso('Listar picking pedidos', self::PERMISO_LISTAR, $menuId);
        $permisoFacturar = $this->upsertPermiso('Facturar desde picking pedidos', self::PERMISO_FACTURAR, $menuId);
        $this->asignarPermisoRoles($permisoListar, $rolIds);
        $this->asignarPermisoRoles($permisoFacturar, $rolIds);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        foreach ([self::PERMISO_LISTAR, self::PERMISO_FACTURAR] as $slug) {
            $permisoIds = DB::table('permiso')->where('slug', $slug)->pluck('id');
            if ($permisoIds->isNotEmpty()) {
                DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
                DB::table('permiso')->whereIn('id', $permisoIds)->delete();
            }
        }

        $menuIds = DB::table('menu')->where('url', self::MENU_URL)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $existente = DB::table('menu')->where('url', $url)->first();
        if ($existente) {
            DB::table('menu')->where('id', $existente->id)->update([
                'nombre' => $nombre,
                'menu_id' => $padre,
                'orden' => $orden,
                'icono' => $icono,
                'updated_at' => now(),
            ]);

            return (int) $existente->id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $padre,
            'nombre' => $nombre,
            'url' => $url,
            'orden' => $orden,
            'icono' => $icono,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
