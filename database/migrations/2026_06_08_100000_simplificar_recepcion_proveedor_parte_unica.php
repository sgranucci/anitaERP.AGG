<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('recepcion_proveedor_parte_unica');

        Schema::create('recepcion_proveedor_parte_unica', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recepcion_proveedor_id');
            $table->unsignedBigInteger('recepcion_proveedor_articulo_id');
            $table->unsignedInteger('numeroparte')->unique()
                ->comment('Número secuencial global de parte única (Anita recpunica.recpu_id)');
            $table->timestamps();

            $table->index('recepcion_proveedor_id', 'idx_recep_parte_unica_recepcion');
            $table->index('recepcion_proveedor_articulo_id', 'idx_recep_parte_unica_linea');
            $table->foreign('recepcion_proveedor_id', 'fk_recep_parte_unica_recepcion')
                ->references('id')->on('recepcion_proveedor')->onDelete('cascade');
            $table->foreign('recepcion_proveedor_articulo_id', 'fk_recep_parte_unica_linea')
                ->references('id')->on('recepcion_proveedor_articulo')->onDelete('cascade');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepcion_proveedor_parte_unica');
    }
};
