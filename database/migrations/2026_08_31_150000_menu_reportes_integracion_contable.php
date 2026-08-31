<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Submenú Contable → «Reportes de integración»: atajos a listados que Contaduría
 * ya usa en otros módulos (mismas URLs / permisos; no duplica pantallas).
 *
 * - Posición financiera (Caja)
 * - Flash contable
 * - Remesas (reporte)
 * - Artículos vendidos gastronomía (Ventas)
 */
return new class extends Migration
{
    private const MENU_PADRE = 'Módulo Contable';

    private const SUBMENU_NOMBRE = 'Reportes de integración';

    private const SUBMENU_URL = '#reportes-integracion-contable';

    private const SUBMENU_ICONO = 'fa-th-list';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-contaduría',
        'Op-contaduria',
        'Sup-contaduria',
    ];

    /**
     * url => [nombre menú, icono, slug permiso listar]
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const HIJOS = [
        'caja/posicion-financiera' => [
            'Posición financiera',
            'fa-balance-scale',
            'listar-posicion-financiera',
        ],
        'contable/flash-contable' => [
            'Flash contable',
            'fa-bolt',
            'listar-flash-contable',
        ],
        'caja/remesa-reporte' => [
            'Remesas',
            'fa-file-alt',
            'listar-remesa-reporte',
        ],
        'ventas/gastronomia/articulos-vendidos' => [
            'Artículos vendidos (gastronomía)',
            'fa-cubes',
            'listar-articulos-vendidos-gastronomia',
        ],
    ];

    public function up(): void
    {
        $padreId = $this->resolverMenuPorNombre(self::MENU_PADRE);
        if ($padreId <= 0) {
            return;
        }

        $ordenSub = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $submenuId = $this->upsertMenu(
            self::SUBMENU_URL,
            self::SUBMENU_NOMBRE,
            $padreId,
            $ordenSub,
            self::SUBMENU_ICONO,
        );

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            $this->vincularCadenaMenu($submenuId, $rolId);
        }

        $orden = 1;
        foreach (self::HIJOS as $url => [$nombre, $icono, $slug]) {
            $menuId = $this->upsertMenuEnPadre($submenuId, $url, $nombre, $icono, $orden);
            $orden++;

            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            foreach ($rolIds as $rolId) {
                $this->vincularMenuRol($menuId, $rolId);
                $this->vincularPermisoRol($permisoId, $rolId);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
        $this->forgetPermisoRolCache($rolIds);
    }

    public function down(): void
    {
        $submenuId = (int) (DB::table('menu')
            ->where('url', self::SUBMENU_URL)
            ->value('id') ?? 0);
        if ($submenuId <= 0) {
            $submenuId = (int) (DB::table('menu')
                ->where('nombre', self::SUBMENU_NOMBRE)
                ->where('url', '#')
                ->value('id') ?? 0);
        }
        if ($submenuId <= 0) {
            return;
        }

        $hijoIds = DB::table('menu')->where('menu_id', $submenuId)->pluck('id');
        // Solo borrar hijos creados como atajos (misma URL bajo este padre), no los originales.
        if ($hijoIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $hijoIds)->delete();
            DB::table('menu')->whereIn('id', $hijoIds)->delete();
        }
        DB::table('menu_rol')->where('menu_id', $submenuId)->delete();
        DB::table('menu')->where('id', $submenuId)->delete();

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenuEnPadre(
        int $padreId,
        string $url,
        string $nombre,
        string $icono,
        int $orden,
    ): int {
        $existente = (int) (DB::table('menu')
            ->where('url', $url)
            ->where('menu_id', $padreId)
            ->value('id') ?? 0);
        $payload = [
            'menu_id' => $padreId,
            'nombre' => $nombre,
            'url' => $url,
            'icono' => $icono,
            'orden' => $orden,
            'updated_at' => now(),
        ];
        if ($existente > 0) {
            DB::table('menu')->where('id', $existente)->update($payload);

            return $existente;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, [
            'created_at' => now(),
        ]));
    }

    private function upsertMenu(string $url, string $nombre, int $padreId, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($id <= 0) {
            $id = (int) (DB::table('menu')
                ->where('nombre', $nombre)
                ->where('menu_id', $padreId)
                ->where('url', '#')
                ->value('id') ?? 0);
        }
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

    private function resolverMenuPorNombre(string $nombre): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', $nombre)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        // Variantes de nombre sin tilde / mayúsculas.
        foreach (['Enc-contadur%', 'Op-contadur%', 'Sup-contadur%'] as $like) {
            foreach (DB::table('rol')->where('nombre', 'like', $like)->pluck('id') as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
    }

    private function vincularCadenaMenu(int $menuId, int $rolId): void
    {
        $actual = $menuId;
        $vistos = [];
        while ($actual > 0 && ! isset($vistos[$actual])) {
            $vistos[$actual] = true;
            $this->vincularMenuRol($actual, $rolId);
            $actual = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
        }
    }

    private function vincularMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        DB::table('menu_rol')->updateOrInsert(
            ['menu_id' => $menuId, 'rol_id' => $rolId],
            []
        );
    }

    private function vincularPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        DB::table('permiso_rol')->updateOrInsert(
            ['permiso_id' => $permisoId, 'rol_id' => $rolId],
            []
        );
    }

    /** @param list<int> $rolIds */
    private function forgetPermisoRolCache(array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            try {
                cache()->tags('Permiso')->forget('Permiso.rolid.'.$rolId);
            } catch (\Throwable) {
            }
        }
    }
};
