<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('item_estacionamiento', 'articulo_id')) {
            Schema::table('item_estacionamiento', function (Blueprint $table) {
                $table->dropForeign('fk_item_estacionamiento_articulo');
                $table->dropColumn('articulo_id');
            });
        }

        if (Schema::hasColumn('cuenta_estacionamiento_linea', 'articulo_id')) {
            Schema::table('cuenta_estacionamiento_linea', function (Blueprint $table) {
                $table->dropForeign('fk_linea_estacionamiento_art');
                $table->dropColumn('articulo_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('item_estacionamiento', function (Blueprint $table) {
            if (! Schema::hasColumn('item_estacionamiento', 'articulo_id')) {
                $table->unsignedBigInteger('articulo_id')->nullable()->after('empresa_id');
                $table->foreign('articulo_id', 'fk_item_estacionamiento_articulo')
                    ->references('id')->on('articulo')
                    ->onDelete('set null')
                    ->onUpdate('restrict');
            }
        });

        Schema::table('cuenta_estacionamiento_linea', function (Blueprint $table) {
            if (! Schema::hasColumn('cuenta_estacionamiento_linea', 'articulo_id')) {
                $table->unsignedBigInteger('articulo_id')->nullable()->after('item_estacionamiento_id');
                $table->foreign('articulo_id', 'fk_linea_estacionamiento_art')
                    ->references('id')->on('articulo')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');
            }
        });
    }
};
