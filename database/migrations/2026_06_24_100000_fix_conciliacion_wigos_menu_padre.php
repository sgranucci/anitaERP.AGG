<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrección puntual El Bierzo: la migración uif_conciliacion_wigos usó menu_id=180
 * pensando que era el padre UIF; en esta instalación 180 es Pedidos (ventas/pedido).
 *
 * No ejecutar en AGG ni otros entornos con módulo UIF: allí la migración original
 * (con padre resuelto por URL) define el menú correctamente.
 */
return new class extends Migration
{
    private const MENU_URL = 'uif/conciliacion-wigos';

    private const MENU_PEDIDO_URL = 'ventas/pedido';

    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'EL BIERZO') {
            return;
        }

        $menuWigosId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuWigosId === 0) {
            return;
        }

        $padreActualId = (int) (DB::table('menu')->where('id', $menuWigosId)->value('menu_id') ?? 0);
        $padrePedidoId = (int) (DB::table('menu')->where('url', self::MENU_PEDIDO_URL)->value('id') ?? 0);

        if ($padrePedidoId === 0 || $padreActualId !== $padrePedidoId) {
            return;
        }

        DB::table('permiso')->where('menu_id', $menuWigosId)->update([
            'menu_id' => null,
            'updated_at' => now(),
        ]);
        DB::table('menu_rol')->where('menu_id', $menuWigosId)->delete();
        DB::table('menu')->where('id', $menuWigosId)->delete();

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No se revierte: el padre fijo 180 (Pedidos) era incorrecto en El Bierzo.
    }
};
