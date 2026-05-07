<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las FK ya crean índices sobre listaprecio_proveedor_id y articulo_id.
     * Este compuesto optimiza: historial y “último precio por artículo” filtrando por lista
     * (WHERE lista AND articulo ORDER BY fechavigencia / MAX(fechavigencia)).
     */
    public function up(): void
    {
        Schema::table('listaprecio_proveedor_articulo', function (Blueprint $table) {
            $table->index(['listaprecio_proveedor_id', 'articulo_id', 'fechavigencia'], 'idx_lpa_lista_art_vig');
        });
    }

    public function down(): void
    {
        Schema::table('listaprecio_proveedor_articulo', function (Blueprint $table) {
            $table->dropIndex('idx_lpa_lista_art_vig');
        });
    }
};
