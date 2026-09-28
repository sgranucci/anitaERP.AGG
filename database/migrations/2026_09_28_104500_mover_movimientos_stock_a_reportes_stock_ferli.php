<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El informe de movimientos quedó bajo un submenú nuevo «Informes de stock».
 * En Ferli ya existe «Reportes Stock»: el ítem se mueve ahí, se llama
 * «Movimientos de stock» y el rol Ventas puede verlo.
 */
return new class extends Migration
{
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

        $reportesId = (int) (DB::table('menu')
            ->where('menu_id', $stockMenuId)
            ->where('nombre', 'Reportes Stock')
            ->where('url', '#')
            ->value('id') ?? 0);
        if ($reportesId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $padreAnterior = (int) (DB::table('menu')->where('id', $menuId)->value('menu_id') ?? 0);
        $orden = (int) (DB::table('menu')->where('menu_id', $reportesId)->where('id', '!=', $menuId)->max('orden') ?? 0) + 1;

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $reportesId,
            'nombre' => 'Movimientos de stock',
            'orden' => $orden,
            'updated_at' => now(),
        ]);

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        $rolVentas = (int) (DB::table('rol')->where('nombre', 'Ventas')->value('id') ?? 0);
        if ($rolVentas > 0) {
            DB::table('menu_rol')->updateOrInsert(['menu_id' => $menuId, 'rol_id' => $rolVentas], []);
            DB::table('menu_rol')->updateOrInsert(['menu_id' => $reportesId, 'rol_id' => $rolVentas], []);
            DB::table('menu_rol')->updateOrInsert(['menu_id' => $stockMenuId, 'rol_id' => $rolVentas], []);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->updateOrInsert(
                    ['permiso_id' => $permisoId, 'rol_id' => $rolVentas],
                    []
                );
            }
        }

        $this->borrarInformesVacio($padreAnterior, $reportesId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $rolVentas = (int) (DB::table('rol')->where('nombre', 'Ventas')->value('id') ?? 0);
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);

        if ($rolVentas > 0 && $menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolVentas)->delete();
        }
        if ($rolVentas > 0 && $permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolVentas)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function borrarInformesVacio(int $padreAnterior, int $reportesId): void
    {
        if ($padreAnterior <= 0 || $padreAnterior === $reportesId) {
            return;
        }

        $padre = DB::table('menu')->where('id', $padreAnterior)->first(['id', 'nombre', 'url']);
        if ($padre === null || $padre->nombre !== 'Informes de stock' || $padre->url !== '#') {
            return;
        }

        $hijos = (int) DB::table('menu')->where('menu_id', $padreAnterior)->count();
        if ($hijos > 0) {
            return;
        }

        if ((int) DB::table('permiso')->where('menu_id', $padreAnterior)->count() > 0) {
            return;
        }

        DB::table('menu_rol')->where('menu_id', $padreAnterior)->delete();
        DB::table('menu')->where('id', $padreAnterior)->delete();
    }
};
