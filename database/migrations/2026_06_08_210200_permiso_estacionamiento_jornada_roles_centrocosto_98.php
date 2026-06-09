<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Refuerza menú y permisos de jornada estacionamiento para todos los roles del CC 98 (Tesorería).
 */
return new class extends Migration
{
    private const MENU_PADRE_URL = '#';

    private const MENU_PADRE_NOMBRE = 'Estacionamiento';

    private const MENU_URL = 'caja/estacionamiento/jornada';

    private const CENTRO_COSTO_CODIGO = 98;

    private const SLUGS = [
        'gestionar-jornada-estacionamiento',
        'abrir-jornada-estacionamiento',
        'cerrar-jornada-estacionamiento',
        'eliminar-jornada-estacionamiento',
    ];

    public function up(): void
    {
        $centrocostoId = (int) (DB::table('centrocosto')->where('codigo', self::CENTRO_COSTO_CODIGO)->value('id') ?? 0);
        if ($centrocostoId <= 0) {
            return;
        }

        $rolIds = DB::table('rol')->where('centrocosto_id', $centrocostoId)->pluck('id')->all();
        if ($rolIds === []) {
            return;
        }

        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', self::MENU_PADRE_URL)
            ->value('id') ?? 0);

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        $menuIds = array_values(array_filter([$padreId, $menuId]));
        $permisoIds = DB::table('permiso')->whereIn('slug', self::SLUGS)->pluck('id')->all();

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            foreach ($menuIds as $mid) {
                if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rolId]);
                }
            }
            foreach ($permisoIds as $pid) {
                $pid = (int) $pid;
                if (! DB::table('permiso_rol')->where('permiso_id', $pid)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $pid, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No revertir asignaciones de rol: pueden haberse usado en producción.
    }
};
