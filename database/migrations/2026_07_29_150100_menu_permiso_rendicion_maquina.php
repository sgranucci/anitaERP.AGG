<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_PADRE_NOMBRE = 'Rendición de máquinas';

    private const MENU_HIJO_URL = 'caja/rendicion-maquina';

    private const MENU_REF_ROLES_URL = 'caja/usocuentacaja';

    /** @var list<array{nombre:string, slug:string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar rendiciones de máquinas', 'slug' => 'listar-rendicion-maquina'],
        ['nombre' => 'Ingresar rendición de máquinas', 'slug' => 'crear-rendicion-maquina'],
        ['nombre' => 'Editar rendición de máquinas', 'slug' => 'editar-rendicion-maquina'],
        ['nombre' => 'Actualizar rendición de máquinas', 'slug' => 'actualizar-rendicion-maquina'],
        ['nombre' => 'Borrar rendición de máquinas', 'slug' => 'borrar-rendicion-maquina'],
        ['nombre' => 'Imprimir rendición de máquinas', 'slug' => 'imprimir-rendicion-maquina'],
    ];

    /** @var list<string> */
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
        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        if ($padreId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuHijoId = $this->upsertMenu(self::MENU_HIJO_URL, 'Rendiciones', $padreId, $orden, 'fa-clipboard-list');

        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_REF_ROLES_URL)->value('id') ?? 0);
        $this->upsertPermisos($menuHijoId, $refMenuId, $padreId);
        $this->asignarRolesTesoreria($padreId, $menuHijoId);

        SuitecrmPermiso::flushCachePermisos();
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

    private function upsertPermisos(int $menuId, int $refMenuId, int $padreMenuId): void
    {
        $rolIdsMenuRef = $refMenuId > 0
            ? DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all()
            : [];

        foreach (self::PERMISOS as $row) {
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

            foreach (self::PERMISOS as $row) {
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
        $slugs = array_column(self::PERMISOS, 'slug');

        foreach (DB::table('permiso')->whereIn('slug', $slugs)->pluck('id') as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        $menuId = DB::table('menu')->where('url', self::MENU_HIJO_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
