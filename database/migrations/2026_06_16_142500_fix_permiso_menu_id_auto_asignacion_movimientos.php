<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_MOVIMIENTO_CAJA_URL = 'caja/movimientocaja';

    /** Slugs del ABM MovimientoCajaController (coincidencia errónea con Interbanking). */
    private const SLUGS_MOVIMIENTO_CAJA = [
        'crea-movimientos-caja',
        'lista-movimientos-caja',
        'edita-movimientos-caja',
        'actualiza-movimientos-caja',
        'borra-movimientos-caja',
        'supervisor-movimientos-caja',
    ];

    /** Sin menú en BD; el auto-match los asignó mal a Interbanking. */
    private const SLUGS_REVERTIR_A_NULL = [
        'crear-movimientos-de-stock',
        'listar-movimientos-de-stock',
        'editar-movimientos-de-stock',
        'actualizar-movimientos-de-stock',
        'borrar-movimientos-de-stock',
        'crear-movimientos-orden-trabajo',
        'listar-movimientos-orden-trabajo',
        'editar-movimientos-orden-trabajo',
        'actualizar-movimientos-orden-trabajo',
        'borrar-movimientos-orden-trabajo',
        'borrar-items-pedidos',
    ];

    public function up(): void
    {
        $menuMovimientoCajaId = (int) (DB::table('menu')->where('url', self::MENU_MOVIMIENTO_CAJA_URL)->value('id') ?? 0);

        if ($menuMovimientoCajaId > 0) {
            DB::table('permiso')
                ->whereIn('slug', self::SLUGS_MOVIMIENTO_CAJA)
                ->update([
                    'menu_id' => $menuMovimientoCajaId,
                    'updated_at' => now(),
                ]);
        }

        DB::table('permiso')
            ->whereIn('slug', self::SLUGS_REVERTIR_A_NULL)
            ->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $interbankingMenuId = (int) (DB::table('menu')
            ->where('url', 'caja/interbanking/movimientos-persistidos')
            ->value('id') ?? 0);

        $slugsInterbanking = array_merge(self::SLUGS_MOVIMIENTO_CAJA, self::SLUGS_REVERTIR_A_NULL);

        if ($interbankingMenuId > 0) {
            DB::table('permiso')
                ->whereIn('slug', $slugsInterbanking)
                ->update([
                    'menu_id' => $interbankingMenuId,
                    'updated_at' => now(),
                ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
