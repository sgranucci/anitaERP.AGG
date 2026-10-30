<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El reporte se llama fórmulas, igual que el ABM.
 * El cambio de menú corre en todos los entornos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = (int) (DB::table('menu')->where('url', 'stock/reporte-formulas')->value('id') ?? 0);
        if ($id <= 0) {
            $id = (int) (DB::table('menu')->where('url', 'stock/reporte-recetas')->value('id') ?? 0);
        }
        if ($id <= 0) {
            return;
        }

        DB::table('menu')->where('id', $id)->update([
            'nombre' => 'Reporte de fórmulas',
            'url' => 'stock/reporte-formulas',
            'updated_at' => now(),
        ]);

        DB::table('permiso')->where('slug', 'listar-recetas-reporte-gastronomia')->update([
            'nombre' => 'Listar reporte de fórmulas',
            'menu_id' => $id,
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $id = (int) (DB::table('menu')->where('url', 'stock/reporte-formulas')->value('id') ?? 0);
        if ($id <= 0) {
            return;
        }

        DB::table('menu')->where('id', $id)->update([
            'nombre' => 'Reporte de recetas',
            'url' => 'stock/reporte-recetas',
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
