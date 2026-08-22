<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const MENU_TABLAS = 'Tablas de Sueldos';

    private const MENU_REPORTES = 'Reportes de Sueldos';

    /** @var list<array{url: string, nombre: string, submenu: string, permisos: list<array{nombre: string, slug: string}>}> */
    private const ITEMS = [
        [
            'url' => 'sueldos/tipo-sancion',
            'nombre' => 'Tipos de sanción',
            'submenu' => self::MENU_TABLAS,
            'permisos' => [
                ['nombre' => 'Listar tipo sancion sueldos', 'slug' => 'listar-tipo-sancion-sueldos'],
                ['nombre' => 'Crear tipo sancion sueldos', 'slug' => 'crear-tipo-sancion-sueldos'],
                ['nombre' => 'Editar tipo sancion sueldos', 'slug' => 'editar-tipo-sancion-sueldos'],
                ['nombre' => 'Actualizar tipo sancion sueldos', 'slug' => 'actualizar-tipo-sancion-sueldos'],
                ['nombre' => 'Borrar tipo sancion sueldos', 'slug' => 'borrar-tipo-sancion-sueldos'],
            ],
        ],
        [
            'url' => 'sueldos/motivo-sancion',
            'nombre' => 'Motivos de sanción',
            'submenu' => self::MENU_TABLAS,
            'permisos' => [
                ['nombre' => 'Listar motivo sancion sueldos', 'slug' => 'listar-motivo-sancion-sueldos'],
                ['nombre' => 'Crear motivo sancion sueldos', 'slug' => 'crear-motivo-sancion-sueldos'],
                ['nombre' => 'Editar motivo sancion sueldos', 'slug' => 'editar-motivo-sancion-sueldos'],
                ['nombre' => 'Actualizar motivo sancion sueldos', 'slug' => 'actualizar-motivo-sancion-sueldos'],
                ['nombre' => 'Borrar motivo sancion sueldos', 'slug' => 'borrar-motivo-sancion-sueldos'],
            ],
        ],
        [
            'url' => 'sueldos/sancion-reporte',
            'nombre' => 'Sanciones de empleados',
            'submenu' => self::MENU_REPORTES,
            'permisos' => [
                ['nombre' => 'Listar sancion reporte sueldos', 'slug' => 'listar-sancion-reporte-sueldos'],
                ['nombre' => 'Listar sancion empleado sueldos', 'slug' => 'listar-sancion-empleado-sueldos'],
                ['nombre' => 'Crear sancion empleado sueldos', 'slug' => 'crear-sancion-empleado-sueldos'],
                ['nombre' => 'Actualizar sancion empleado sueldos', 'slug' => 'actualizar-sancion-empleado-sueldos'],
                ['nombre' => 'Anular sancion empleado sueldos', 'slug' => 'anular-sancion-empleado-sueldos'],
                ['nombre' => 'Imprimir sancion sueldos', 'slug' => 'imprimir-sancion-sueldos'],
            ],
        ],
    ];

    public function up(): void
    {
        $moduloId = $this->asegurarMenu(self::MENU_MODULO, 0, 'fa-money');
        $tablasId = $this->asegurarMenu(self::MENU_TABLAS, $moduloId, 'fa-table');
        $reportesId = $this->asegurarMenu(self::MENU_REPORTES, $moduloId, 'fa-file-alt');

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($moduloId, $rolId);
            $this->asegurarMenuRol($tablasId, $rolId);
            $this->asegurarMenuRol($reportesId, $rolId);
        }

        foreach (self::ITEMS as $item) {
            $padreId = $item['submenu'] === self::MENU_REPORTES ? $reportesId : $tablasId;
            $existente = (int) (DB::table('menu')->where('url', $item['url'])->value('id') ?? 0);
            $orden = $existente > 0
                ? (int) (DB::table('menu')->where('id', $existente)->value('orden') ?? 0)
                : (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
            $menuId = $this->upsertMenuHijo($item['url'], $item['nombre'], $padreId, $orden);

            $permisoIds = [];
            foreach ($item['permisos'] as $perm) {
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
        foreach (self::ITEMS as $item) {
            $urls[] = $item['url'];
            foreach ($item['permisos'] as $perm) {
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

    private function asegurarMenu(string $nombre, int $padreId, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('nombre', $nombre)->where('menu_id', $padreId)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;

        return (int) DB::table('menu')->insertGetId([
            'nombre' => $nombre,
            'url' => '#',
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => $icono,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertMenuHijo(string $url, string $nombre, int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'url' => $url,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => null,
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
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
};
