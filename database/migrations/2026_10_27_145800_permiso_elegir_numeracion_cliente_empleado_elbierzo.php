<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use App\Support\Ventas\ClienteCodigoEmpleadoBierzoSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * EL BIERZO: permiso para elegir la serie de código (cliente o empleado) en el alta.
 * Administrador lo tiene por can(). Enc-contaduría y Op-contaduria son Claudia, Luisa y Carolina.
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/cliente';

    private const PERMISO_NOMBRE = 'Elegir numeración de cliente o empleado';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-contaduría',
        'Op-contaduria',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $slug = ClienteCodigoEmpleadoBierzoSupport::PERMISO;
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $now = now();
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::PERMISO_NOMBRE,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => self::PERMISO_NOMBRE,
                'menu_id' => $menuId,
                'updated_at' => $now,
            ]);
        }

        foreach (self::ROLES as $rolNombre) {
            $rolId = (int) (DB::table('rol')->where('nombre', $rolNombre)->value('id') ?? 0);
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', ClienteCodigoEmpleadoBierzoSupport::PERMISO)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
        DB::table('permiso')->where('id', $permisoId)->delete();

        SuitecrmPermiso::flushCachePermisos();
    }
};
