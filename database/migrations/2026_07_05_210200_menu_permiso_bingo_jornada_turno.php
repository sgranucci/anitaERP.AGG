<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_PADRE_NOMBRE = 'Bingo';

    private const MENU_REF_ROLES_URL = 'caja/usocuentacaja';

    /** @var array<string, array{nombre:string, icono:string, permisos:list<array{nombre:string, slug:string}>}> */
    private const MENUS = [
        'caja/bingo/jornada' => [
            'nombre' => 'Jornada',
            'icono' => 'fa-calendar-check-o',
            'permisos' => [
                ['nombre' => 'Gestionar jornada bingo', 'slug' => 'gestionar-jornada-bingo'],
                ['nombre' => 'Abrir jornada bingo', 'slug' => 'abrir-jornada-bingo'],
                ['nombre' => 'Cerrar jornada bingo', 'slug' => 'cerrar-jornada-bingo'],
                ['nombre' => 'Eliminar jornada bingo', 'slug' => 'eliminar-jornada-bingo'],
            ],
        ],
        'caja/bingo/turno' => [
            'nombre' => 'Turnos',
            'icono' => 'fa-clock-o',
            'permisos' => [
                ['nombre' => 'Listar turnos bingo', 'slug' => 'listar-turno-bingo'],
                ['nombre' => 'Ingresar turnos bingo', 'slug' => 'crear-turno-bingo'],
                ['nombre' => 'Editar turnos bingo', 'slug' => 'editar-turno-bingo'],
                ['nombre' => 'Actualizar turnos bingo', 'slug' => 'actualizar-turno-bingo'],
                ['nombre' => 'Borrar turnos bingo', 'slug' => 'borrar-turno-bingo'],
            ],
        ],
        'caja/bingo/configuracion-puntoventa' => [
            'nombre' => 'Config. terminal',
            'icono' => 'fa-desktop',
            'permisos' => [
                ['nombre' => 'Listar config. terminal bingo', 'slug' => 'listar-configuracion-puntoventa-bingo'],
                ['nombre' => 'Ingresar config. terminal bingo', 'slug' => 'crear-configuracion-puntoventa-bingo'],
                ['nombre' => 'Editar config. terminal bingo', 'slug' => 'editar-configuracion-puntoventa-bingo'],
                ['nombre' => 'Actualizar config. terminal bingo', 'slug' => 'actualizar-configuracion-puntoventa-bingo'],
                ['nombre' => 'Borrar config. terminal bingo', 'slug' => 'borrar-configuracion-puntoventa-bingo'],
            ],
        ],
        'caja/bingo/habilitacion-turno' => [
            'nombre' => 'Habilitación de turno',
            'icono' => 'fa-key',
            'permisos' => [
                ['nombre' => 'Gestionar habilitación de turno bingo', 'slug' => 'gestionar-habilitacion-turno-bingo'],
                ['nombre' => 'Habilitar turno bingo', 'slug' => 'habilitar-turno-bingo'],
                ['nombre' => 'Cierre parcial turno bingo', 'slug' => 'cierre-parcial-turno-bingo'],
                ['nombre' => 'Cerrar turno operativo bingo', 'slug' => 'cerrar-turno-operativo-bingo'],
            ],
        ],
    ];

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
        $padreId = $this->resolverMenuPadreId();
        if ($padreId <= 0) {
            return;
        }

        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_REF_ROLES_URL)->value('id') ?? 0);
        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0);

        foreach (self::MENUS as $url => $meta) {
            $orden++;
            $menuId = $this->upsertMenu($url, $meta['nombre'], $padreId, $orden, $meta['icono']);
            $this->upsertPermisos($meta['permisos'], $menuId, $refMenuId, $padreId);
            $this->asignarRolesTesoreria($menuId, $padreId, $meta['permisos']);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuPadreId(): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
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
            if ($padreMenuId > 0 && ! DB::table('menu_rol')->where('menu_id', $padreMenuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreMenuId, 'rol_id' => $rolId]);
            }
        }
    }

    /**
     * @param  array<int, array{nombre:string, slug:string}>  $slugs
     */
    private function asignarRolesTesoreria(int $menuId, int $padreMenuId, array $slugs): void
    {
        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES_TESORERIA)->pluck('id')->all();

        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            foreach ([$padreMenuId, $menuId] as $mid) {
                if ($mid > 0 && ! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rid]);
                }
            }

            foreach ($slugs as $row) {
                $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
                if ($permisoId > 0) {
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
        $slugs = [];
        foreach (self::MENUS as $meta) {
            foreach ($meta['permisos'] as $p) {
                $slugs[] = $p['slug'];
            }
        }

        foreach (DB::table('permiso')->whereIn('slug', $slugs)->pluck('id') as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        foreach (array_keys(self::MENUS) as $url) {
            $menuId = DB::table('menu')->where('url', $url)->value('id');
            if ($menuId) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
