<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/viandas/dia';

    /** @var list<string> */
    private const ROLES_LISTAR = [
        'Enc-gastronomía',
        'Sup-Gastronomia',
    ];

    /** Permiso especial de borrado: solo supervisor. @var list<string> */
    private const ROLES_BORRAR = [
        'Sup-Gastronomia',
    ];

    public function up(): void
    {
        $viandasMenuId = $this->resolverMenuViandasId();
        if ($viandasMenuId === 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $viandasMenuId,
                'nombre' => 'Viandas del día',
                'url' => self::MENU_URL,
                'orden' => 25,
                'icono' => 'fa-calendar-check',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $viandasMenuId,
                'nombre' => 'Viandas del día',
                'icono' => 'fa-calendar-check',
                'updated_at' => now(),
            ]);
        }

        $permisos = [
            [
                'nombre' => 'Listar viandas del día',
                'slug' => 'listar-viandas-dia-gastronomia',
                'roles' => self::ROLES_LISTAR,
                'menu_rol' => true,
            ],
            [
                'nombre' => 'Borrar vianda del día',
                'slug' => 'borrar-consumo-vianda-gastronomia',
                'roles' => self::ROLES_BORRAR,
                'menu_rol' => false,
            ],
        ];

        foreach ($permisos as $row) {
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

            foreach ($this->resolverRoles($row['roles']) as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
                if ($row['menu_rol'] && ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function resolverRoles(array $nombres): array
    {
        $rolIds = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $rolIds[] = $id;
            }
        }

        if ($rolIds !== []) {
            return array_values(array_unique($rolIds));
        }

        // Fallback por prefijo de nombre de rol si cambió la denominación exacta.
        $ids = [];
        foreach ($nombres as $nombre) {
            $prefijo = str_starts_with($nombre, 'Sup') ? 'Sup-Gastronom%' : 'Enc-gastronom%';
            $id = (int) (DB::table('rol')->where('nombre', 'like', $prefijo)->orderBy('id')->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
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
        foreach (['listar-viandas-dia-gastronomia', 'borrar-consumo-vianda-gastronomia'] as $slug) {
            $permisoIds = DB::table('permiso')->where('slug', $slug)->pluck('id')->all();
            foreach ($permisoIds as $pid) {
                DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
                DB::table('permiso')->where('id', $pid)->delete();
            }
        }

        $menuIds = DB::table('menu')->where('url', self::MENU_URL)->pluck('id')->all();
        foreach ($menuIds as $mid) {
            DB::table('menu_rol')->where('menu_id', $mid)->delete();
            DB::table('menu')->where('id', $mid)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
