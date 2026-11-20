<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: Mayor plano por cuenta para Op-Pagos (Rubén Barrera).
 * Si el rol no tiene Contable, también asigna Reportes Contables y Módulo Contable.
 */
return new class extends Migration
{
    private const ROL = 'Op-Pagos';

    private const MENU_URL = 'contable/mayor-plano-cuenta';

    private const PERMISO_SLUG = 'listar-mayor-plano-cuenta';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);

        if ($permisoId > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }

        if ($menuId > 0) {
            $this->asegurarMenuRol($menuId, $rolId);
            $this->propagarMenuPadres($menuId, $rolId);
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
        if ($rolId <= 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->delete();
        }

        // No quitamos Reportes Contables / Módulo Contable: pueden usarse por otros ítems.

        PermisoCacheSupport::forgetRol($rolId);
        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function propagarMenuPadres(int $menuId, int $rolId): void
    {
        $actual = $menuId;
        $guard = 0;
        while ($actual > 0 && $guard < 8) {
            $padre = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
            if ($padre <= 0) {
                break;
            }
            $this->asegurarMenuRol($padre, $rolId);
            $actual = $padre;
            $guard++;
        }
    }
};
