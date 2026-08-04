<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_REPORTES = [
        'url' => '#',
        'nombre' => 'Reportes',
        'icono' => 'fa-print',
    ];

    private const MENU_OC = [
        'url' => 'compras/ordencompra-reporte',
        'nombre' => 'Informe de órdenes de compra',
        'icono' => 'fa-file-alt',
        'slug' => 'listar-reporte-ordencompra',
        'permiso_nombre' => 'Listar informe de órdenes de compra',
    ];

    private const URL_REQUISICION_REPORTE = 'compras/requisicion-reporte';

    public function up(): void
    {
        $comprasMenuId = $this->resolverMenuComprasId();
        if ($comprasMenuId === 0) {
            return;
        }

        $reportesPadreId = $this->upsertMenuReportes($comprasMenuId);

        $refMenuId = (int) (DB::table('menu')->where('url', 'compras/ordencompra')->value('id') ?? 0);
        $refPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-ordencompra')->value('id') ?? 0);
        $rolIds = $this->resolverRoles($refMenuId, $refPermisoId);
        $this->asignarRolesMenu($reportesPadreId, $rolIds);

        $reqMenuId = (int) (DB::table('menu')->where('url', self::URL_REQUISICION_REPORTE)->value('id') ?? 0);
        if ($reqMenuId > 0) {
            DB::table('menu')->where('id', $reqMenuId)->update([
                'menu_id' => $reportesPadreId,
                'orden' => 1,
                'updated_at' => now(),
            ]);
            $this->asignarRolesMenu($reqMenuId, $rolIds);
        }

        $ocOrden = 2;
        $ocMenuId = $this->upsertMenuHijo($reportesPadreId, self::MENU_OC['url'], self::MENU_OC['nombre'], $ocOrden, self::MENU_OC['icono']);
        $ocPermisoId = $this->upsertPermiso(self::MENU_OC['permiso_nombre'], self::MENU_OC['slug'], $ocMenuId);
        $this->asignarRoles($ocMenuId, [$ocPermisoId], $rolIds);

        if ($reqMenuId > 0) {
            $reqPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-reporte-requisicion-compras')->value('id') ?? 0);
            if ($reqPermisoId > 0) {
                $this->asignarRoles($reqMenuId, [$reqPermisoId], $rolIds);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::MENU_OC['slug'])->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_OC['url'])->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $comprasMenuId = $this->resolverMenuComprasId();
        $reqMenuId = (int) (DB::table('menu')->where('url', self::URL_REQUISICION_REPORTE)->value('id') ?? 0);
        if ($reqMenuId > 0 && $comprasMenuId > 0) {
            $parentReq = (int) (DB::table('menu')->where('url', 'compras/requisicion')->value('menu_id') ?? $comprasMenuId);
            DB::table('menu')->where('id', $reqMenuId)->update([
                'menu_id' => $parentReq > 0 ? $parentReq : $comprasMenuId,
                'updated_at' => now(),
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuComprasId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('url', '#')
            ->where(function ($q) {
                $q->where('nombre', 'like', '%Compras%')
                    ->orWhere('nombre', 'Módulo de Compras');
            })
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'compras/ordencompra')->value('menu_id') ?? 0);
    }

    private function upsertMenuReportes(int $comprasMenuId): int
    {
        $existente = (int) (DB::table('menu')
            ->where('menu_id', $comprasMenuId)
            ->where('url', '#')
            ->where('nombre', 'Reportes')
            ->value('id') ?? 0);

        if ($existente > 0) {
            return $existente;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $comprasMenuId)->max('orden') ?? 0) + 1;

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $comprasMenuId,
            'nombre' => self::MENU_REPORTES['nombre'],
            'url' => self::MENU_REPORTES['url'],
            'orden' => $orden,
            'icono' => self::MENU_REPORTES['icono'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertMenuHijo(int $parentId, string $url, string $nombre, int $orden, string $icono): int
    {
        $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($menuId === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentId,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $parentId,
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $menuId;
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $permisoId)->update([
            'menu_id' => $menuId,
            'nombre' => $nombre,
            'updated_at' => now(),
        ]);

        return $permisoId;
    }

    /** @return list<int> */
    private function resolverRoles(int $refMenuId, int $refPermisoId): array
    {
        $rolIds = [];
        if ($refPermisoId > 0) {
            $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
        }
        if ($refMenuId > 0) {
            $rolIdsMenu = DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all();
            $rolIds = array_values(array_unique(array_merge($rolIds, $rolIdsMenu)));
        }

        $reqPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-reporte-requisicion-compras')->value('id') ?? 0);
        if ($reqPermisoId > 0) {
            $rolReq = DB::table('permiso_rol')->where('permiso_id', $reqPermisoId)->pluck('rol_id')->unique()->all();
            $rolIds = array_values(array_unique(array_merge($rolIds, $rolReq)));
        }

        return array_map('intval', $rolIds);
    }

    /** @param list<int> $permisoIds @param list<int> $rolIds */
    private function asignarRoles(int $menuId, array $permisoIds, array $rolIds): void
    {
        $this->asignarRolesMenu($menuId, $rolIds);
        if ($rolIds === [] || $permisoIds === []) {
            return;
        }
        $rolIds = DB::table('rol')->whereIn('id', $rolIds)->pluck('id')->all();
        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
                }
            }
        }
    }

    /** @param list<int> $rolIds */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        if ($rolIds === []) {
            return;
        }
        $rolIds = DB::table('rol')->whereIn('id', $rolIds)->pluck('id')->all();
        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
            }
        }
    }
};
