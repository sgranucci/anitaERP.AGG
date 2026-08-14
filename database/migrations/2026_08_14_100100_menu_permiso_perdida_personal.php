<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_PADRE_URL = '#';

    private const MENU_PADRE_NOMBRE = 'Pérdidas de personal';

    /** @var list<array{url:string, nombre:string, icono:string, permisos:list<array{nombre:string, slug:string}>}> */
    private const MENUS_HIJO = [
        [
            'url' => 'caja/concepto-perdida',
            'nombre' => 'Conceptos de pérdidas',
            'icono' => 'fa-tags',
            'permisos' => [
                ['nombre' => 'Listar conceptos de pérdidas', 'slug' => 'listar-concepto-perdida'],
                ['nombre' => 'Ingresar conceptos de pérdidas', 'slug' => 'crear-concepto-perdida'],
                ['nombre' => 'Editar conceptos de pérdidas', 'slug' => 'editar-concepto-perdida'],
                ['nombre' => 'Actualizar conceptos de pérdidas', 'slug' => 'actualizar-concepto-perdida'],
                ['nombre' => 'Borrar conceptos de pérdidas', 'slug' => 'borrar-concepto-perdida'],
            ],
        ],
        [
            'url' => 'caja/imputacion-perdida',
            'nombre' => 'Imputaciones de pérdidas',
            'icono' => 'fa-list',
            'permisos' => [
                ['nombre' => 'Listar imputaciones de pérdidas', 'slug' => 'listar-imputacion-perdida'],
                ['nombre' => 'Ingresar imputaciones de pérdidas', 'slug' => 'crear-imputacion-perdida'],
                ['nombre' => 'Editar imputaciones de pérdidas', 'slug' => 'editar-imputacion-perdida'],
                ['nombre' => 'Actualizar imputaciones de pérdidas', 'slug' => 'actualizar-imputacion-perdida'],
                ['nombre' => 'Borrar imputaciones de pérdidas', 'slug' => 'borrar-imputacion-perdida'],
            ],
        ],
        [
            'url' => 'caja/perdida-personal',
            'nombre' => 'Pérdidas de personal',
            'icono' => 'fa-user-minus',
            'permisos' => [
                ['nombre' => 'Listar pérdidas de personal', 'slug' => 'listar-perdida-personal'],
                ['nombre' => 'Ingresar pérdidas de personal', 'slug' => 'crear-perdida-personal'],
                ['nombre' => 'Editar pérdidas de personal', 'slug' => 'editar-perdida-personal'],
                ['nombre' => 'Actualizar pérdidas de personal', 'slug' => 'actualizar-perdida-personal'],
                ['nombre' => 'Borrar pérdidas de personal', 'slug' => 'borrar-perdida-personal'],
            ],
        ],
    ];

    /**
     * Solo Administrador + Tesorería + Capital Humano.
     * No heredar roles de caja/usocuentacaja (trae Contaduría/Finanzas).
     *
     * @var list<string>
     */
    private const ROLES_PERMITIDOS = [
        'administrador',
        'Op-tesoreria',
        'op-Tesoreria Operativa',
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
        'Ger-Tesoreria',
        'Sup-tesoreria',
        'Sup-Tesoreria',
        'Sup-tesorería',
        'enc-Capital Humano',
        'op-Capital Humano',
        'ger-capitalhumano',
    ];

    public function up(): void
    {
        $cajaId = $this->resolverMenuCajaId();
        $padreId = $this->upsertMenuPadre($cajaId);
        $ordenBase = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0);

        $menuHijos = [];
        foreach (self::MENUS_HIJO as $idx => $menu) {
            $orden = $ordenBase + $idx + 1;
            $menuId = $this->upsertMenu($menu['url'], $menu['nombre'], $padreId, $orden, $menu['icono']);
            $this->upsertPermisos($menu['permisos'], $menuId);
            $menuHijos[] = ['id' => $menuId, 'permisos' => $menu['permisos']];
        }

        $this->asignarRolesPermitidos($padreId, $menuHijos);

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuCajaId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', 'Módulo de Caja')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'like', '%Caja%')
                    ->orWhere('nombre', 'like', '%caja%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 104);
    }

    private function upsertMenuPadre(int $cajaId): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $cajaId)
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', self::MENU_PADRE_URL)
            ->value('id') ?? 0);

        if ($id === 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $cajaId)->max('orden') ?? 0) + 1;

            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $cajaId,
                'nombre' => self::MENU_PADRE_NOMBRE,
                'url' => self::MENU_PADRE_URL,
                'orden' => $orden,
                'icono' => 'fa-user-minus',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $cajaId,
            'nombre' => self::MENU_PADRE_NOMBRE,
            'icono' => 'fa-user-minus',
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);

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

    /**
     * @param  array<int, array{nombre:string, slug:string}>  $slugs
     */
    private function upsertPermisos(array $slugs, int $menuId): void
    {
        foreach ($slugs as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);

            if ($permisoId === 0) {
                DB::table('permiso')->insert([
                    'nombre' => $row['nombre'],
                    'slug' => $row['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $row['nombre'],
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * @param  list<array{id:int, permisos:list<array{nombre:string, slug:string}>}>  $menuHijos
     */
    private function asignarRolesPermitidos(int $padreMenuId, array $menuHijos): void
    {
        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES_PERMITIDOS)->pluck('id')->all();

        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if ($padreMenuId > 0 && ! DB::table('menu_rol')->where('menu_id', $padreMenuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreMenuId, 'rol_id' => $rid]);
            }

            foreach ($menuHijos as $hijo) {
                $menuId = (int) $hijo['id'];
                if ($menuId > 0 && ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
                }

                foreach ($hijo['permisos'] as $row) {
                    $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
                    if ($permisoId <= 0) {
                        continue;
                    }
                    $this->vincularMenuPermisoRol($menuId, $permisoId, $rid);
                }
            }
        }
    }

    private function vincularMenuPermisoRol(int $menuId, int $permisoId, int $rolId): void
    {
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
        }
    }

    public function down(): void
    {
        foreach (self::MENUS_HIJO as $menu) {
            $slugs = array_column($menu['permisos'], 'slug');
            foreach (DB::table('permiso')->whereIn('slug', $slugs)->pluck('id') as $pid) {
                DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
                DB::table('permiso')->where('id', $pid)->delete();
            }

            $menuId = DB::table('menu')->where('url', $menu['url'])->value('id');
            if ($menuId) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        $padreId = DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', self::MENU_PADRE_URL)
            ->value('id');
        if ($padreId) {
            $hijos = DB::table('menu')->where('menu_id', $padreId)->count();
            if ($hijos === 0) {
                DB::table('menu_rol')->where('menu_id', $padreId)->delete();
                DB::table('menu')->where('id', $padreId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
