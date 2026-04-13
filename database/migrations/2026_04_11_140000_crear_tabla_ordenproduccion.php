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
        Schema::create('ordenproduccion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->datetime('fechainicio')->nullable();
            $table->datetime('fechafinalizacion')->nullable();
            $table->unsignedBigInteger('lineallenado_id')->nullable();
            $table->foreign('lineallenado_id', 'fk_ordenproduccion_lineallenado')->references('id')->on('lineallenado')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('numeroordenproduccion');
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->foreign('articulo_id', 'fk_ordenproduccion_articulo')->references('id')->on('articulo')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('cantidad', 22, 4);
            $table->unsignedBigInteger('provienebin_id')->nullable();
            $table->foreign('provienebin_id', 'fk_ordenproduccion_provienebin')->references('id')->on('provienebin')->onDelete('restrict')->onUpdate('restrict');
            $table->string('lote', 50);
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_ordenproduccion_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordenproduccion');
    }
};
