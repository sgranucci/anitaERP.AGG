<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: atajo de Archivo pagos Macro también en Cuentas a pagar
 * (mismo URL que el ítem de Módulo de Caja).
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/macro/archivo-pago';

    private const MENU_NOMBRE = 'Archivo pagos Macro';

    private const PERMISO = 'generar-archivo-pago-macro';

    private const PADRE_CXP_NOMBRE = 'Cuentas a pagar';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $padreCxp = (int) (DB::table('menu')
            ->where('nombre', self::PADRE_CXP_NOMBRE)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        if ($padreCxp <= 0) {
            return;
        }

        // Insertar después de Propuesta de pagos / Pago a proveedores.
        $ordenRef = (int) (DB::table('menu')
            ->where('menu_id', $padreCxp)
            ->whereIn('url', ['compras/propuesta-pago', 'compras/pagoproveedor'])
            ->max('orden') ?? 5);
        $orden = $ordenRef + 1;

        // Correr hacia abajo los hermanos con orden >= nuevo.
        DB::table('menu')
            ->where('menu_id', $padreCxp)
            ->where('orden', '>=', $orden)
            ->where('url', '!=', self::MENU_URL)
            ->increment('orden');

        $menuId = (int) (DB::table('menu')
            ->where('menu_id', $padreCxp)
            ->where('url', self::MENU_URL)
            ->value('id') ?? 0);

        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::MENU_NOMBRE,
                'orden' => $orden,
                'icono' => 'fa-university',
                'updated_at' => now(),
            ]);
        } else {
            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'menu_id' => $padreCxp,
                'orden' => $orden,
                'icono' => 'fa-university',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Roles: mismos que el ítem Macro existente / permiso.
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        $rolIds = [];
        if ($permisoId > 0) {
            $rolIds = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->pluck('rol_id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        }
        $rolIds = array_values(array_unique(array_merge(
            $rolIds,
            DB::table('rol')
                ->where(function ($q) {
                    $q->whereIn('nombre', ['administrador', 'Enc-pagos', 'Op-Pagos'])
                        ->orWhere('nombre', 'like', '%pagos%');
                })
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all()
        )));

        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $padreCxp)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $padreCxp,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

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
