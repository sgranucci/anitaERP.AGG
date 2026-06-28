<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordencompra_articulo_precio_historia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ordencompra_id');
            $table->foreign('ordencompra_id', 'fk_oc_art_precio_hist_oc')
                ->references('id')->on('ordencompra')->onDelete('cascade');
            $table->unsignedBigInteger('ordencompra_articulo_id');
            $table->foreign('ordencompra_articulo_id', 'fk_oc_art_precio_hist_oc_art')
                ->references('id')->on('ordencompra_articulo')->onDelete('cascade');
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->foreign('articulo_id', 'fk_oc_art_precio_hist_articulo')
                ->references('id')->on('articulo')->onDelete('set null');
            $table->decimal('precio_anterior', 22, 4);
            $table->decimal('precio_nuevo', 22, 4);
            $table->unsignedBigInteger('recepcion_proveedor_id')->nullable();
            $table->foreign('recepcion_proveedor_id', 'fk_oc_art_precio_hist_recepcion')
                ->references('id')->on('recepcion_proveedor')->onDelete('set null');
            $table->unsignedBigInteger('recepcion_proveedor_articulo_id')->nullable();
            $table->foreign('recepcion_proveedor_articulo_id', 'fk_oc_art_precio_hist_rec_art')
                ->references('id')->on('recepcion_proveedor_articulo')->onDelete('set null');
            $table->string('origen', 40);
            $table->string('comentario', 500)->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_oc_art_precio_hist_usuario')
                ->references('id')->on('usuario')->onDelete('cascade');
            $table->dateTime('fecha');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['ordencompra_id', 'fecha'], 'idx_oc_art_precio_hist_oc_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordencompra_articulo_precio_historia');
    }
};
