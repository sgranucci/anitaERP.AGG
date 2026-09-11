<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Certificados ARCA (CSR + instalación del .crt):
 * - Canónica: Configuración → Configuración por módulo → Ventas
 * - Atajo en Módulo de Ventas: solo administrador
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/certificados-arca';

    private const MENU_NOMBRE = 'Certificados ARCA';

    private const GRUPO_URL = '#configuracion-por-modulo';

    private const MODULO_URL = '#config-modulo-ventas';

    private const VENTAS_ROOT_NOMBRE = 'Módulo de Ventas';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar certificados ARCA', 'slug' => 'listar-certificados-arca'],
        ['nombre' => 'Generar CSR certificados ARCA', 'slug' => 'generar-csr-certificados-arca'],
        ['nombre' => 'Instalar certificados ARCA', 'slug' => 'instalar-certificados-arca'],
    ];

    /** @var list<string> */
    private const ROLES_CONFIG = [
        'administrador',
        'Enc-sistemas',
        'Tecnico de Tecnología',
        'op-Gerencia de Tecnologia',
    ];

    public function up(): void
    {
        $configRootId = $this->resolverMenuConfiguracionId();
        $grupoId = (int) (DB::table('menu')->where('url', self::GRUPO_URL)->value('id') ?? 0);
        $moduloVentasId = (int) (DB::table('menu')->where('url', self::MODULO_URL)->value('id') ?? 0);
        if ($configRootId === 0 || $grupoId === 0 || $moduloVentasId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $moduloVentasId)->max('orden') ?? 0) + 1;
        $menuCanonicoId = $this->upsertMenu(
            self::MENU_URL,
            self::MENU_NOMBRE,
            $moduloVentasId,
            $orden,
            'fa-certificate'
        );

        $rolIds = $this->resolverRolIds(self::ROLES_CONFIG);
        $this->reemplazarMenuRoles($menuCanonicoId, $rolIds);
        $this->asignarRolesMenu($moduloVentasId, $rolIds);
        $this->asignarRolesMenu($grupoId, $rolIds);
        $this->asignarRolesMenu($configRootId, $rolIds);

        foreach (self::PERMISOS as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuCanonicoId);
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        $ventasRootId = $this->resolverModuloVentasId();
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($ventasRootId > 0 && $adminId > 0) {
            $atajoId = (int) (DB::table('menu')
                ->where('menu_id', $ventasRootId)
                ->where('url', self::MENU_URL)
                ->where('id', '!=', $menuCanonicoId)
                ->value('id') ?? 0);

            if ($atajoId === 0) {
                $ordenAtajo = (int) (DB::table('menu')->where('menu_id', $ventasRootId)->max('orden') ?? 0) + 1;
                $atajoId = (int) DB::table('menu')->insertGetId([
                    'menu_id' => $ventasRootId,
                    'nombre' => self::MENU_NOMBRE,
                    'url' => self::MENU_URL,
                    'orden' => $ordenAtajo,
                    'icono' => 'fa-certificate',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->reemplazarMenuRoles($atajoId, [$adminId]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuCanonicoId = (int) (DB::table('menu')
            ->where('url', self::MENU_URL)
            ->orderBy('id')
            ->value('id') ?? 0);

        $ventasRootId = $this->resolverModuloVentasId();
        if ($ventasRootId > 0) {
            $atajos = DB::table('menu')
                ->where('menu_id', $ventasRootId)
                ->where('url', self::MENU_URL)
                ->when($menuCanonicoId > 0, fn ($q) => $q->where('id', '!=', $menuCanonicoId))
                ->pluck('id');
            foreach ($atajos as $atajoId) {
                DB::table('menu_rol')->where('menu_id', $atajoId)->delete();
                DB::table('menu')->where('id', $atajoId)->delete();
            }
        }

        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        if ($menuCanonicoId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuCanonicoId)->delete();
            DB::table('menu')->where('id', $menuCanonicoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuConfiguracionId(): int
    {
        $id = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'Configuración')
            ->value('id') ?? 0);
    }

    private function resolverModuloVentasId(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', self::VENTAS_ROOT_NOMBRE)
            ->orderBy('id')
            ->value('id') ?? 0);
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
            'nombre' => $nombre,
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
    private function reemplazarMenuRoles(int $menuId, array $rolIds): void
    {
        DB::table('menu_rol')->where('menu_id', $menuId)->delete();
        $this->asignarRolesMenu($menuId, $rolIds);
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
};
