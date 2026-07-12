<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_PADRE_NOMBRE = 'Módulo Contable';

    private const SUBMENU_NOMBRE = 'Presentaciones ARCA';

    /** @var list<array{url_vieja: string, url_nueva: string, nombre: string, icono: string, orden: int}> */
    private const MENUS = [
        [
            'url_vieja' => 'contable/sicore',
            'url_nueva' => 'contable/sicore',
            'nombre' => 'SICORE',
            'icono' => 'fa-file-export',
            'orden' => 1,
        ],
        [
            'url_vieja' => 'configuracion/libro-iva-digital',
            'url_nueva' => 'contable/libro-iva-digital',
            'nombre' => 'Libro IVA Digital',
            'icono' => 'fa-file-export',
            'orden' => 2,
        ],
        [
            'url_vieja' => 'configuracion/retencion_impositiva_arca',
            'url_nueva' => 'contable/control-retencion',
            'nombre' => 'Control retención',
            'icono' => 'fa-balance-scale',
            'orden' => 3,
        ],
        [
            'url_vieja' => 'configuracion/sicore-config',
            'url_nueva' => 'contable/sicore-config',
            'nombre' => 'Configuración SICORE',
            'icono' => 'fa-cogs',
            'orden' => 4,
        ],
    ];

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría', 'Enc-impuestos'];

    public function up(): void
    {
        $padreId = $this->resolverMenuContableId();
        if ($padreId === 0) {
            return;
        }

        $submenuId = $this->upsertSubmenu($padreId);

        foreach (self::MENUS as $menu) {
            $menuId = $this->migrarMenu($menu, $submenuId);
            if ($menuId > 0) {
                $this->asignarRolesMenu($menuId);
            }
        }

        $this->asignarRolesMenu($submenuId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $padreId = $this->resolverMenuContableId();
        $configPadreId = $this->resolverMenuConfiguracionId();

        $submenuId = (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('nombre', self::SUBMENU_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        $revert = [
            ['contable/sicore', 'contable/sicore', 'SICORE (presentación ARCA)', $padreId, 99],
            ['contable/libro-iva-digital', 'configuracion/libro-iva-digital', 'Libro IVA Digital', 229, 99],
            ['contable/control-retencion', 'configuracion/retencion_impositiva_arca', 'Retención impositiva ARCA', $configPadreId > 0 ? $configPadreId : 229, 99],
            ['contable/sicore-config', 'configuracion/sicore-config', 'Configuración SICORE', $configPadreId > 0 ? $configPadreId : 33, 99],
        ];

        foreach ($revert as [$urlActual, $urlNueva, $nombre, $menuPadre, $orden]) {
            $id = (int) (DB::table('menu')->where('url', $urlActual)->value('id') ?? 0);
            if ($id > 0 && $menuPadre > 0) {
                DB::table('menu')->where('id', $id)->update([
                    'menu_id' => $menuPadre,
                    'url' => $urlNueva,
                    'nombre' => $nombre,
                    'orden' => $orden,
                    'updated_at' => now(),
                ]);
            }
        }

        if ($submenuId > 0) {
            DB::table('menu_rol')->where('menu_id', $submenuId)->delete();
            DB::table('menu')->where('id', $submenuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @param array{url_vieja: string, url_nueva: string, nombre: string, icono: string, orden: int} $menu */
    private function migrarMenu(array $menu, int $submenuId): int
    {
        $id = (int) (DB::table('menu')->where('url', $menu['url_vieja'])->value('id') ?? 0);

        if ($id === 0) {
            $id = (int) (DB::table('menu')->where('url', $menu['url_nueva'])->value('id') ?? 0);
        }

        if ($id === 0) {
            $id = (int) DB::table('menu')->insertGetId([
                'menu_id' => $submenuId,
                'nombre' => $menu['nombre'],
                'url' => $menu['url_nueva'],
                'orden' => $menu['orden'],
                'icono' => $menu['icono'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $id)->update([
                'menu_id' => $submenuId,
                'nombre' => $menu['nombre'],
                'url' => $menu['url_nueva'],
                'orden' => $menu['orden'],
                'icono' => $menu['icono'],
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('menu_id', $id)->update(['updated_at' => now()]);

        return $id;
    }

    private function upsertSubmenu(int $padreId): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('nombre', self::SUBMENU_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;

        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => self::SUBMENU_NOMBRE,
                'url' => '#',
                'orden' => $orden,
                'icono' => 'fa-university',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padreId,
            'nombre' => self::SUBMENU_NOMBRE,
            'orden' => $orden,
            'icono' => 'fa-university',
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function asignarRolesMenu(int $menuId): void
    {
        foreach ($this->resolverRolIds() as $rolId) {
            DB::table('menu_rol')->updateOrInsert(
                ['menu_id' => $menuId, 'rol_id' => $rolId],
                []
            );
        }
    }

    private function resolverMenuContableId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        return $id > 0 ? $id : 43;
    }

    private function resolverMenuConfiguracionId(): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', 'Módulo Configuración')
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

        return array_values(array_unique($ids));
    }
};
