<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CBU de pago elegido al proveedor (cuando hay más de uno en proveedor_formapago).
 * Usado por OP, Ingresos/Egresos y archivo Interbanking / Anita auxpag.axp_cbu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pagoproveedor')) {
            Schema::table('pagoproveedor', function (Blueprint $table) {
                if (! Schema::hasColumn('pagoproveedor', 'proveedor_formapago_id')) {
                    $table->unsignedBigInteger('proveedor_formapago_id')->nullable()->after('proveedor_id');
                    $table->index('proveedor_formapago_id', 'pagoproveedor_formapago_idx');
                }
                if (! Schema::hasColumn('pagoproveedor', 'cbu_pago')) {
                    $table->string('cbu_pago', 22)->nullable()->after('proveedor_formapago_id');
                }
            });
        }

        if (Schema::hasTable('caja_movimiento')) {
            Schema::table('caja_movimiento', function (Blueprint $table) {
                if (! Schema::hasColumn('caja_movimiento', 'proveedor_formapago_id')) {
                    $table->unsignedBigInteger('proveedor_formapago_id')->nullable()->after('proveedor_id');
                    $table->index('proveedor_formapago_id', 'caja_movimiento_formapago_idx');
                }
                if (! Schema::hasColumn('caja_movimiento', 'cbu_pago')) {
                    $table->string('cbu_pago', 22)->nullable()->after('proveedor_formapago_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pagoproveedor')) {
            Schema::table('pagoproveedor', function (Blueprint $table) {
                if (Schema::hasColumn('pagoproveedor', 'cbu_pago')) {
                    $table->dropColumn('cbu_pago');
                }
                if (Schema::hasColumn('pagoproveedor', 'proveedor_formapago_id')) {
                    try {
                        $table->dropIndex('pagoproveedor_formapago_idx');
                    } catch (\Throwable) {
                    }
                    $table->dropColumn('proveedor_formapago_id');
                }
            });
        }

        if (Schema::hasTable('caja_movimiento')) {
            Schema::table('caja_movimiento', function (Blueprint $table) {
                if (Schema::hasColumn('caja_movimiento', 'cbu_pago')) {
                    $table->dropColumn('cbu_pago');
                }
                if (Schema::hasColumn('caja_movimiento', 'proveedor_formapago_id')) {
                    try {
                        $table->dropIndex('caja_movimiento_formapago_idx');
                    } catch (\Throwable) {
                    }
                    $table->dropColumn('proveedor_formapago_id');
                }
            });
        }
    }
};
