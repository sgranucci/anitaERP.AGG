<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caja/pieza informativas en líneas de TM. Schema compartido; en AGG quedan 0.
 * El saldo de stock sigue siendo solo kilos (`cantidad`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transferencia_mercaderia_articulo')) {
            return;
        }

        if (! Schema::hasColumn('transferencia_mercaderia_articulo', 'pieza')) {
            Schema::table('transferencia_mercaderia_articulo', function (Blueprint $table) {
                $table->decimal('pieza', 20, 6)->nullable()->default(0)->after('cantidad_destino');
            });
        }
        if (! Schema::hasColumn('transferencia_mercaderia_articulo', 'caja')) {
            Schema::table('transferencia_mercaderia_articulo', function (Blueprint $table) {
                $after = Schema::hasColumn('transferencia_mercaderia_articulo', 'pieza')
                    ? 'pieza'
                    : 'cantidad_destino';
                $table->decimal('caja', 20, 6)->nullable()->default(0)->after($after);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('transferencia_mercaderia_articulo')) {
            return;
        }

        Schema::table('transferencia_mercaderia_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('transferencia_mercaderia_articulo', 'caja')) {
                $table->dropColumn('caja');
            }
        });
        Schema::table('transferencia_mercaderia_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('transferencia_mercaderia_articulo', 'pieza')) {
                $table->dropColumn('pieza');
            }
        });
    }
};
