<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anita imprime_etiquetas / eti_prod.fc:
 * - separa (stkumd) → «En que separa»
 * - cant_unid → «Cantidad que separa»
 * - nro_apertura → «Nro.» en etiqueta y lote/apertura
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'separa_unidadmedida_id')) {
                $table->unsignedInteger('separa_unidadmedida_id')->nullable()->after('cant_pieza');
            }
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'cant_unid_separa')) {
                $table->unsignedInteger('cant_unid_separa')->default(1)->after('separa_unidadmedida_id');
            }
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'nro_apertura')) {
                $table->unsignedInteger('nro_apertura')->default(1)->after('cant_unid_separa');
            }
        });

        Schema::table('stock_etiqueta', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_etiqueta', 'separa_unidadmedida_id')) {
                $table->unsignedInteger('separa_unidadmedida_id')->nullable()->after('unidadmedida_id');
            }
            if (! Schema::hasColumn('stock_etiqueta', 'cant_unid_separa')) {
                $table->unsignedInteger('cant_unid_separa')->default(1)->after('separa_unidadmedida_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            foreach (['nro_apertura', 'cant_unid_separa', 'separa_unidadmedida_id'] as $col) {
                if (Schema::hasColumn('recepcion_proveedor_articulo', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('stock_etiqueta', function (Blueprint $table) {
            foreach (['cant_unid_separa', 'separa_unidadmedida_id'] as $col) {
                if (Schema::hasColumn('stock_etiqueta', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
