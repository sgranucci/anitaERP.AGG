<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos fiscales especiales del padrón proveedor (CUIT constancia + CM05 anual).
 * Separado de proveedor_archivo (Anita) para no mezclar con el sync proarch.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proveedor_documento_fiscal')) {
            return;
        }

        Schema::create('proveedor_documento_fiscal', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id')
                ->references('id')
                ->on('proveedor')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            // CUIT | CM05
            $table->string('tipo', 16);
            $table->string('nombrearchivo', 255);
            $table->date('fecha_vencimiento')->nullable();
            $table->unsignedSmallInteger('anio_ejercicio')->nullable();
            // ABM | PORTAL
            $table->string('origen', 16)->default('ABM');
            $table->unsignedBigInteger('presento_usuario_id')->nullable();
            $table->foreign('presento_usuario_id')
                ->references('id')
                ->on('usuario')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();
            $table->index(['proveedor_id', 'tipo']);
            $table->index(['proveedor_id', 'fecha_vencimiento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_documento_fiscal');
    }
};
