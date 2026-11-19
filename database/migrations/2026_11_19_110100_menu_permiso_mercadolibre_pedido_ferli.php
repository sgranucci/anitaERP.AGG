<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú Pedidos Mercado Libre — solo Calzados Ferli.
 * Bajo Facturación Local (#facturacion-local).
 */
return new class extends Migration
{
    private const PADRE_URL = '#facturacion-local';

    private const URL = 'ventas/mercadolibre-pedidos';

    /** @var list<array{slug:string,nombre:string}> */
    private const PERMISOS = [
        ['slug' => 'listar-mercadolibre-pedidos', 'nombre' => 'Listar pedidos Mercado Libre'],
        ['slug' => 'sincronizar-mercadolibre-pedidos', 'nombre' => 'Sincronizar pedidos Mercado Libre'],
        ['slug' => 'facturar-mercadolibre-pedidos', 'nombre' => 'Facturar pedidos Mercado Libre'],
        ['slug' => 'autorizar-mercadolibre', 'nombre' => 'Autorizar app Mercado Libre'],
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

        $menuId = $this->upsertMenu(
            self::URL,
            'Pedidos Mercado Libre',
            $padreId,
            9,
            'fa-shopping-bag'
        );
        $this->asignarRolesMenu($menuId, $rolIds);

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

        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuIds = DB::table('menu')->where('url', self::URL)->pluck('id');
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
            'slug' => $slug,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];
        if ($id === 0) {
            $payload['created_at'] = now();

            return (int) DB::table('permiso')->insertGetId($payload);
        }

        DB::table('permiso')->where('id', $id)->update($payload);

        return $id;
    }

    /** @param list<string> $nombres @return list<int> */
    private function resolverRolIds(array $nombres): array
    {
        return DB::table('rol')->whereIn('nombre', $nombres)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @param list<int> $rolIds */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
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

    /** @param list<int> $rolIds */
    private function asignarPermisoRoles(int $permisoId, array $rolIds): void
    {
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
