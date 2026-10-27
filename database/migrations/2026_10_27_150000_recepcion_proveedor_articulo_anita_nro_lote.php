<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Número interno de lote Anita (recv_nro_interno / numabm a-stock.c ref 2).
 * Se conserva en la línea para no pedir otro al reenviar la recepción.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recepcion_proveedor_articulo')) {
            return;
        }

        if (! Schema::hasColumn('recepcion_proveedor_articulo', 'anita_nro_lote')) {
            Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
                $table->unsignedBigInteger('anita_nro_lote')->nullable()->after('penvp_nro_interno');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('recepcion_proveedor_articulo')) {
            return;
        }

        if (Schema::hasColumn('recepcion_proveedor_articulo', 'anita_nro_lote')) {
            Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
                $table->dropColumn('anita_nro_lote');
            });
        }
    }
};
