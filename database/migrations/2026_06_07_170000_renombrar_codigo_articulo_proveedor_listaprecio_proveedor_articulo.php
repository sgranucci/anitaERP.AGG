<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('listaprecio_proveedor_articulo', 'articulo_proveedor')
            && ! Schema::hasColumn('listaprecio_proveedor_articulo', 'codigo_articulo_proveedor')) {
            Schema::table('listaprecio_proveedor_articulo', function (Blueprint $table) {
                $table->renameColumn('articulo_proveedor', 'codigo_articulo_proveedor');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('listaprecio_proveedor_articulo', 'codigo_articulo_proveedor')
            && ! Schema::hasColumn('listaprecio_proveedor_articulo', 'articulo_proveedor')) {
            Schema::table('listaprecio_proveedor_articulo', function (Blueprint $table) {
                $table->renameColumn('codigo_articulo_proveedor', 'articulo_proveedor');
            });
        }
    }
};
