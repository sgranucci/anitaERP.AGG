<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turno_estacionamiento', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_turno_estacionamiento_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->string('nombre', 255);
            $table->string('codigo', 50)->nullable();
            $table->time('hora_desde')->nullable();
            $table->time('hora_hasta')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['nombre', 'empresa_id'], 'uk_turno_estacionamiento_nombre_empresa');
            $table->unique(['codigo', 'empresa_id'], 'uk_turno_estacionamiento_codigo_empresa');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turno_estacionamiento');
    }
};
