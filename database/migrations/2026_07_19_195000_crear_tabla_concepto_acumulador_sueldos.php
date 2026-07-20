<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Override concepto <-> acumulador. Ademas del agrupamiento automatico por tipo
 * de concepto (acumulador_sueldos.tipos_incluye), permite:
 *   - incluir explicitamente un concepto en un acumulador (con signo propio),
 *   - excluir un concepto de un acumulador aunque su tipo coincida
 *     (ej. un remunerativo que NO debe formar la base de SAC).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concepto_acumulador_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('concepto_id');
            $table->unsignedBigInteger('acumulador_id');
            $table->tinyInteger('signo')->default(1);      // 1 suma, -1 resta
            $table->boolean('excluir')->default(false);    // true = quita del acumulador
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['concepto_id', 'acumulador_id'], 'conceptoacum_concepto_acum_uq');
            $table->index('acumulador_id', 'conceptoacum_acum_ix');

            $table->foreign('concepto_id')
                ->references('id')->on('concepto_sueldos')
                ->onDelete('cascade');
            $table->foreign('acumulador_id')
                ->references('id')->on('acumulador_sueldos')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concepto_acumulador_sueldos');
    }
};
