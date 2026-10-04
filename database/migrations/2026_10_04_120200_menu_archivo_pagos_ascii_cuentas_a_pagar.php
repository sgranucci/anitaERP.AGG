<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Atajo de Archivo pagos (ASCII) de Interbanking también en Cuentas a pagar.
 * Misma URL que el ítem de API Interbanking.
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/interbanking/archivo-pago';

    private const MENU_NOMBRE = 'Archivo pagos (ASCII)';

    private const PERMISO = 'generar-archivo-pago-interbanking';

    private const PADRE_CXP_NOMBRE = 'Cuentas a pagar';

    public function up(): void
    {
        $padreCxp = (int) (DB::table('menu')
            ->where('nombre', self::PADRE_CXP_NOMBRE)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        if ($padreCxp <= 0) {
            return;
        }

        $ordenRef = (int) (DB::table('menu')
            ->where('menu_id', $padreCxp)
            ->whereIn('url', ['compras/propuesta-pago', 'compras/pagoproveedor', 'caja/macro/archivo-pago'])
            ->max('orden') ?? 5);
        $orden = $ordenRef + 1;

        $menuId = (int) (DB::table('menu')
            ->where('menu_id', $padreCxp)
            ->where('url', self::MENU_URL)
            ->value('id') ?? 0);

        if ($menuId <= 0) {
            DB::table('menu')
                ->where('menu_id', $padreCxp)
                ->where('orden', '>=', $orden)
                ->increment('orden');

            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'menu_id' => $padreCxp,
                'orden' => $orden,
                'icono' => 'fa-file-text-o',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::MENU_NOMBRE,
                'orden' => $orden,
                'icono' => 'fa-file-text-o',
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        $rolIds = [];
        if ($permisoId > 0) {
            $rolIds = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->pluck('rol_id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        }
        $canonicoId = (int) (DB::table('menu')
            ->where('url', self::MENU_URL)
            ->where('menu_id', '<>', $padreCxp)
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($canonicoId > 0) {
            $rolIds = array_merge(
                $rolIds,
                DB::table('menu_rol')->where('menu_id', $canonicoId)->pluck('rol_id')->map(static fn ($id) => (int) $id)->all()
            );
        }
        $rolIds = array_values(array_unique($rolIds));

        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $padreCxp)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreCxp, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $padreCxp = (int) (DB::table('menu')
            ->where('nombre', self::PADRE_CXP_NOMBRE)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        if ($padreCxp <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')
            ->where('menu_id', $padreCxp)
            ->where('url', self::MENU_URL)
            ->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
