<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bingo_carton', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('codigo', 20);
            $table->string('nombre', 255);
            $table->decimal('precio_unitario', 15, 2)->default(0);
            $table->unsignedTinyInteger('lineas')->default(4);
            $table->boolean('es_azar')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('activo');
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_bingo_carton_empresa')
                ->references('id')->on('empresa')
                ->restrictOnDelete();
            $table->unique(['empresa_id', 'codigo'], 'uq_bingo_carton_empresa_codigo');
            $table->index(['empresa_id', 'estado'], 'idx_bingo_carton_empresa_estado');
        });

        Schema::create('bingo_concepto_rendicion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('codigo', 20)->nullable();
            $table->char('signo', 1);
            $table->string('detalle', 255);
            $table->decimal('porcentaje', 8, 4)->nullable();
            $table->string('base_calculo', 40)->default('total_cartones');
            $table->decimal('monto_fijo', 15, 2)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('activo');
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_bingo_concepto_rendicion_empresa')
                ->references('id')->on('empresa')
                ->restrictOnDelete();
            $table->index(['empresa_id', 'estado', 'orden'], 'idx_bingo_concepto_empresa_estado_orden');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bingo_concepto_rendicion');
        Schema::dropIfExists('bingo_carton');
    }
};
