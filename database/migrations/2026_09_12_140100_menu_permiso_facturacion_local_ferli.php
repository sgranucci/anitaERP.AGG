<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permisos Facturación Local — solo Calzados Ferli.
 */
return new class extends Migration
{
    private const PADRE_URL = '#facturacion-local';

    private const PADRE_NOMBRE = 'Facturación Local';

    /** @var list<array{url:string,nombre:string,icono:string,orden:int}> */
    private const HIJOS = [
        ['url' => 'ventas/facturacion-local', 'nombre' => 'POS Local', 'icono' => 'fa-cash-register', 'orden' => 1],
        ['url' => 'ventas/facturacion-local/locales', 'nombre' => 'Locales', 'icono' => 'fa-store', 'orden' => 2],
        ['url' => 'ventas/facturacion-local/turnos', 'nombre' => 'Turnos', 'icono' => 'fa-clock', 'orden' => 3],
        // Sync canal: solo artisan (facturacion-local:sync-canal). Ver migración 2026_09_13_121000.
        ['url' => 'ventas/facturacion-local/reportes', 'nombre' => 'Reportes Local', 'icono' => 'fa-chart-bar', 'orden' => 4],
    ];

    /** @var list<array{nombre:string,slug:string}> */
    private const PERMISOS = [
        ['nombre' => 'Usar POS Facturación Local', 'slug' => 'usar-facturacion-local'],
        ['nombre' => 'Listar locales venta', 'slug' => 'listar-local-venta'],
        ['nombre' => 'Crear locales venta', 'slug' => 'crear-local-venta'],
        ['nombre' => 'Editar locales venta', 'slug' => 'editar-local-venta'],
        ['nombre' => 'Actualizar locales venta', 'slug' => 'actualizar-local-venta'],
        ['nombre' => 'Borrar locales venta', 'slug' => 'borrar-local-venta'],
        ['nombre' => 'Abrir turno Facturación Local', 'slug' => 'abrir-turno-facturacion-local'],
        ['nombre' => 'Cerrar turno Facturación Local', 'slug' => 'cerrar-turno-facturacion-local'],
        ['nombre' => 'Listar turnos Facturación Local', 'slug' => 'listar-turno-facturacion-local'],
        ['nombre' => 'Reportes Facturación Local', 'slug' => 'reportes-facturacion-local'],
    ];
    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Admin-ventas',
        'Enc-admin',
        'Ventas',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $ventasId = $this->resolverModuloVentasId();
        if ($ventasId === 0) {
            return;
        }

        $ordenPadre = (int) (DB::table('menu')->where('menu_id', $ventasId)->max('orden') ?? 0) + 1;
        $padreId = $this->upsertMenu(self::PADRE_URL, self::PADRE_NOMBRE, $ventasId, $ordenPadre, 'fa-store');

        $rolIds = $this->resolverRolIds(self::ROLES);
        $this->asignarRolesMenu($padreId, $rolIds);
        $this->asignarRolesMenu($ventasId, $rolIds);

        $menuPosId = 0;
        foreach (self::HIJOS as $hijo) {
            $menuId = $this->upsertMenu($hijo['url'], $hijo['nombre'], $padreId, $hijo['orden'], $hijo['icono']);
            $this->asignarRolesMenu($menuId, $rolIds);
            if ($hijo['url'] === 'ventas/facturacion-local') {
                $menuPosId = $menuId;
            }
        }

        $menuPermiso = $menuPosId > 0 ? $menuPosId : $padreId;
        foreach (self::PERMISOS as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuPermiso);
            $this->asignarPermisoRoles($permisoId, $rolIds);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $urls = array_merge([self::PADRE_URL], array_column(self::HIJOS, 'url'));
        $menuIds = DB::table('menu')->whereIn('url', $urls)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverModuloVentasId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'Módulo de Ventas')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $id = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'Módulo Ventas')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'ventas/factura')->value('menu_id') ?? 0);
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->where('menu_id', $padre)->value('id') ?? 0);
        if ($id === 0) {
            $id = (int) (DB::table('menu')->where('url', $url)->orderBy('id')->value('id') ?? 0);
        }

        if ($id === 0) {
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

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padre,
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => mb_substr($nombre, 0, 50),
            'menu_id' => $menuId > 0 ? $menuId : null,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => $slug,
            'created_at' => now(),
        ]));
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
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

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        if ($menuId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarPermisoRoles(int $permisoId, array $rolIds): void
    {
        if ($permisoId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
