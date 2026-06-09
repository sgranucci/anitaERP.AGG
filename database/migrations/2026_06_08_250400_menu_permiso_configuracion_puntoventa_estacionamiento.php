<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_PADRE_URL = '#';

    private const MENU_PADRE_NOMBRE = 'Estacionamiento';

    private const MENU_URL = 'caja/estacionamiento/configuracion-puntoventa';

    private const MENU_REF_ROLES_URL = 'caja/usocuentacaja';

    private const ROL_ADMINISTRADOR = 'administrador';

    /** Perfiles tesorería (centro de costo 98). */
    private const ROLES_TESORERIA = [
        'Op-tesoreria',
        'op-Tesoreria Operativa',
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
        'Ger-Tesoreria',
        'Sup-tesoreria',
        'Sup-Tesoreria',
        'Sup-tesorería',
    ];

    public function up(): void
    {
        $cajaId = $this->resolverMenuCajaId();
        $padreId = $this->upsertMenuPadre($cajaId);
        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_REF_ROLES_URL)->value('id') ?? 0);

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Config. punto de venta', $padreId, $orden, 'fa-desktop');

        $slugs = [
            ['nombre' => 'Listar config. PV estacionamiento', 'slug' => 'listar-configuracion-puntoventa-estacionamiento'],
            ['nombre' => 'Ingresar config. PV estacionamiento', 'slug' => 'crear-configuracion-puntoventa-estacionamiento'],
            ['nombre' => 'Editar config. PV estacionamiento', 'slug' => 'editar-configuracion-puntoventa-estacionamiento'],
            ['nombre' => 'Actualizar config. PV estacionamiento', 'slug' => 'actualizar-configuracion-puntoventa-estacionamiento'],
            ['nombre' => 'Borrar config. PV estacionamiento', 'slug' => 'borrar-configuracion-puntoventa-estacionamiento'],
        ];

        $this->upsertPermisos($slugs, $menuId, $refMenuId, $padreId);
        $this->asignarRolAdministrador($menuId, $padreId, $slugs);
        $this->asignarRolesTesoreria($menuId, $padreId, $slugs);

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
                'icono' => 'fa-car',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $cajaId,
            'nombre' => self::MENU_PADRE_NOMBRE,
            'icono' => 'fa-car',
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
    private function upsertPermisos(array $slugs, int $menuId, int $refMenuId, int $padreMenuId): void
    {
        $rolIdsMenuRef = $refMenuId > 0
            ? DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all()
            : [];

        foreach ($slugs as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);

            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
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

            foreach ($rolIdsMenuRef as $rolId) {
                $this->vincularMenuPermisoRol($menuId, $permisoId, (int) $rolId);
            }
        }

        foreach ($rolIdsMenuRef as $rolId) {
            $rid = (int) $rolId;
            if ($padreMenuId > 0 && ! DB::table('menu_rol')->where('menu_id', $padreMenuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreMenuId, 'rol_id' => $rid]);
            }
        }
    }

    /**
     * @param  array<int, array{nombre:string, slug:string}>  $slugs
     */
    private function asignarRolAdministrador(int $menuId, int $padreMenuId, array $slugs): void
    {
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL_ADMINISTRADOR)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        if ($padreMenuId > 0) {
            if (! DB::table('menu_rol')->where('menu_id', $padreMenuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreMenuId, 'rol_id' => $rolId]);
            }
        }

        foreach ($slugs as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                $this->vincularMenuPermisoRol($menuId, $permisoId, $rolId);
            }
        }
    }

    /**
     * @param  array<int, array{nombre:string, slug:string}>  $slugs
     */
    private function asignarRolesTesoreria(int $menuId, int $padreMenuId, array $slugs): void
    {
        $rolIds = $this->resolverRolIdsTesoreria();

        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if ($padreMenuId > 0) {
                if (! DB::table('menu_rol')->where('menu_id', $padreMenuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $padreMenuId, 'rol_id' => $rid]);
                }
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
            }

            foreach ($slugs as $row) {
                $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
                if ($permisoId > 0) {
                    $this->vincularMenuPermisoRol($menuId, $permisoId, $rid);
                }
            }
        }
    }

    /**
     * @return list<int>
     */
    private function resolverRolIdsTesoreria(): array
    {
        $ids = DB::table('rol')->whereIn('nombre', self::ROLES_TESORERIA)->pluck('id')->all();

        $supId = (int) (DB::table('rol')->where('nombre', 'like', 'Sup-tesorer%')->orderBy('id')->value('id') ?? 0);
        if ($supId > 0) {
            $ids[] = $supId;
        }

        return array_values(array_unique(array_map('intval', $ids)));
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
        $slugs = [
            'listar-configuracion-puntoventa-estacionamiento',
            'crear-configuracion-puntoventa-estacionamiento',
            'editar-configuracion-puntoventa-estacionamiento',
            'actualizar-configuracion-puntoventa-estacionamiento',
            'borrar-configuracion-puntoventa-estacionamiento',
        ];

        foreach (DB::table('permiso')->whereIn('slug', $slugs)->pluck('id') as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
