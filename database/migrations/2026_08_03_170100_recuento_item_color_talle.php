<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock dimensional en líneas de recuento: color_id/talle_id (0 = sin variante).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recuento_item')) {
            return;
        }

        Schema::table('recuento_item', function (Blueprint $table) {
            if (! Schema::hasColumn('recuento_item', 'color_id')) {
                $table->unsignedBigInteger('color_id')->default(0)->after('articulo_id');
            }
            if (! Schema::hasColumn('recuento_item', 'talle_id')) {
                $table->unsignedBigInteger('talle_id')->default(0)->after('color_id');
            }
        });

        try {
            Schema::table('recuento_item', function (Blueprint $table) {
                $table->dropUnique('uq_recuentoitem_recuento_articulo');
            });
        } catch (\Throwable $e) {
            // Ya no existe.
        }

        try {
            Schema::table('recuento_item', function (Blueprint $table) {
                $table->unique(
                    ['recuento_id', 'articulo_id', 'color_id', 'talle_id'],
                    'uq_recuentoitem_recuento_art_col_tal'
                );
            });
        } catch (\Throwable $e) {
            // Ya existe.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('recuento_item')) {
            return;
        }

        try {
            Schema::table('recuento_item', function (Blueprint $table) {
                $table->dropUnique('uq_recuentoitem_recuento_art_col_tal');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('recuento_item', function (Blueprint $table) {
                $table->unique(['recuento_id', 'articulo_id'], 'uq_recuentoitem_recuento_articulo');
            });
        } catch (\Throwable $e) {
        }

        Schema::table('recuento_item', function (Blueprint $table) {
            if (Schema::hasColumn('recuento_item', 'talle_id')) {
                $table->dropColumn('talle_id');
            }
            if (Schema::hasColumn('recuento_item', 'color_id')) {
                $table->dropColumn('color_id');
            }
        });
    }
};
