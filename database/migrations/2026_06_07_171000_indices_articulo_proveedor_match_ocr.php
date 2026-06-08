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
        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->index(
                ['proveedor_id', 'codigo_articulo_proveedor'],
                'idx_articulo_proveedor_prov_codigo'
            );
            $table->index(
                ['proveedor_id', 'codigobarra'],
                'idx_articulo_proveedor_prov_codigobarra'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->dropIndex('idx_articulo_proveedor_prov_codigo');
            $table->dropIndex('idx_articulo_proveedor_prov_codigobarra');
        });
    }
};
