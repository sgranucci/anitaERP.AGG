<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL_OLD = 'stock/configuracion-puntoventa-gastronomia';

    private const MENU_URL = 'ventas/configuracion-puntoventa-gastronomia';

    public function up(): void
    {
        $gastronomiaMenuId = $this->resolverMenuGastronomiaId();
        if ($gastronomiaMenuId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')
            ->whereIn('url', [self::MENU_URL_OLD, self::MENU_URL])
            ->value('id') ?? 0);

        if ($menuId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $gastronomiaMenuId)->max('orden') ?? 0) + 1;

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $gastronomiaMenuId,
            'url' => self::MENU_URL,
            'nombre' => 'Config. punto de venta',
            'orden' => $orden > 0 ? $orden : 1,
            'icono' => 'fa-desktop',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $stockMenuId = (int) (DB::table('menu')->where('url', 'stock/articulo')->value('menu_id') ?? 10);
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId <= 0) {
            return;
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $stockMenuId,
            'url' => self::MENU_URL_OLD,
            'updated_at' => now(),
        ]);
    }

    private function resolverMenuGastronomiaId(): int
    {
        $id = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')
                    ->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        $ventasId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Ventas')
                    ->orWhere('nombre', 'like', '%Módulo de Ventas%');
            })
            ->orderBy('id')
            ->value('id') ?? 51);

        return (int) (DB::table('menu')
            ->where('menu_id', $ventasId)
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')
                    ->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);
    }
};
