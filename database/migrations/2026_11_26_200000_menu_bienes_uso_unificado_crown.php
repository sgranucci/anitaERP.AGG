<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: un único menú «Bienes de uso» bajo Módulo Contable.
 *
 * Antes: el ABM de bienes (contable/bien-uso) colgaba directo de Módulo Contable y
 * rubros / subrubros / índices / revalúo / asiento estaban en el submenú «Activo Fijo».
 * Ahora: el submenú «Activo Fijo» pasa a llamarse «Bienes de uso» y allí queda también
 * el ABM. Los permisos y las URL no cambian.
 *
 * Solo Crown. AGG, Interforming, El Bierzo, etc. mantienen el menú anterior.
 */
return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Contable';

    private const SUBMENU_ORIGEN = 'Activo Fijo';

    private const SUBMENU_NUEVO = 'Bienes de uso';

    private const URL_ABM = 'contable/bien-uso';

    private const NOMBRE_ABM_NUEVO = 'Alta y consulta de bienes';

    private const NOMBRE_ABM_ORIGEN = 'Bienes de uso';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $moduloId = $this->moduloContableId();
        if ($moduloId === 0) {
            return;
        }

        $submenuId = (int) (DB::table('menu')
            ->where('nombre', self::SUBMENU_ORIGEN)
            ->where('url', '#')
            ->where('menu_id', $moduloId)
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($submenuId === 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1;
            $submenuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $moduloId,
                'nombre' => self::SUBMENU_NUEVO,
                'url' => '#',
                'orden' => $orden,
                'icono' => 'fa-building',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $submenuId)->update([
                'nombre' => self::SUBMENU_NUEVO,
                'updated_at' => now(),
            ]);
        }

        $abmId = (int) (DB::table('menu')->where('url', self::URL_ABM)->value('id') ?? 0);
        if ($abmId > 0) {
            DB::table('menu')->where('id', $abmId)->update([
                'menu_id' => $submenuId,
                'nombre' => self::NOMBRE_ABM_NUEVO,
                'orden' => 0,
                'updated_at' => now(),
            ]);
        }

        $this->asegurarRolesDelSubmenu($moduloId, $submenuId);

        PermisoCacheSupport::flush();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $moduloId = $this->moduloContableId();
        if ($moduloId === 0) {
            return;
        }

        $submenuId = (int) (DB::table('menu')
            ->where('nombre', self::SUBMENU_NUEVO)
            ->where('url', '#')
            ->where('menu_id', $moduloId)
            ->orderBy('id')
            ->value('id') ?? 0);

        $abmId = (int) (DB::table('menu')->where('url', self::URL_ABM)->value('id') ?? 0);
        if ($abmId > 0) {
            DB::table('menu')->where('id', $abmId)->update([
                'menu_id' => $moduloId,
                'nombre' => self::NOMBRE_ABM_ORIGEN,
                'orden' => (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1,
                'updated_at' => now(),
            ]);
        }

        if ($submenuId > 0) {
            DB::table('menu')->where('id', $submenuId)->update([
                'nombre' => self::SUBMENU_ORIGEN,
                'updated_at' => now(),
            ]);
        }

        PermisoCacheSupport::flush();
        SuitecrmPermiso::flushCachePermisos();
    }

    private function moduloContableId(): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', self::MENU_MODULO)
            ->where('url', '#')
            ->where('menu_id', 0)
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    /**
     * El padre y el submenú necesitan menu_rol de los roles que ven el ABM,
     * si no el árbol no los muestra aunque tengan permiso.
     */
    private function asegurarRolesDelSubmenu(int $moduloId, int $submenuId): void
    {
        $abmId = (int) (DB::table('menu')->where('url', self::URL_ABM)->value('id') ?? 0);
        if ($abmId === 0 || $submenuId === 0) {
            return;
        }

        $rolIds = DB::table('menu_rol')->where('menu_id', $abmId)->pluck('rol_id')->map(fn ($id) => (int) $id)->all();

        foreach ([$submenuId, $moduloId] as $menuId) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
        }
    }
};
