<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const MENU_SUBMENU = 'Tablas de Sueldos';

    /** @var list<string> */
    private const ROLES = ['administrador'];

    /** @var list<array{url: string, nombre: string, permisos: list<array{nombre: string, slug: string}>}> */
    private const HIJOS = [
        [
            'url' => 'sueldos/obrasocial',
            'nombre' => 'Obras sociales',
            'permisos' => [
                ['nombre' => 'Listar obra social sueldos', 'slug' => 'listar-obrasocial-sueldos'],
                ['nombre' => 'Crear obra social sueldos', 'slug' => 'crear-obrasocial-sueldos'],
                ['nombre' => 'Editar obra social sueldos', 'slug' => 'editar-obrasocial-sueldos'],
                ['nombre' => 'Actualizar obra social sueldos', 'slug' => 'actualizar-obrasocial-sueldos'],
                ['nombre' => 'Borrar obra social sueldos', 'slug' => 'borrar-obrasocial-sueldos'],
            ],
        ],
        [
            'url' => 'sueldos/sindicato',
            'nombre' => 'Sindicatos',
            'permisos' => [
                ['nombre' => 'Listar sindicato sueldos', 'slug' => 'listar-sindicato-sueldos'],
                ['nombre' => 'Crear sindicato sueldos', 'slug' => 'crear-sindicato-sueldos'],
                ['nombre' => 'Editar sindicato sueldos', 'slug' => 'editar-sindicato-sueldos'],
                ['nombre' => 'Actualizar sindicato sueldos', 'slug' => 'actualizar-sindicato-sueldos'],
                ['nombre' => 'Borrar sindicato sueldos', 'slug' => 'borrar-sindicato-sueldos'],
            ],
        ],
    ];

    public function up(): void
    {
        $moduloId = (int) (DB::table('menu')->where('nombre', self::MENU_MODULO)->where('menu_id', 0)->value('id') ?? 0);
        if ($moduloId === 0) {
            $ordenModulo = (int) (DB::table('menu')->where('menu_id', 0)->max('orden') ?? 0) + 1;
            $moduloId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_MODULO, 'url' => '#', 'menu_id' => 0,
                'orden' => $ordenModulo, 'icono' => 'fa-money', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $submenuId = (int) (DB::table('menu')->where('nombre', self::MENU_SUBMENU)->where('menu_id', $moduloId)->value('id') ?? 0);
        if ($submenuId === 0) {
            $ordenSubmenu = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1;
            $submenuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_SUBMENU, 'url' => '#', 'menu_id' => $moduloId,
                'orden' => $ordenSubmenu, 'icono' => 'fa-table', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($moduloId, $rolId);
            $this->asegurarMenuRol($submenuId, $rolId);
        }

        $ordenHijo = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0);
        foreach (self::HIJOS as $hijo) {
            $existente = (int) (DB::table('menu')->where('url', $hijo['url'])->value('id') ?? 0);
            if ($existente > 0) {
                $orden = (int) (DB::table('menu')->where('id', $existente)->value('orden') ?? 0);
            } else {
                $ordenHijo++;
                $orden = $ordenHijo;
            }
            $menuId = $this->upsertMenuHijo($hijo['url'], $hijo['nombre'], $submenuId, $orden);

            $permisoIds = [];
            foreach ($hijo['permisos'] as $perm) {
                $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
            }

            foreach ($rolIds as $rolId) {
                $this->asegurarMenuRol($menuId, $rolId);
                foreach ($permisoIds as $permisoId) {
                    if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                        DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                    }
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = [];
        $urls = [];
        foreach (self::HIJOS as $hijo) {
            $urls[] = $hijo['url'];
            foreach ($hijo['permisos'] as $perm) {
                $slugs[] = $perm['slug'];
            }
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuIds = DB::table('menu')->whereIn('url', $urls)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenuHijo(string $url, string $nombre, int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = ['nombre' => $nombre, 'url' => $url, 'menu_id' => $padreId, 'orden' => $orden, 'icono' => null, 'updated_at' => now()];
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = ['nombre' => $nombre, 'slug' => $slug, 'menu_id' => $menuId, 'updated_at' => now()];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        return DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id')
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
};
