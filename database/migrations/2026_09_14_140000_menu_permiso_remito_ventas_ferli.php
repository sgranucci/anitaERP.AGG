<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: asegura menú y permisos del ABM Remitos en Módulo de Ventas.
 *
 * El ítem ya no se oculta en MenuFerliSupport; las rutas ventas/remito están
 * habilitadas para Ferli (salvo Interforming).
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/remito';

    private const MENU_NOMBRE = 'Remitos';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar remitos', 'slug' => 'listar-remitos'],
        ['nombre' => 'Crear remitos', 'slug' => 'crear-remitos'],
        ['nombre' => 'Editar remitos', 'slug' => 'editar-remitos'],
        ['nombre' => 'Actualizar remitos', 'slug' => 'actualizar-remitos'],
        ['nombre' => 'Borrar remitos', 'slug' => 'borrar-remitos'],
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Admin-ventas',
        'Ventas',
        'Oficina',
        'Deposito',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $parentMenuId = (int) (DB::table('menu')
            ->where('nombre', 'Módulo de Ventas')
            ->where('url', '#')
            ->value('id') ?? 0);

        if ($parentMenuId <= 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'ventas/pedido')->value('menu_id') ?? 0);
        }

        if ($parentMenuId <= 0) {
            return;
        }

        $existente = DB::table('menu')->where('url', self::MENU_URL)->first();
        $ordenPedido = (int) (DB::table('menu')->where('url', 'ventas/pedido')->value('orden') ?? 2);
        $orden = $existente
            ? (int) ($existente->orden ?? ($ordenPedido + 1))
            : $ordenPedido + 1;

        // Si quedó al final del módulo, acercarlo a Pedidos (sin reordenar hermanos).
        $maxOrden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0);
        if ($existente && (int) $existente->orden >= $maxOrden - 1) {
            $orden = $ordenPedido + 1;
        }

        $menuId = $this->upsertMenu(self::MENU_URL, self::MENU_NOMBRE, $parentMenuId, $orden, 'fa-truck');

        $rolIds = $this->resolverRolIds(self::ROLES);
        $this->asignarRolesMenu($menuId, $rolIds);
        $this->asignarRolesMenu($parentMenuId, $rolIds);

        foreach (self::PERMISOS as $perm) {
            $permisoId = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
            $this->asignarPermisoRoles($permisoId, $rolIds);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $rolIds = $this->resolverRolIds(self::ROLES);
        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();

        if ($permisoIds !== []) {
            DB::table('permiso_rol')
                ->whereIn('permiso_id', $permisoIds)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        DB::table('menu_rol')->where('menu_id', $menuId)->whereIn('rol_id', $rolIds)->delete();

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
