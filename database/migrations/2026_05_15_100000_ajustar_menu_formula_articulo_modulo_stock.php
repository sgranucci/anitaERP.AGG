<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asegura que "Fórmulas de artículos" cuelgue del Módulo de Stock y que todos los permisos
 * del CRUD queden asociados a esa opción de menú, con visibilidad en menú para los mismos
 * roles que ven "Artículos" (stock/articulo).
 */
return new class extends Migration
{
    private const MENU_URL = 'stock/formula-articulo';

    private const SLUGS = [
        'listar-formula-articulo',
        'crear-formula-articulo',
        'editar-formula-articulo',
        'actualizar-formula-articulo',
        'borrar-formula-articulo',
    ];

    public function up(): void
    {
        $moduloStockId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Stock')
                    ->orWhere('nombre', 'like', '%Módulo de Stock%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($moduloStockId === 0) {
            $moduloStockId = 10;
        }

        $menuFormulaId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuFormulaId === 0) {
            $menuFormulaId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $moduloStockId,
                'nombre' => 'Fórmulas de artículos',
                'url' => self::MENU_URL,
                'orden' => 5,
                'icono' => 'fa-flask',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuFormulaId)->update([
                'menu_id' => $moduloStockId,
                'nombre' => 'Fórmulas de artículos',
                'icono' => 'fa-flask',
                'updated_at' => now(),
            ]);
        }

        foreach (self::SLUGS as $slug) {
            DB::table('permiso')->where('slug', $slug)->update([
                'menu_id' => $menuFormulaId,
                'updated_at' => now(),
            ]);
        }

        $menuArticulosId = (int) (DB::table('menu')->where('url', 'stock/articulo')->value('id') ?? 0);
        $rolIds = [];
        if ($menuArticulosId > 0) {
            $rolIds = DB::table('menu_rol')->where('menu_id', $menuArticulosId)->pluck('rol_id')->unique()->all();
        }
        if ($rolIds === []) {
            $refPermisoArticulos = (int) (DB::table('permiso')->where('slug', 'listar-articulos')->value('id') ?? 0);
            if ($refPermisoArticulos > 0) {
                $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoArticulos)->pluck('rol_id')->unique()->all();
            }
        }

        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if ($rid <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuFormulaId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuFormulaId,
                    'rol_id' => $rid,
                ]);
            }
        }
    }

    public function down(): void
    {
        // No revertir posición de menú para no romper instalaciones ya usadas.
    }
};
