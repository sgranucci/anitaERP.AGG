<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entregas semanales por línea de OC (fecha + cantidad).
 * Opcional por artículo; UI vía ORDENCOMPRA_ENTREGA_SEMANAL (El Bierzo / Surmar).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ordencompra_articulo_entrega')) {
            return;
        }

        Schema::create('ordencompra_articulo_entrega', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ordencompra_articulo_id');
            $table->date('fecha');
            $table->decimal('cantidad', 18, 4);
            $table->unsignedSmallInteger('orden')->default(1);
            $table->timestamps();

            $table->foreign('ordencompra_articulo_id', 'oc_art_entrega_linea_fk')
                ->references('id')
                ->on('ordencompra_articulo')
                ->cascadeOnDelete();
            $table->index(['ordencompra_articulo_id', 'orden'], 'oc_art_entrega_linea_orden_idx');
            $table->index(['ordencompra_articulo_id', 'fecha'], 'oc_art_entrega_linea_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordencompra_articulo_entrega');
    }
};
