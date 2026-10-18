<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Limpia el seed de inventario IT (PCs) que vino con la tabla bien_uso.
 * El operador autorizó borrar esos datos al armar el módulo contable estándar.
 * Sin gate de EMPRESA: aplica en AGG, Crown y demás.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bien_uso')) {
            return;
        }

        // Solo filas sin código contable Anita (seed inventario / import IT).
        DB::table('bien_uso')
            ->where(function ($q) {
                $q->whereNull('codigo_bien')->orWhere('codigo_bien', 0);
            })
            ->whereNotNull('hostname')
            ->whereIn('tipo_bien', ['P', 'I', 'M'])
            ->delete();
    }

    public function down(): void
    {
        // No se restaura el seed de inventario.
    }
};
