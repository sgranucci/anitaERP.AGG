<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permiso del informe de recepción de proveedores (l-recprov.c)
 * en Reportes de Stock.
 *
 * Roles: supervisores y encargados de gastronomía + todos los de compras y logística.
 */
return new class extends Migration
{
    private const MENU_URL = 'stock/reporte-recepcion-proveedor';

    private const PERMISO_SLUG = 'listar-reporte-recepcion-proveedor';

    private const PERMISO_NOMBRE = 'Listar reporte recepción de proveedores';

    public function up(): void
    {
        $stockMenuId = $this->resolverMenuStockId();
        if ($stockMenuId <= 0) {
            return;
        }

        $reportesPadreId = $this->resolverMenuReportesId($stockMenuId);
        if ($reportesPadreId <= 0) {
            $ordenPadre = (int) (DB::table('menu')->where('menu_id', $stockMenuId)->max('orden') ?? 0) + 1;
            $reportesPadreId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $stockMenuId,
                'nombre' => 'Reportes',
                'url' => '#',
                'orden' => $ordenPadre,
                'icono' => 'fa-print',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $reportesPadreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Recepción de proveedores', $reportesPadreId, $orden, 'fa-truck');
        $permisoId = $this->upsertPermiso(self::PERMISO_NOMBRE, self::PERMISO_SLUG, $menuId);

        $rolIds = $this->resolverRolIds();
        $this->asignarRolesMenu($stockMenuId, $rolIds);
        $this->asignarRolesMenu($reportesPadreId, $rolIds);
        $this->asignarRoles($menuId, [$permisoId], $rolIds);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuStockId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('url', '#')
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Stock')
                    ->orWhere('nombre', 'like', '%Módulo de Stock%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'stock/recepcion-proveedor')->value('menu_id') ?? 0);
    }

    private function resolverMenuReportesId(int $stockMenuId): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $stockMenuId)
            ->where('url', '#')
            ->where('nombre', 'Reportes')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        $id = (int) (DB::table('menu')
            ->where('menu_id', $stockMenuId)
            ->where('url', '#')
            ->where('nombre', 'Reportes Stock')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $stockMenuId)
            ->where('url', '#')
            ->where('nombre', 'Informes de stock')
            ->value('id') ?? 0);
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = [];

        foreach (['administrador'] as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        foreach (DB::table('rol')->where('nombre', 'like', 'Sup-Gastronom%')->pluck('id') as $id) {
            $ids[] = (int) $id;
        }
        foreach (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->pluck('id') as $id) {
            $ids[] = (int) $id;
        }

        foreach (DB::table('rol')->where('nombre', 'like', '%compras%')->pluck('id') as $id) {
            $ids[] = (int) $id;
        }
        foreach (DB::table('rol')->where('nombre', 'like', '%Compras%')->pluck('id') as $id) {
            $ids[] = (int) $id;
        }
        foreach (DB::table('rol')->where('nombre', 'like', '%logistica%')->pluck('id') as $id) {
            $ids[] = (int) $id;
        }
        foreach (DB::table('rol')->where('nombre', 'like', '%Logistica%')->pluck('id') as $id) {
            $ids[] = (int) $id;
        }
        foreach (DB::table('rol')->where('nombre', 'like', '%logística%')->pluck('id') as $id) {
            $ids[] = (int) $id;
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
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

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = ['nombre' => $nombre, 'menu_id' => $menuId, 'updated_at' => now()];

        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => $slug,
            'created_at' => now(),
        ]));
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
