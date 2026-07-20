<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menús/permisos para solicitudes de indumentaria: árbol de aprobación (config),
 * bandeja de aprobación y reporte de solicitudes. Dentro del submenú "Indumentaria".
 */
return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const MENU_SUBMENU = 'Indumentaria';

    /** @var list<array{url:string, nombre:string, icono:string, permisos:list<array{nombre:string, slug:string}>}> */
    private const ITEMS = [
        [
            'url' => 'sueldos/indumentaria/solicitudes',
            'nombre' => 'Solicitudes',
            'icono' => 'fa-clipboard-list',
            'permisos' => [
                ['nombre' => 'Listar solicitudes de indumentaria', 'slug' => 'listar-solicitud-indumentaria'],
                ['nombre' => 'Crear solicitudes de indumentaria', 'slug' => 'crear-solicitud-indumentaria'],
            ],
        ],
        [
            'url' => 'sueldos/indumentaria/bandeja',
            'nombre' => 'Bandeja de aprobación',
            'icono' => 'fa-inbox',
            'permisos' => [
                ['nombre' => 'Aprobar solicitudes de indumentaria', 'slug' => 'aprobar-solicitud-indumentaria'],
            ],
        ],
        [
            'url' => 'sueldos/indumentaria/aprobacion',
            'nombre' => 'Árbol de aprobación',
            'icono' => 'fa-sitemap',
            'permisos' => [
                ['nombre' => 'Ver árbol de aprobación de indumentaria', 'slug' => 'ver-aprobacion-indumentaria'],
                ['nombre' => 'Editar árbol de aprobación de indumentaria', 'slug' => 'editar-aprobacion-indumentaria'],
            ],
        ],
    ];

    public function up(): void
    {
        $moduloId = (int) (DB::table('menu')->where('nombre', self::MENU_MODULO)->where('menu_id', 0)->value('id') ?? 0);
        if ($moduloId === 0) {
            return;
        }
        $submenuId = (int) (DB::table('menu')->where('nombre', self::MENU_SUBMENU)->where('menu_id', $moduloId)->value('id') ?? 0);
        if ($submenuId === 0) {
            $ordenSubmenu = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1;
            $submenuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_SUBMENU, 'url' => '#', 'menu_id' => $moduloId,
                'orden' => $ordenSubmenu, 'icono' => 'fa-tshirt', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolIds();

        foreach (self::ITEMS as $item) {
            $orden = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
            $menuId = $this->upsertMenuHijo($item['url'], $item['nombre'], $submenuId, $orden, $item['icono']);

            $permisoIds = [];
            foreach ($item['permisos'] as $perm) {
                $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
            }

            foreach ($rolIds as $rolId) {
                $this->asegurarMenuRol($menuId, $rolId);
                $this->asegurarMenuRol($submenuId, $rolId);
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
        foreach (self::ITEMS as $item) {
            $slugs = array_map(fn ($p) => $p['slug'], $item['permisos']);
            $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
            if ($permisoIds->isNotEmpty()) {
                DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
                DB::table('permiso')->whereIn('id', $permisoIds)->delete();
            }
            $menuId = (int) (DB::table('menu')->where('url', $item['url'])->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenuHijo(string $url, string $nombre, int $padreId, int $orden, ?string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = ['nombre' => $nombre, 'url' => $url, 'menu_id' => $padreId, 'orden' => $orden, 'icono' => $icono, 'updated_at' => now()];
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

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        return DB::table('rol')
            ->where('nombre', 'administrador')
            ->orWhere('nombre', 'like', '%apital%umano%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
};
