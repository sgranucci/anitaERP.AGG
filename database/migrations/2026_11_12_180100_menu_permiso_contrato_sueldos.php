<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú canónico: Configuración → Configuración por módulo → Sueldos → Plantillas de contrato.
 * Atajo operativo solo administrador: Módulo Sueldos → Tablas de Sueldos.
 * Permisos de emisión sobre la solapa del empleado.
 */
return new class extends Migration
{
    private const URL_CANONICA = 'sueldos/contrato-plantilla';

    private const URL_ATAJO = 'sueldos/contrato-plantilla-atajo';

    private const CONFIG_MODULO = '#config-modulo-sueldos';

    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const MENU_TABLAS = 'Tablas de Sueldos';

    /** @var list<string> */
    private const ROLES_CONFIG = ['administrador', 'Enc-sueldos', 'Sueldos'];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar plantillas contrato sueldos', 'slug' => 'listar-contrato-plantilla-sueldos'],
        ['nombre' => 'Crear plantilla contrato sueldos', 'slug' => 'crear-contrato-plantilla-sueldos'],
        ['nombre' => 'Editar plantilla contrato sueldos', 'slug' => 'editar-contrato-plantilla-sueldos'],
        ['nombre' => 'Actualizar plantilla contrato sueldos', 'slug' => 'actualizar-contrato-plantilla-sueldos'],
        ['nombre' => 'Borrar plantilla contrato sueldos', 'slug' => 'borrar-contrato-plantilla-sueldos'],
        ['nombre' => 'Emitir contrato empleado sueldos', 'slug' => 'emitir-contrato-empleado-sueldos'],
    ];

    public function up(): void
    {
        $configModuloId = $this->asegurarConfigModuloSueldos();
        $menuCanonicoId = $this->upsertMenu(
            self::URL_CANONICA,
            'Plantillas de contrato',
            $configModuloId,
            (int) (DB::table('menu')->where('menu_id', $configModuloId)->max('orden') ?? 0) + 1,
            'fa-file-word-o'
        );

        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuCanonicoId);
        }

        $rolIds = $this->resolverRolIds(self::ROLES_CONFIG);
        foreach ($this->ancestrosMenu($configModuloId) as $menuId) {
            foreach ($rolIds as $rolId) {
                $this->asegurarMenuRol($menuId, $rolId);
            }
        }
        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($menuCanonicoId, $rolId);
            foreach ($permisoIds as $permisoId) {
                $this->asegurarPermisoRol($permisoId, $rolId);
            }
        }

        // Atajo bajo el módulo operativo: solo administrador
        $tablasId = $this->asegurarTablasSueldos();
        if ($tablasId > 0) {
            $atajoId = $this->upsertMenu(
                self::URL_ATAJO,
                'Plantillas de contrato',
                $tablasId,
                (int) (DB::table('menu')->where('menu_id', $tablasId)->max('orden') ?? 0) + 1,
                'fa-file-word-o'
            );
            // Misma URL operativa que la canónica (redirect en rutas)
            DB::table('menu')->where('id', $atajoId)->update([
                'url' => self::URL_CANONICA,
                'updated_at' => now(),
            ]);
            $adminIds = $this->resolverRolIds(['administrador']);
            foreach ($adminIds as $rolId) {
                $this->asegurarMenuRol($atajoId, $rolId);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuIds = DB::table('menu')
            ->whereIn('url', [self::URL_CANONICA, self::URL_ATAJO])
            ->orWhere(function ($q) {
                $q->where('nombre', 'Plantillas de contrato')
                    ->where('url', self::URL_CANONICA);
            })
            ->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarConfigModuloSueldos(): int
    {
        $id = (int) (DB::table('menu')->where('url', self::CONFIG_MODULO)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $grupoId = (int) (DB::table('menu')->where('url', '#configuracion-por-modulo')->value('id') ?? 0);
        if ($grupoId === 0) {
            $configRoot = (int) (DB::table('menu')->where('nombre', 'Configuración')->where('menu_id', 0)->value('id') ?? 0);
            if ($configRoot === 0) {
                $configRoot = (int) DB::table('menu')->insertGetId([
                    'nombre' => 'Configuración',
                    'url' => '#',
                    'menu_id' => 0,
                    'orden' => 90,
                    'icono' => 'fa-cog',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $grupoId = (int) DB::table('menu')->insertGetId([
                'nombre' => 'Configuración por módulo',
                'url' => '#configuracion-por-modulo',
                'menu_id' => $configRoot,
                'orden' => 10,
                'icono' => 'fa-cogs',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return (int) DB::table('menu')->insertGetId([
            'nombre' => 'Sueldos',
            'url' => self::CONFIG_MODULO,
            'menu_id' => $grupoId,
            'orden' => 6,
            'icono' => 'fa-money',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function asegurarTablasSueldos(): int
    {
        $moduloId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_MODULO)
            ->where(function ($q) {
                $q->where('menu_id', 0)->orWhereNull('menu_id');
            })
            ->value('id') ?? 0);
        if ($moduloId === 0) {
            return 0;
        }

        $id = (int) (DB::table('menu')
            ->where('nombre', self::MENU_TABLAS)
            ->where('menu_id', $moduloId)
            ->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'nombre' => self::MENU_TABLAS,
            'url' => '#',
            'menu_id' => $moduloId,
            'orden' => (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1,
            'icono' => 'fa-table',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<int>
     */
    private function ancestrosMenu(int $menuId): array
    {
        $ids = [$menuId];
        $actual = $menuId;
        for ($i = 0; $i < 8; $i++) {
            $padre = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
            if ($padre <= 0) {
                break;
            }
            $ids[] = $padre;
            $actual = $padre;
        }

        return $ids;
    }

    private function upsertMenu(string $url, string $nombre, int $padreId, int $orden, ?string $icono): int
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

    private function asegurarPermisoRol(int $permisoId, int $rolId): void
    {
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
        }
    }

    /** @return list<int> */
    private function resolverRolIds(array $nombres): array
    {
        return DB::table('rol')->whereIn('nombre', $nombres)->pluck('id')
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
};
