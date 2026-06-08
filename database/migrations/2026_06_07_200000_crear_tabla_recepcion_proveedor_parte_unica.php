<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepcion_proveedor_parte_unica', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recepcion_proveedor_id');
            $table->unsignedBigInteger('recepcion_proveedor_articulo_id');
            $table->unsignedBigInteger('articulo_id');
            $table->unsignedInteger('secuencia_unidad')->comment('1..N dentro de la línea de recepción');
            $table->unsignedInteger('recpu_id')->unique()->comment('Número de parte única global (Anita recpunica.recpu_id)');
            $table->string('recpu_tipo', 3);
            $table->string('recpu_letra', 1);
            $table->integer('recpu_sucursal');
            $table->integer('recpu_nro');
            $table->integer('recpu_linea');
            $table->string('recpu_articulo', 13);
            $table->boolean('anita_sincronizado')->default(false);
            $table->timestamps();

            $table->unique(['recepcion_proveedor_articulo_id', 'secuencia_unidad'], 'uk_recep_parte_unica_linea_seq');
            $table->index(['recpu_tipo', 'recpu_letra', 'recpu_sucursal', 'recpu_nro', 'recpu_linea'], 'idx_recpunica_clave_recep');
            $table->foreign('recepcion_proveedor_id', 'fk_recep_parte_unica_recepcion')
                ->references('id')->on('recepcion_proveedor')->onDelete('cascade');
            $table->foreign('recepcion_proveedor_articulo_id', 'fk_recep_parte_unica_linea')
                ->references('id')->on('recepcion_proveedor_articulo')->onDelete('cascade');
            $table->foreign('articulo_id', 'fk_recep_parte_unica_articulo')
                ->references('id')->on('articulo')->onDelete('restrict');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepcion_proveedor_parte_unica');
    }
};
