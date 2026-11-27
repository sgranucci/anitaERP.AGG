<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de conciliado por movimiento (como la fecha de conciliación de Anita).
 *
 * Un movimiento del ERP (lado C) o del banco (lado B) con marca está conciliado aunque no tenga
 * cruce contra el otro lado. La marca dice cuándo y de dónde viene (anita, manual, etc.).
 * Sin marca ni cruce, el movimiento está pendiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conciliacion_bancaria_marca')) {
            return;
        }

        Schema::create('conciliacion_bancaria_marca', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacaja_id');
            $table->char('lado', 1); // C = ERP, B = banco
            $table->string('hash', 100); // mismo hash que usan los cruces
            $table->date('fecha_conciliacion');
            $table->string('origen', 20)->default('manual');
            $table->string('tipo', 20)->nullable();
            $table->string('numero', 40)->nullable();
            $table->date('fecha_mov')->nullable();
            $table->decimal('importe', 18, 2)->nullable();
            $table->string('detalle', 255)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->unique(['cuentacaja_id', 'lado', 'hash'], 'cbm_unq_marca');
            $table->index(['empresa_id', 'cuentacaja_id'], 'cbm_idx_cuenta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_bancaria_marca');
    }
};
