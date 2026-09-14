<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú Stock de locales + Consulta de precios (Facturación Local) — solo Calzados Ferli.
 * Equivalente Anita: c-stocklocal.c / c-articulo.c.
 */
return new class extends Migration
{
    private const PADRE_URL = '#facturacion-local';

    /** @var list<array{url:string,nombre:string,icono:string,orden:int}> */
    private const HIJOS = [
        ['url' => 'ventas/facturacion-local/stock', 'nombre' => 'Stock de locales', 'icono' => 'fa-boxes', 'orden' => 5],
        ['url' => 'ventas/facturacion-local/consulta-precios', 'nombre' => 'Consulta de precios', 'icono' => 'fa-tags', 'orden' => 6],
    ];

    /** @var list<array{nombre:string,slug:string}> */
    private const PERMISOS = [
        ['nombre' => 'Consultar stock locales', 'slug' => 'consultar-stock-local'],
        ['nombre' => 'Consultar precios locales', 'slug' => 'consultar-precios-local'],
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

        $padreId = (int) (DB::table('menu')->where('url', self::PADRE_URL)->orderBy('id')->value('id') ?? 0);
        if ($padreId === 0) {
            return;
        }

        $rolIds = $this->resolverRolIds(self::ROLES);
        $this->asignarRolesMenu($padreId, $rolIds);

        $menuPermiso = $padreId;
        foreach (self::HIJOS as $hijo) {
            $menuId = $this->upsertMenu($hijo['url'], $hijo['nombre'], $padreId, $hijo['orden'], $hijo['icono']);
            $this->asignarRolesMenu($menuId, $rolIds);
            if ($hijo['url'] === 'ventas/facturacion-local/stock') {
                $menuPermiso = $menuId;
            }
        }

        foreach (self::PERMISOS as $i => $permiso) {
            $menuId = $i === 0 ? $menuPermiso : (int) (DB::table('menu')
                ->where('url', 'ventas/facturacion-local/consulta-precios')
                ->value('id') ?? $menuPermiso);
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
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

        $urls = array_column(self::HIJOS, 'url');
        $menuIds = DB::table('menu')->whereIn('url', $urls)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
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
        if ($menuId <= 0 || $rolIds === []) {
            return;
        }
        foreach ($rolIds as $rolId) {
            $existe = DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
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
        if ($permisoId <= 0 || $rolIds === []) {
            return;
        }
        foreach ($rolIds as $rolId) {
            $existe = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
