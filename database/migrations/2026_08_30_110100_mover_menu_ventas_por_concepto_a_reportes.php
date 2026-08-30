<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El alta 2026_08_30_110000 resolvía el padre por iva-ventas (Módulo Ventas).
 * El reporte vive en Reportes de ventas, junto a kilos / artículos vendidos.
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/ventas-por-concepto';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $reportesId = $this->resolverMenuReportesVentasId();
        if ($menuId === 0 || $reportesId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('id', $menuId)->value('orden') ?? 0);
        if ((int) (DB::table('menu')->where('id', $menuId)->value('menu_id') ?? 0) !== $reportesId) {
            $orden = (int) (DB::table('menu')->where('menu_id', $reportesId)->max('orden') ?? 0) + 1;
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $reportesId,
            'orden' => $orden,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No vuelve al módulo: el lugar correcto es Reportes.
    }

    private function resolverMenuReportesVentasId(): int
    {
        $ventasId = (int) (DB::table('menu')
            ->whereIn('nombre', ['Módulo de Ventas', 'Módulo Ventas'])
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($ventasId > 0) {
            $reportesId = (int) (DB::table('menu')
                ->where('menu_id', $ventasId)
                ->where('nombre', 'Reportes')
                ->where('url', '#')
                ->value('id') ?? 0);
            if ($reportesId > 0) {
                return $reportesId;
            }
        }

        return (int) (DB::table('menu')->where('url', 'ventas/reppedido')->value('menu_id') ?? 0);
    }
};
