<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: Archivo pagos Francés bajo Cuentas a pagar → Transferencias,
 * con los mismos roles que Solicitudes de retiro y Archivo pagos (ASCII).
 * Saca Archivo pagos Macro del menú de este servidor (Caja y Cuentas a pagar).
 */
return new class extends Migration
{
    private const URL_FRANCES = 'caja/frances/archivo-pago';

    private const URL_MACRO = 'caja/macro/archivo-pago';

    private const SUBMENU_URL = '#transferencias-cap';

    private const PERMISO = 'generar-archivo-pago-frances';

    /** @var list<string> */
    private const HERMANOS = [
        'compras/solicitud-retiro',
        'caja/interbanking/archivo-pago',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $submenuId = (int) (DB::table('menu')->where('url', self::SUBMENU_URL)->value('id') ?? 0);
        if ($submenuId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::URL_FRANCES)->value('id') ?? 0);
        if ($menuId <= 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => 'Archivo pagos Francés',
                'url' => self::URL_FRANCES,
                'menu_id' => $submenuId,
                'orden' => $orden,
                'icono' => 'fa-university',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => 'Archivo pagos Francés',
                'menu_id' => $submenuId,
                'icono' => 'fa-university',
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId <= 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Generar archivo de pagos Banco Francés',
                'slug' => self::PERMISO,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => 'Generar archivo de pagos Banco Francés',
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        $hermanoIds = DB::table('menu')
            ->whereIn('url', self::HERMANOS)
            ->where('menu_id', $submenuId)
            ->pluck('id');
        $rolIds = DB::table('menu_rol')->whereIn('menu_id', $hermanoIds)->pluck('rol_id')->unique();
        $capId = (int) (DB::table('menu')->where('id', $submenuId)->value('menu_id') ?? 0);
        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if ($rid <= 0) {
                continue;
            }
            foreach (array_filter([$menuId, $submenuId, $capId]) as $padre) {
                if (! DB::table('menu_rol')->where('menu_id', $padre)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $padre, 'rol_id' => $rid]);
                }
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
            }
            PermisoCacheSupport::forgetRol($rid);
        }

        $this->sacarMacroDelMenu();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::URL_FRANCES)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $cajaId = $this->restaurarMacro(104, 'Módulo de Caja', 20, [
            'administrador', 'Enc-cuentasapagar', 'Enc-finanzas', 'Enc-impuestos',
            'Op-contaduria', 'Op-cuentasapagar', 'Op-Finanzas', 'Sup-contaduria',
        ]);
        if ($cajaId > 0) {
            DB::table('permiso')
                ->where('slug', 'generar-archivo-pago-macro')
                ->whereNull('menu_id')
                ->update(['menu_id' => $cajaId, 'updated_at' => now()]);
        }
        $this->restaurarMacro(496, 'Cuentas a pagar', 9, [
            'administrador', 'Enc-contaduría', 'Enc-cuentasapagar', 'Enc-finanzas', 'Enc-impuestos',
            'Op-contaduria', 'Op-cuentasapagar', 'Op-Finanzas', 'Sup-contaduria',
        ]);
        SuitecrmPermiso::flushCachePermisos();
    }

    private function sacarMacroDelMenu(): void
    {
        $ids = DB::table('menu')->where('url', self::URL_MACRO)->pluck('id');
        foreach ($ids as $id) {
            $id = (int) $id;
            DB::table('permiso')->where('menu_id', $id)->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);
            DB::table('menu_rol')->where('menu_id', $id)->delete();
            DB::table('menu')->where('id', $id)->delete();
        }
    }

    /**
     * @param  list<string>  $roles
     */
    private function restaurarMacro(int $padreIdConocido, string $padreNombre, int $orden, array $roles): int
    {
        $padreId = (int) (DB::table('menu')->where('id', $padreIdConocido)->value('id') ?? 0);
        if ($padreId <= 0) {
            $padreId = (int) (DB::table('menu')
                ->where('menu_id', 0)
                ->where('nombre', $padreNombre)
                ->orderBy('id')
                ->value('id') ?? 0);
        }
        if ($padreId <= 0) {
            return 0;
        }
        $existente = (int) (DB::table('menu')->where('url', self::URL_MACRO)->where('menu_id', $padreId)->value('id') ?? 0);
        if ($existente > 0) {
            return $existente;
        }

        $menuId = (int) DB::table('menu')->insertGetId([
            'nombre' => 'Archivo pagos Macro',
            'url' => self::URL_MACRO,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => 'fa-university',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rolIds = DB::table('rol')->whereIn('nombre', $roles)->pluck('id');
        foreach ($rolIds as $rolId) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => (int) $rolId]);
        }

        return $menuId;
    }
};
