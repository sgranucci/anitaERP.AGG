<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranza_descuento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cobranza_id');
            $table->unsignedBigInteger('venta_origen_id');
            $table->unsignedBigInteger('cliente_cuentacorriente_origen_id');
            $table->unsignedBigInteger('venta_nc_id')->nullable();
            $table->unsignedBigInteger('cliente_cuentacorriente_nc_id')->nullable();
            $table->string('tipo', 20);
            $table->decimal('valor', 18, 4);
            $table->decimal('importe_calculado', 18, 2);
            $table->string('leyenda', 255)->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->timestamps();

            $table->foreign('cobranza_id')->references('id')->on('cobranza')->cascadeOnDelete();
            $table->index(['cobranza_id', 'venta_origen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranza_descuento');
    }
};
