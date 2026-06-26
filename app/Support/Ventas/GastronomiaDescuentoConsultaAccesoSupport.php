<?php

namespace App\Support\Ventas;

use Illuminate\Support\Facades\DB;

/**
 * Quién puede usar la consulta modal / leer descuento gastronomía (POS, reportes, etc.).
 */
final class GastronomiaDescuentoConsultaAccesoSupport
{
    private const MENU_REPORTE_DESCUENTOS = 'ventas/gastronomia/descuento-reporte';

    public static function assert(): void
    {
        if (session()->get('rol_nombre') === 'administrador') {
            return;
        }

        if (can('usar-proceso-facturacion-gastronomia', false)) {
            return;
        }

        if (can('listar-descuento-gastronomia', false)) {
            return;
        }

        if (self::rolTieneMenuReporteDescuentos()) {
            return;
        }

        can('usar-proceso-facturacion-gastronomia');
    }

    public static function rolTieneMenuReporteDescuentos(): bool
    {
        $rolId = (int) session()->get('rol_id');
        if ($rolId <= 0) {
            return false;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_REPORTE_DESCUENTOS)->value('id') ?? 0);

        return $menuId > 0
            && DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists();
    }
}
