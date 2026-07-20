<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacacion_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->unique();
            $table->string('descripcion', 30);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['descripcion', 'codigo']);
        });

        Schema::create('vacacion_periodo_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('vacacion_id');
            $table->unsignedInteger('nro_linea');
            $table->date('fecha_desde')->nullable();
            $table->date('fecha_hasta')->nullable();
            $table->string('tipo_dia', 20)->nullable();
            $table->unsignedInteger('cantidad_dias')->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['vacacion_id', 'nro_linea'], 'vacperiodo_vacacion_linea_uq');
            $table->index(['vacacion_id', 'fecha_desde', 'fecha_hasta'], 'vacperiodo_rango_idx');

            $table->foreign('vacacion_id')
                ->references('id')
                ->on('vacacion_sueldos')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacacion_periodo_sueldos');
        Schema::dropIfExists('vacacion_sueldos');
    }
};
