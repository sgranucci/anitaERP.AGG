<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'articulo_stock_id')) {
                $table->unsignedBigInteger('articulo_stock_id')->nullable()->after('articulo_id');
                $table->foreign('articulo_stock_id', 'fk_recepcion_proveedor_articulo_articulo_stock')
                    ->references('id')->on('articulo')->onDelete('restrict')->onUpdate('restrict');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('recepcion_proveedor_articulo', 'articulo_stock_id')) {
                $table->dropForeign('fk_recepcion_proveedor_articulo_articulo_stock');
                $table->dropColumn('articulo_stock_id');
            }
        });
    }
};
