<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fecha de pago de solicitudes para el perfil Op-Pagos (Rubén Barrera).
 */
return new class extends Migration
{
    private const ROL = 'Op-Pagos';

    private const PERMISO_SLUG = 'programar-fecha-pago-solicitud';

    private const MENU_URL = 'solicitudpago/fecha-pago';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($rolId <= 0 || $permisoId <= 0) {
            return;
        }

        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }

        $menuIds = DB::table('menu')->where('url', self::MENU_URL)->pluck('id');
        foreach ($menuIds as $menuId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        PermisoCacheSupport::forgetRol($rolId);
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->delete();
        }
        $menuIds = DB::table('menu')->where('url', self::MENU_URL)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->where('rol_id', $rolId)->whereIn('menu_id', $menuIds)->delete();
        }

        PermisoCacheSupport::forgetRol($rolId);
        SuitecrmPermiso::flushCachePermisos();
    }
};
