<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salida_uso_salida_impresora', function (Blueprint $table) {
            $table->unsignedBigInteger('salida_id');
            $table->unsignedBigInteger('uso_salida_impresora_id');

            $table->primary(['salida_id', 'uso_salida_impresora_id'], 'pk_salida_uso_salida_impresora');

            $table->foreign('salida_id', 'fk_salida_uso_salida_salida')
                ->references('id')->on('salida')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('uso_salida_impresora_id', 'fk_salida_uso_salida_uso')
                ->references('id')->on('uso_salida_impresora')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salida_uso_salida_impresora');
    }
};
