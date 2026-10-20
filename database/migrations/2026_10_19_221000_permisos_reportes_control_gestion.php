<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asigna a Control de Gestión los reportes nuevos:
 * - Control de inventario de items (stock)
 * - Cruce visitas por OC (seguridad)
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ROLES = [
        'Enc-Control de Gestión',
        'Op-Control de Gestión',
    ];

    /** @var list<array{url: string, slug: string}> */
    private const REPORTES = [
        [
            'url' => 'stock/informes-de-stock/control-inventario-items',
            'slug' => 'listar-reporte-control-inventario-items',
        ],
        [
            'url' => 'seguridad/reporte-visitas-oc-cruce',
            'slug' => 'listar-reporte-visitas-oc-cruce',
        ],
    ];

    public function up(): void
    {
        $rolIds = $this->resolverRoles();
        if ($rolIds === []) {
            return;
        }

        foreach (self::REPORTES as $reporte) {
            $menuId = (int) (DB::table('menu')->where('url', $reporte['url'])->value('id') ?? 0);
            $permisoId = (int) (DB::table('permiso')->where('slug', $reporte['slug'])->value('id') ?? 0);
            if ($menuId <= 0 || $permisoId <= 0) {
                continue;
            }

            foreach ($rolIds as $rolId) {
                DB::table('menu_rol')->updateOrInsert(['menu_id' => $menuId, 'rol_id' => $rolId], []);
                DB::table('permiso_rol')->updateOrInsert(['permiso_id' => $permisoId, 'rol_id' => $rolId], []);
                $this->asegurarAncestrosMenu($menuId, $rolId);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $rolIds = $this->resolverRoles();
        if ($rolIds === []) {
            return;
        }

        foreach (self::REPORTES as $reporte) {
            $menuId = (int) (DB::table('menu')->where('url', $reporte['url'])->value('id') ?? 0);
            $permisoId = (int) (DB::table('permiso')->where('slug', $reporte['slug'])->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->whereIn('rol_id', $rolIds)->delete();
            }
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->whereIn('rol_id', $rolIds)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRoles(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id <= 0) {
                $like = str_starts_with($nombre, 'Enc-')
                    ? 'Enc-Control de Gesti%'
                    : 'Op-Control de Gesti%';
                $id = (int) (DB::table('rol')->where('nombre', 'like', $like)->orderBy('id')->value('id') ?? 0);
            }
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        foreach (DB::table('rol')->where('nombre', 'like', '%Control de Gesti%')->pluck('id') as $cgId) {
            $ids[] = (int) $cgId;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function asegurarAncestrosMenu(int $menuId, int $rolId): void
    {
        $actual = $menuId;
        for ($i = 0; $i < 8; $i++) {
            $padreId = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
            if ($padreId <= 0) {
                break;
            }
            DB::table('menu_rol')->updateOrInsert(['menu_id' => $padreId, 'rol_id' => $rolId], []);
            $actual = $padreId;
        }
    }
};
