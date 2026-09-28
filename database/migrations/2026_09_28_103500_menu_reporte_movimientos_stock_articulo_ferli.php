<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Informe de movimientos de stock por artículo (l-stkmov). Solo Ferli.
 */
return new class extends Migration
{
    private const MENU_PADRE_NOMBRE = 'Reportes Stock';

    private const MENU_NOMBRE = 'Movimientos de stock';

    private const MENU_URL = 'stock/informes-de-stock/movimientos-por-articulo';

    private const PERMISO_SLUG = 'listar-reporte-movimientos-stock-articulo';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $stockMenuId = (int) (DB::table('menu')->where('url', '#')->where('nombre', 'Módulo de Stock')->value('id') ?? 0);
        if ($stockMenuId <= 0) {
            return;
        }

        $reportesPadreId = (int) (DB::table('menu')
            ->where('menu_id', $stockMenuId)
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        if ($reportesPadreId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $reportesPadreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, self::MENU_NOMBRE, $reportesPadreId, $orden, 'fa-exchange-alt');
        $permisoId = $this->upsertPermiso('Listar reporte movimientos de stock por artículo', self::PERMISO_SLUG, $menuId);

        $rolIds = $this->rolesDelMenu(
            (int) (DB::table('menu')->where('url', 'stock/informes-de-stock/existencias-por-deposito')->value('id') ?? 0)
        );
        foreach (['administrador', 'Ventas'] as $nombreRol) {
            $rolId = (int) (DB::table('rol')->where('nombre', $nombreRol)->value('id') ?? 0);
            if ($rolId > 0) {
                $rolIds[] = $rolId;
            }
        }
        $rolIds = array_values(array_unique(array_filter($rolIds)));

        foreach ($rolIds as $rolId) {
            DB::table('menu_rol')->updateOrInsert(['menu_id' => $menuId, 'rol_id' => $rolId], []);
            DB::table('menu_rol')->updateOrInsert(['menu_id' => $reportesPadreId, 'rol_id' => $rolId], []);
            DB::table('menu_rol')->updateOrInsert(['menu_id' => $stockMenuId, 'rol_id' => $rolId], []);
            DB::table('permiso_rol')->updateOrInsert(['permiso_id' => $permisoId, 'rol_id' => $rolId], []);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

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

    /**
     * @return list<int>
     */
    private function rolesDelMenu(int $menuId): array
    {
        if ($menuId <= 0) {
            return [];
        }

        return DB::table('menu_rol')
            ->where('menu_id', $menuId)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->all();
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

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'menu_id' => $padre,
                'nombre' => $nombre,
                'orden' => $orden,
                'icono' => $icono,
                'updated_at' => now(),
            ]);

            return $id;
        }

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
};
