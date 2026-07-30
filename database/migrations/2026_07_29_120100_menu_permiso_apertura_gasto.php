<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_PADRE_URL = '#';

    private const MENU_PADRE_NOMBRE = 'Rendición de máquinas';

    private const MENU_HIJO_URL = 'caja/apertura-gasto';

    private const MENU_REF_ROLES_URL = 'caja/usocuentacaja';

    private const USO_CUENTACAJA_NOMBRE = 'Rendición de máquinas';

    /** @var list<array{nombre:string, slug:string}> */
    private const PERMISOS_APERTURA = [
        ['nombre' => 'Listar apertura de gastos', 'slug' => 'listar-apertura-gasto'],
        ['nombre' => 'Ingresar apertura de gastos', 'slug' => 'crear-apertura-gasto'],
        ['nombre' => 'Editar apertura de gastos', 'slug' => 'editar-apertura-gasto'],
        ['nombre' => 'Actualizar apertura de gastos', 'slug' => 'actualizar-apertura-gasto'],
        ['nombre' => 'Borrar apertura de gastos', 'slug' => 'borrar-apertura-gasto'],
    ];

    /** Administrador + perfiles tesorería. */
    private const ROLES_TESORERIA = [
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
    ];

    public function up(): void
    {
        $cajaId = $this->resolverMenuCajaId();
        $padreId = $this->upsertMenuPadre($cajaId);
        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuHijoId = $this->upsertMenu(self::MENU_HIJO_URL, 'Apertura de gastos', $padreId, $orden, 'fa-list');

        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_REF_ROLES_URL)->value('id') ?? 0);
        $this->upsertPermisos(self::PERMISOS_APERTURA, $menuHijoId, $refMenuId, $padreId);
        $this->asignarRolesTesoreria($padreId, $menuHijoId);
        $this->seedUsocuentacaja();

        SuitecrmPermiso::flushCachePermisos();
    }

    private function seedUsocuentacaja(): void
    {
        $existente = DB::table('usocuentacaja')
            ->where('nombre', self::USO_CUENTACAJA_NOMBRE)
            ->value('id');

        if ($existente) {
            return;
        }

        DB::table('usocuentacaja')->insert([
            'nombre' => self::USO_CUENTACAJA_NOMBRE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
                'icono' => 'fa-coins',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $cajaId,
            'nombre' => self::MENU_PADRE_NOMBRE,
            'icono' => 'fa-coins',
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

    private function asignarRolesTesoreria(int $padreMenuId, int $menuHijoId): void
    {
        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES_TESORERIA)->pluck('id')->all();

        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            foreach ([$padreMenuId, $menuHijoId] as $menuId) {
                if ($menuId > 0 && ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
                }
            }

            foreach (self::PERMISOS_APERTURA as $row) {
                $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
                if ($permisoId <= 0) {
                    continue;
                }
                $this->vincularMenuPermisoRol($menuHijoId, $permisoId, $rid);
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
        $slugs = array_column(self::PERMISOS_APERTURA, 'slug');

        foreach (DB::table('permiso')->whereIn('slug', $slugs)->pluck('id') as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        $menuId = DB::table('menu')->where('url', self::MENU_HIJO_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $padreId = DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', self::MENU_PADRE_URL)
            ->value('id');
        if ($padreId) {
            DB::table('menu_rol')->where('menu_id', $padreId)->delete();
            DB::table('menu')->where('id', $padreId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
