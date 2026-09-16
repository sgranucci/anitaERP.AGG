<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: menú + permiso exportación pagos Banco Macro (diskette).
 * Roles de pagos (Enc-pagos / Op-Pagos) + administrador.
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/macro/archivo-pago';

    private const MENU_NOMBRE = 'Archivo pagos Macro';

    private const PERMISO = 'generar-archivo-pago-macro';

    private const PERMISO_NOMBRE = 'Generar archivo pagos Banco Macro';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-pagos',
        'Op-Pagos',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $moduloCajaId = (int) (DB::table('menu')
            ->where('nombre', 'Módulo de Caja')
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        if ($moduloCajaId <= 0) {
            $moduloCajaId = 104;
        }

        // Bajo Módulo de Caja (no dentro de API Interbanking: Macro es canal propio).
        $padreId = $moduloCajaId;

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::MENU_NOMBRE,
                'menu_id' => $padreId,
                'icono' => 'fa-university',
                'updated_at' => now(),
            ]);
        } else {
            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'menu_id' => $padreId,
                'orden' => $orden,
                'icono' => 'fa-university',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => self::PERMISO_NOMBRE,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        } else {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::PERMISO_NOMBRE,
                'slug' => self::PERMISO,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        // Ampliar con roles de Archivo IB + cualquier rol "pagos"
        $ibPermisoId = (int) (DB::table('permiso')
            ->where('slug', 'generar-archivo-pago-interbanking')
            ->value('id') ?? 0);
        if ($ibPermisoId > 0) {
            $rolIds = array_merge(
                $rolIds,
                DB::table('permiso_rol')->where('permiso_id', $ibPermisoId)->pluck('rol_id')->map(static fn ($id) => (int) $id)->all()
            );
        }
        $extra = DB::table('rol')
            ->where('nombre', 'like', '%pagos%')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
        $rolIds = array_values(array_unique(array_merge($rolIds, $extra)));

        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $padreId,
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

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
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
};
