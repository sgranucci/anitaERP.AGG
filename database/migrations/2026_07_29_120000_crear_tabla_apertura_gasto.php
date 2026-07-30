<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apertura_gasto', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('codigo');
            $table->string('nombre', 40);
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->unsignedBigInteger('cuentacontable_contrapartida_id')->nullable();
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestamps();

            $table->unique('codigo', 'uq_apertura_gasto_codigo');
            $table->foreign('empresa_id', 'fk_apertura_gasto_empresa')
                ->references('id')->on('empresa')
                ->restrictOnDelete();
            $table->foreign('cuentacontable_id', 'fk_apertura_gasto_cuentacontable')
                ->references('id')->on('cuentacontable')
                ->restrictOnDelete();
            $table->foreign('cuentacontable_contrapartida_id', 'fk_apertura_gasto_cuenta_contrap')
                ->references('id')->on('cuentacontable')
                ->nullOnDelete();
            $table->foreign('centrocosto_id', 'fk_apertura_gasto_centrocosto')
                ->references('id')->on('centrocosto')
                ->nullOnDelete();
            $table->index(['empresa_id', 'estado'], 'idx_apertura_gasto_empresa_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apertura_gasto');
    }
};
