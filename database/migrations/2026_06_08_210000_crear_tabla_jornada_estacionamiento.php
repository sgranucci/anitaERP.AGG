<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jornada_estacionamiento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->date('fecha_jornada');
            $table->string('estado', 20)->default('abierta');
            $table->unsignedBigInteger('usuario_apertura_id')->nullable();
            $table->unsignedBigInteger('usuario_cierre_id')->nullable();
            $table->timestamp('apertura_en')->nullable();
            $table->timestamp('cierre_en')->nullable();
            $table->text('observacion_apertura')->nullable();
            $table->text('observacion_cierre')->nullable();
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresa');
            $table->foreign('usuario_apertura_id')->references('id')->on('usuario');
            $table->foreign('usuario_cierre_id')->references('id')->on('usuario');
            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'fecha_jornada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jornada_estacionamiento');
    }
};
