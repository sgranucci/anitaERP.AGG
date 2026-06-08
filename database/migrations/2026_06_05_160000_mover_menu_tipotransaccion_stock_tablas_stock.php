<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mueve "Tipos transacción stock" del Módulo de Stock a "Tablas de stock".
 */
return new class extends Migration
{
    private const MENU_URL = 'stock/tipotransaccion_stock';

    public function up(): void
    {
        $tablasStockMenuId = $this->resolverMenuTablasStockId();
        if ($tablasStockMenuId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $tablasStockMenuId)->max('orden') ?? 0) + 1;

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $tablasStockMenuId,
            'nombre' => 'Tipos transacción stock',
            'orden' => $orden > 0 ? $orden : 1,
            'icono' => 'fa-tags',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $moduloStockId = $this->resolverModuloStockId();
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId <= 0 || $moduloStockId <= 0) {
            return;
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $moduloStockId,
            'orden' => 5,
            'updated_at' => now(),
        ]);
    }

    private function resolverMenuTablasStockId(): int
    {
        $moduloStockId = $this->resolverModuloStockId();

        $id = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Tablas de stock')
                    ->orWhere('nombre', 'like', '%Tablas de stock%');
            })
            ->when($moduloStockId > 0, fn ($q) => $q->where('menu_id', $moduloStockId))
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'stock/depmae')->value('menu_id') ?? 0);
    }

    private function resolverModuloStockId(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Stock')
                    ->orWhere('nombre', 'like', '%Módulo de Stock%');
            })
            ->orderBy('id')
            ->value('id') ?? 10);
    }
};
