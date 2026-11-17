<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: Ventas → Transferencias → Facturas PXSOL (API).
 */
return new class extends Migration
{
    private const PADRE = 'Transferencias';

    private const URL = 'ventas/transferencia-pxsol-api';

    private const PERMISO = 'transferir-facturas-pxsol-api-crown';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-contaduría',
        'Sup-contaduria',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ventasId = $this->idModuloVentas();
        if ($ventasId <= 0) {
            return;
        }

        $padreId = (int) (DB::table('menu')
            ->where('menu_id', $ventasId)
            ->where('nombre', self::PADRE)
            ->where('url', '#')
            ->value('id') ?? 0);
        if ($padreId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            $refOrden = (int) (DB::table('menu')->where('url', 'ventas/transferencia-pxsol')->value('orden') ?? 0);
            $orden = $refOrden > 0 ? $refOrden + 1 : ((int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1);
            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => 'Facturas PXSOL (API)',
                'url' => self::URL,
                'menu_id' => $padreId,
                'orden' => $orden,
                'icono' => 'fa-cloud-download',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => 'Facturas PXSOL (API)',
                'menu_id' => $padreId,
                'icono' => 'fa-cloud-download',
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId <= 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Transferir facturas de PXSOL por API',
                'slug' => self::PERMISO,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => 'Transferir facturas de PXSOL por API',
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id');
        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
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
            PermisoCacheSupport::forgetRol($rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function idModuloVentas(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('url', '#')
            ->whereIn('nombre', ['Módulo de Ventas', 'Módulo Ventas'])
            ->orderBy('id')
            ->value('id') ?? 0);
    }
};
