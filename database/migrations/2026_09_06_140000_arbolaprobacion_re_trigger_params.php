<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_re_trigger')) {
            return;
        }

        Schema::table('arbolaprobacion_re_trigger', function (Blueprint $table) {
            if (! Schema::hasColumn('arbolaprobacion_re_trigger', 'param_monto')) {
                $table->decimal('param_monto', 18, 4)->nullable()->after('accion_rama');
            }
            if (! Schema::hasColumn('arbolaprobacion_re_trigger', 'param_moneda_id')) {
                $table->unsignedBigInteger('param_moneda_id')->nullable()->after('param_monto');
            }
            if (! Schema::hasColumn('arbolaprobacion_re_trigger', 'param_cuentacontable_id')) {
                $table->unsignedBigInteger('param_cuentacontable_id')->nullable()->after('param_moneda_id');
            }
            if (! Schema::hasColumn('arbolaprobacion_re_trigger', 'vigencia_desde')) {
                $table->date('vigencia_desde')->nullable()->after('param_cuentacontable_id');
            }
            if (! Schema::hasColumn('arbolaprobacion_re_trigger', 'vigencia_hasta')) {
                $table->date('vigencia_hasta')->nullable()->after('vigencia_desde');
            }
            if (! Schema::hasColumn('arbolaprobacion_re_trigger', 'observacion')) {
                $table->string('observacion', 255)->nullable()->after('vigencia_hasta');
            }
        });

        try {
            Schema::table('arbolaprobacion_re_trigger', function (Blueprint $table) {
                $table->foreign('param_moneda_id', 'fk_arbol_re_trigger_moneda')
                    ->references('id')->on('moneda')->nullOnDelete();
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('arbolaprobacion_re_trigger', function (Blueprint $table) {
                $table->foreign('param_cuentacontable_id', 'fk_arbol_re_trigger_cuenta')
                    ->references('id')->on('cuentacontable')->nullOnDelete();
            });
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('arbolaprobacion_re_trigger')) {
            return;
        }

        Schema::table('arbolaprobacion_re_trigger', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_arbol_re_trigger_moneda');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropForeign('fk_arbol_re_trigger_cuenta');
            } catch (\Throwable $e) {
            }

            $cols = ['observacion', 'vigencia_hasta', 'vigencia_desde', 'param_cuentacontable_id', 'param_moneda_id', 'param_monto'];
            $drop = [];
            foreach ($cols as $col) {
                if (Schema::hasColumn('arbolaprobacion_re_trigger', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
