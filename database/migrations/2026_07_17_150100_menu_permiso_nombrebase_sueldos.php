<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const MENU_MODULO_ICONO = 'fa-money';

    private const MENU_SUBMENU = 'Tablas de Sueldos';

    private const MENU_SUBMENU_ICONO = 'fa-table';

    /** @var list<string> */
    private const ROLES = ['administrador'];

    /** @var list<array{url: string, nombre: string, icono: string|null, permisos: list<array{nombre: string, slug: string}>}> */
    private const HIJOS = [
        [
            'url' => 'sueldos/nombrebase',
            'nombre' => 'Nombres de bases',
            'icono' => null,
            'permisos' => [
                ['nombre' => 'Listar nombre de base sueldos', 'slug' => 'listar-nombrebase-sueldos'],
                ['nombre' => 'Crear nombre de base sueldos', 'slug' => 'crear-nombrebase-sueldos'],
                ['nombre' => 'Editar nombre de base sueldos', 'slug' => 'editar-nombrebase-sueldos'],
                ['nombre' => 'Actualizar nombre de base sueldos', 'slug' => 'actualizar-nombrebase-sueldos'],
                ['nombre' => 'Borrar nombre de base sueldos', 'slug' => 'borrar-nombrebase-sueldos'],
            ],
        ],
    ];

    public function up(): void
    {
        $ordenModulo = (int) (DB::table('menu')
            ->where('nombre', self::MENU_MODULO)
            ->where('menu_id', 0)
            ->value('orden')
            ?? ((int) (DB::table('menu')->where('menu_id', 0)->max('orden') ?? 0) + 1));
        $moduloId = $this->upsertMenuModulo(self::MENU_MODULO, $ordenModulo, self::MENU_MODULO_ICONO);

        $ordenSubmenu = (int) (DB::table('menu')
            ->where('nombre', self::MENU_SUBMENU)
            ->where('menu_id', $moduloId)
            ->value('orden')
            ?? ((int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1));
        $submenuId = $this->upsertMenuContenedor(self::MENU_SUBMENU, $moduloId, $ordenSubmenu, self::MENU_SUBMENU_ICONO);

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($moduloId, $rolId);
            $this->asegurarMenuRol($submenuId, $rolId);
        }

        $ordenHijo = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0);
        foreach (self::HIJOS as $hijo) {
            $existenteHijoId = (int) (DB::table('menu')->where('url', $hijo['url'])->value('id') ?? 0);
            if ($existenteHijoId > 0) {
                $orden = (int) (DB::table('menu')->where('id', $existenteHijoId)->value('orden') ?? 0);
            } else {
                $ordenHijo++;
                $orden = $ordenHijo;
            }
            $menuId = $this->upsertMenuHijo($hijo['url'], $hijo['nombre'], $submenuId, $orden, $hijo['icono']);

            $permisoIds = [];
            foreach ($hijo['permisos'] as $perm) {
                $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
            }

            foreach ($rolIds as $rolId) {
                $this->asegurarMenuRol($menuId, $rolId);
                foreach ($permisoIds as $permisoId) {
                    if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                        DB::table('permiso_rol')->insert([
                            'permiso_id' => $permisoId,
                            'rol_id' => $rolId,
                        ]);
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

        // Solo borrar el submenú contenedor si ya no le quedan hijos.
        $moduloId = (int) (DB::table('menu')->where('nombre', self::MENU_MODULO)->where('menu_id', 0)->value('id') ?? 0);
        if ($moduloId > 0) {
            $submenuId = (int) (DB::table('menu')->where('nombre', self::MENU_SUBMENU)->where('menu_id', $moduloId)->value('id') ?? 0);
            if ($submenuId > 0 && ! DB::table('menu')->where('menu_id', $submenuId)->exists()) {
                DB::table('menu_rol')->where('menu_id', $submenuId)->delete();
                DB::table('menu')->where('id', $submenuId)->delete();
            }
            // Solo borrar el módulo si ya no le quedan hijos.
            if (! DB::table('menu')->where('menu_id', $moduloId)->exists()) {
                DB::table('menu_rol')->where('menu_id', $moduloId)->delete();
                DB::table('menu')->where('id', $moduloId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenuModulo(string $nombre, int $orden, ?string $icono): int
    {
        return $this->upsertMenuPorNombrePadre($nombre, '#', 0, $orden, $icono);
    }

    private function upsertMenuContenedor(string $nombre, int $padreId, int $orden, ?string $icono): int
    {
        return $this->upsertMenuPorNombrePadre($nombre, '#', $padreId, $orden, $icono);
    }

    private function upsertMenuPorNombrePadre(string $nombre, string $url, int $padreId, int $orden, ?string $icono): int
    {
        $id = (int) (DB::table('menu')->where('nombre', $nombre)->where('menu_id', $padreId)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'url' => $url,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ];

        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function upsertMenuHijo(string $url, string $nombre, int $padreId, int $orden, ?string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'url' => $url,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ];

        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];

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
        return DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
};
