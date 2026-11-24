<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: Sup-contaduria (Lana / Julieta) ve el listado IVA compras.
 * El alta original solo asignó administrador / Enc-contaduría / impuestos.
 */
return new class extends Migration
{
    private const ROL = 'Sup-contaduria';

    private const MENU_URL = 'compras/iva-compras';

    private const PERMISO_SLUG = 'listar-iva-compras';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0 && ! DB::table('menu_rol')->where('rol_id', $rolId)->where('menu_id', $menuId)->exists()) {
            DB::table('menu_rol')->insert([
                'rol_id' => $rolId,
                'menu_id' => $menuId,
            ]);
        }

        // Cadena de padres (Reportes → Módulo de Compras).
        if ($menuId > 0) {
            $actual = $menuId;
            $vistos = [];
            while ($actual > 0 && ! isset($vistos[$actual])) {
                $vistos[$actual] = true;
                $padre = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
                if ($padre <= 0) {
                    break;
                }
                if (! DB::table('menu_rol')->where('rol_id', $rolId)->where('menu_id', $padre)->exists()) {
                    DB::table('menu_rol')->insert([
                        'rol_id' => $rolId,
                        'menu_id' => $padre,
                    ]);
                }
                $actual = $padre;
            }
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0
            && ! DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists()) {
            DB::table('permiso_rol')->insert([
                'rol_id' => $rolId,
                'permiso_id' => $permisoId,
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('rol_id', $rolId)->where('menu_id', $menuId)->delete();
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')
                ->where('rol_id', $rolId)
                ->where('permiso_id', $permisoId)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
