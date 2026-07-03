<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_VIANDAS_URL = '#';

    private const MENU_TIPOS_URL = 'ventas/gastronomia/viandas/tipos-menu';

    /** @var list<string> */
    private const ROLES_OBJETIVO = [
        'Enc-gastronomía',
        'Sup-Gastronomia',
    ];

    public function up(): void
    {
        $parentMenuId = $this->resolverMenuGastronomiaId();
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'ventas/mesa-gastronomia')->value('menu_id') ?? 10);
        }

        $ordenViandas = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;
        $viandasMenuId = (int) (DB::table('menu')
            ->where('menu_id', $parentMenuId)
            ->where('nombre', 'Viandas')
            ->value('id') ?? 0);

        if ($viandasMenuId === 0) {
            $viandasMenuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Viandas',
                'url' => self::MENU_VIANDAS_URL,
                'orden' => $ordenViandas,
                'icono' => 'fa-utensils',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $viandasMenuId)->update([
                'menu_id' => $parentMenuId,
                'orden' => $ordenViandas,
                'icono' => 'fa-utensils',
                'updated_at' => now(),
            ]);
        }

        $ordenTipos = (int) (DB::table('menu')->where('menu_id', $viandasMenuId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_TIPOS_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $viandasMenuId,
                'nombre' => 'Tipos de menú',
                'url' => self::MENU_TIPOS_URL,
                'orden' => $ordenTipos,
                'icono' => 'fa-list',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $viandasMenuId,
                'nombre' => 'Tipos de menú',
                'orden' => $ordenTipos,
                'icono' => 'fa-list',
                'updated_at' => now(),
            ]);
        }

        $slugs = [
            ['nombre' => 'Listar tipos de menú vianda gastronomía', 'slug' => 'listar-vianda-tipo-menu-gastronomia'],
            ['nombre' => 'Ingresar tipos de menú vianda gastronomía', 'slug' => 'crear-vianda-tipo-menu-gastronomia'],
            ['nombre' => 'Editar tipos de menú vianda gastronomía', 'slug' => 'editar-vianda-tipo-menu-gastronomia'],
            ['nombre' => 'Actualizar tipos de menú vianda gastronomía', 'slug' => 'actualizar-vianda-tipo-menu-gastronomia'],
            ['nombre' => 'Borrar tipos de menú vianda gastronomía', 'slug' => 'borrar-vianda-tipo-menu-gastronomia'],
            ['nombre' => 'Sincronizar tipos de menú vianda desde Anita', 'slug' => 'sincronizar-vianda-tipo-menu-gastronomia-anita'],
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
                if (! DB::table('menu_rol')->where('menu_id', $viandasMenuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $viandasMenuId,
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

    private function resolverMenuGastronomiaId(): int
    {
        $id = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')
                    ->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        $ventasId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Ventas')
                    ->orWhere('nombre', 'like', '%Módulo de Ventas%');
            })
            ->orderBy('id')
            ->value('id') ?? 51);

        return (int) (DB::table('menu')
            ->where('menu_id', $ventasId)
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')
                    ->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    public function down(): void
    {
        $slugs = [
            'listar-vianda-tipo-menu-gastronomia',
            'crear-vianda-tipo-menu-gastronomia',
            'editar-vianda-tipo-menu-gastronomia',
            'actualizar-vianda-tipo-menu-gastronomia',
            'borrar-vianda-tipo-menu-gastronomia',
            'sincronizar-vianda-tipo-menu-gastronomia-anita',
        ];

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        foreach ($permisoIds as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        $menuIds = DB::table('menu')->whereIn('url', [self::MENU_TIPOS_URL, self::MENU_VIANDAS_URL])->pluck('id')->all();
        foreach ($menuIds as $mid) {
            DB::table('menu_rol')->where('menu_id', $mid)->delete();
            DB::table('menu')->where('id', $mid)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
