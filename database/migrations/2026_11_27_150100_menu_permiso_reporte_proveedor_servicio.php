<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permiso del reporte de proveedores con clientes de servicios cargados
 * (compras/proveedor-servicio-reporte).
 */
return new class extends Migration
{
    private const MENU = [
        'url' => 'compras/proveedor-servicio-reporte',
        'nombre' => 'Proveedores con clientes de servicios',
        'icono' => 'fa-bolt',
        'slug' => 'listar-reporte-proveedor-servicio',
        'permiso_nombre' => 'Listar proveedores con clientes de servicios',
        'orden' => 6,
    ];

    /**
     * Solo estos roles: compras + pagos + administrador. No se hereda de otros reportes
     * (el de OC asignado a 28 roles incluye sectores que no deben ver datos de proveedores).
     */
    private const ROLES_EXPLICITOS = ['Enc-compras', 'Op-Compras', 'Enc-pagos', 'Op-Pagos', 'administrador'];

    public function up(): void
    {
        $comprasMenuId = $this->resolverMenuComprasId();
        if ($comprasMenuId === 0) {
            return;
        }

        $reportesPadreId = $this->resolverMenuReportesId($comprasMenuId);
        if ($reportesPadreId === 0) {
            $reportesPadreId = $comprasMenuId;
        }

        $rolIds = $this->resolverRoles();

        $menuId = $this->upsertMenuHijo(
            $reportesPadreId,
            self::MENU['url'],
            self::MENU['nombre'],
            self::MENU['orden'],
            self::MENU['icono'],
        );
        $permisoId = $this->upsertPermiso(self::MENU['permiso_nombre'], self::MENU['slug'], $menuId);
        $this->asignarRoles($menuId, [$permisoId], $rolIds);
        $this->asignarRolesMenu($reportesPadreId, $rolIds);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::MENU['slug'])->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU['url'])->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
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

    private function resolverMenuReportesId(int $comprasMenuId): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', $comprasMenuId)
            ->where('url', '#')
            ->where('nombre', 'Reportes')
            ->value('id') ?? 0);
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
    private function resolverRoles(): array
    {
        return array_map('intval', DB::table('rol')
            ->whereIn('nombre', self::ROLES_EXPLICITOS)
            ->pluck('id')
            ->all());
    }

    /** @param  list<int>  $permisoIds  @param  list<int>  $rolIds */
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

    /** @param  list<int>  $rolIds */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        if ($rolIds === [] || $menuId <= 0) {
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
