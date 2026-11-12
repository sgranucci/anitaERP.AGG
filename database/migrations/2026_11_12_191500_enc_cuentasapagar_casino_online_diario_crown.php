<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: Enc-cuentasapagar (Eli / Cofre / Poblete) ya cargaba Resultado online
 * (Del Río / Betsur). La pantalla nueva Casino online diario (global + AD)
 * no incluyó ese rol en el alta.
 */
return new class extends Migration
{
    private const ROL = 'Enc-cuentasapagar';

    private const MENU_URL = 'caja/tesoreria-crown/casino-online';

    /** @var list<string> */
    private const SLUGS = [
        'listar-tesoreria-crown-casino-online',
        'crear-tesoreria-crown-casino-online',
        'editar-tesoreria-crown-casino-online',
        'actualizar-tesoreria-crown-casino-online',
        'eliminar-tesoreria-crown-casino-online',
    ];

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

        if ($menuId > 0) {
            $padreId = (int) (DB::table('menu')->where('id', $menuId)->value('menu_id') ?? 0);
            $padreIds = [];
            if ($padreId > 0) {
                $padreIds[] = $padreId;
                $abueloId = (int) (DB::table('menu')->where('id', $padreId)->value('menu_id') ?? 0);
                if ($abueloId > 0) {
                    $padreIds[] = $abueloId;
                }
            }
            foreach ($padreIds as $idPadre) {
                if (! DB::table('menu_rol')->where('rol_id', $rolId)->where('menu_id', $idPadre)->exists()) {
                    DB::table('menu_rol')->insert([
                        'rol_id' => $rolId,
                        'menu_id' => $idPadre,
                    ]);
                }
            }
        }

        foreach (self::SLUGS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            if (DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists()) {
                continue;
            }
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

        $permisoIds = DB::table('permiso')->whereIn('slug', self::SLUGS)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($permisoIds !== []) {
            DB::table('permiso_rol')
                ->where('rol_id', $rolId)
                ->whereIn('permiso_id', $permisoIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
