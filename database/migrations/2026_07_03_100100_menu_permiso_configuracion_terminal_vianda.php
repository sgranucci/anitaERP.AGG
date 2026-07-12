<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/viandas/configuracion-terminal';

    /** @var list<string> */
    private const ROLES_OBJETIVO = [
        'Enc-gastronomía',
        'Sup-Gastronomia',
    ];

    public function up(): void
    {
        $viandasMenuId = $this->resolverMenuViandasId();
        if ($viandasMenuId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $viandasMenuId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $viandasMenuId,
                'nombre' => 'Terminales',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-desktop',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $viandasMenuId,
                'nombre' => 'Terminales',
                'icono' => 'fa-desktop',
                'updated_at' => now(),
            ]);
        }

        $slugs = [
            ['nombre' => 'Listar terminales de vianda', 'slug' => 'listar-configuracion-terminal-vianda'],
            ['nombre' => 'Ingresar terminales de vianda', 'slug' => 'crear-configuracion-terminal-vianda'],
            ['nombre' => 'Editar terminales de vianda', 'slug' => 'editar-configuracion-terminal-vianda'],
            ['nombre' => 'Actualizar terminales de vianda', 'slug' => 'actualizar-configuracion-terminal-vianda'],
            ['nombre' => 'Borrar terminales de vianda', 'slug' => 'borrar-configuracion-terminal-vianda'],
        ];

        $rolIds = $this->resolverRolesObjetivo();

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

            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolesObjetivo(): array
    {
        $rolIds = [];
        foreach (self::ROLES_OBJETIVO as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $rolIds[] = $id;
            }
        }

        if ($rolIds !== []) {
            return array_values(array_unique($rolIds));
        }

        $encId = (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->orderBy('id')->value('id') ?? 0);
        $supId = (int) (DB::table('rol')->where('nombre', 'like', 'Sup-Gastronom%')->orderBy('id')->value('id') ?? 0);

        return array_values(array_filter([$encId, $supId]));
    }

    private function resolverMenuViandasId(): int
    {
        $tiposMenuId = (int) (DB::table('menu')->where('url', 'ventas/gastronomia/viandas/tipos-menu')->value('menu_id') ?? 0);
        if ($tiposMenuId > 0) {
            return $tiposMenuId;
        }

        return (int) (DB::table('menu')
            ->where('nombre', 'Viandas')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    public function down(): void
    {
        $slugs = [
            'listar-configuracion-terminal-vianda',
            'crear-configuracion-terminal-vianda',
            'editar-configuracion-terminal-vianda',
            'actualizar-configuracion-terminal-vianda',
            'borrar-configuracion-terminal-vianda',
        ];

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        foreach ($permisoIds as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        $menuIds = DB::table('menu')->where('url', self::MENU_URL)->pluck('id')->all();
        foreach ($menuIds as $mid) {
            DB::table('menu_rol')->where('menu_id', $mid)->delete();
            DB::table('menu')->where('id', $mid)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
