<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha tope opcional en reemplazo de firmante: vence_el = último día inclusive;
 * el cron restaura a las 00:05 del día siguiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbol_reemplazo_firmante_log')) {
            return;
        }

        if (! Schema::hasColumn('arbol_reemplazo_firmante_log', 'vence_el')) {
            Schema::table('arbol_reemplazo_firmante_log', function (Blueprint $table) {
                $table->date('vence_el')->nullable()->after('tipos_json');
            });
        }
        if (! Schema::hasColumn('arbol_reemplazo_firmante_log', 'restaurado_at')) {
            Schema::table('arbol_reemplazo_firmante_log', function (Blueprint $table) {
                $table->timestamp('restaurado_at')->nullable()->after('vence_el');
            });
        }
        if (! Schema::hasColumn('arbol_reemplazo_firmante_log', 'restaurado_modo')) {
            Schema::table('arbol_reemplazo_firmante_log', function (Blueprint $table) {
                $table->string('restaurado_modo', 20)->nullable()->after('restaurado_at');
            });
        }
        if (! Schema::hasColumn('arbol_reemplazo_firmante_log', 'restauracion_log_id')) {
            Schema::table('arbol_reemplazo_firmante_log', function (Blueprint $table) {
                $table->unsignedBigInteger('restauracion_log_id')->nullable()->after('restaurado_modo');
            });
        }

        try {
            Schema::table('arbol_reemplazo_firmante_log', function (Blueprint $table) {
                $table->index(['operacion', 'vence_el', 'restaurado_at'], 'arbol_reemplazo_vence_idx');
            });
        } catch (\Throwable) {
            // índice ya existente
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('arbol_reemplazo_firmante_log')) {
            return;
        }

        try {
            Schema::table('arbol_reemplazo_firmante_log', function (Blueprint $table) {
                $table->dropIndex('arbol_reemplazo_vence_idx');
            });
        } catch (\Throwable) {
            //
        }

        $cols = [];
        foreach (['restauracion_log_id', 'restaurado_modo', 'restaurado_at', 'vence_el'] as $col) {
            if (Schema::hasColumn('arbol_reemplazo_firmante_log', $col)) {
                $cols[] = $col;
            }
        }
        if ($cols !== []) {
            Schema::table('arbol_reemplazo_firmante_log', function (Blueprint $table) use ($cols) {
                $table->dropColumn($cols);
            });
        }
    }
};
