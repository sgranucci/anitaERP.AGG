<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const MENU_SUBMENU = 'Reportes de Sueldos';

    private const HIJO_URL = 'sueldos/reporte-definible';

    private const HIJO_NOMBRE = 'Reportes definibles';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar reportes sueldos definibles', 'slug' => 'listar-reporte-sueldos-definible'],
        ['nombre' => 'Crear reporte sueldos definible', 'slug' => 'crear-reporte-sueldos-definible'],
        ['nombre' => 'Editar reporte sueldos definible', 'slug' => 'editar-reporte-sueldos-definible'],
        ['nombre' => 'Actualizar reporte sueldos definible', 'slug' => 'actualizar-reporte-sueldos-definible'],
        ['nombre' => 'Eliminar reporte sueldos definible', 'slug' => 'eliminar-reporte-sueldos-definible'],
        ['nombre' => 'Ejecutar reporte sueldos definible', 'slug' => 'ejecutar-reporte-sueldos-definible'],
        ['nombre' => 'Importar reportes sueldos definibles Anita', 'slug' => 'importar-reporte-sueldos-definible'],
    ];

    public function up(): void
    {
        $moduloId = (int) (DB::table('menu')->where('nombre', self::MENU_MODULO)->where('menu_id', 0)->value('id') ?? 0);
        if ($moduloId === 0) {
            $ordenModulo = (int) (DB::table('menu')->where('menu_id', 0)->max('orden') ?? 0) + 1;
            $moduloId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_MODULO,
                'url' => '#',
                'menu_id' => 0,
                'orden' => $ordenModulo,
                'icono' => 'fa-money',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $submenuId = (int) (DB::table('menu')->where('nombre', self::MENU_SUBMENU)->where('menu_id', $moduloId)->value('id') ?? 0);
        if ($submenuId === 0) {
            $ordenSubmenu = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1;
            $submenuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_SUBMENU,
                'url' => '#',
                'menu_id' => $moduloId,
                'orden' => $ordenSubmenu,
                'icono' => 'fa-chart-bar',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($moduloId, $rolId);
            $this->asegurarMenuRol($submenuId, $rolId);
        }

        $existente = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        $orden = $existente > 0
            ? (int) (DB::table('menu')->where('id', $existente)->value('orden') ?? 0)
            : (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenuHijo(self::HIJO_URL, self::HIJO_NOMBRE, $submenuId, $orden);

        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
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

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = array_map(fn ($p) => $p['slug'], self::PERMISOS);
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenuHijo(string $url, string $nombre, int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'url' => $url,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => 'fa-table',
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        $payload['created_at'] = now();

        return (int) DB::table('menu')->insertGetId($payload);
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

        $payload['created_at'] = now();

        return (int) DB::table('permiso')->insertGetId($payload);
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
        $nombres = ['administrador', 'Enc-sueldos', 'Op-sueldos', 'Enc-personal', 'Op-personal'];
        $ids = DB::table('rol')->whereIn('nombre', $nombres)->pluck('id')->map(fn ($v) => (int) $v)->all();
        if ($ids === []) {
            $admin = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
            if ($admin > 0) {
                $ids[] = $admin;
            }
        }

        return array_values(array_unique($ids));
    }
};
