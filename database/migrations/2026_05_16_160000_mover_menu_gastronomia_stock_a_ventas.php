<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const URL_MAP = [
        'stock/mesa-gastronomia' => 'ventas/mesa-gastronomia',
        'stock/ubicaciones-gastronomia' => 'ventas/ubicaciones-gastronomia',
        'stock/descuento-gastronomia' => 'ventas/descuento-gastronomia',
        'stock/mozo-gastronomia' => 'ventas/mozo-gastronomia',
        'stock/configuracion-puntoventa-gastronomia' => 'ventas/configuracion-puntoventa-gastronomia',
        'stock/gastronomia/proceso-facturacion' => 'ventas/gastronomia/proceso-facturacion',
        'stock/gastronomia/facturas-dia' => 'ventas/gastronomia/facturas-dia',
    ];

    public function up(): void
    {
        foreach (self::URL_MAP as $old => $new) {
            DB::table('menu')->where('url', $old)->update([
                'url' => $new,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::URL_MAP as $old => $new) {
            DB::table('menu')->where('url', $new)->update([
                'url' => $old,
                'updated_at' => now(),
            ]);
        }
    }
};
