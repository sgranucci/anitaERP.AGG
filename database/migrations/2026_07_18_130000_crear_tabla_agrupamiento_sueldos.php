<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agrupamiento_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->unique();
            $table->string('descripcion', 30);
            $table->string('fallo_tipo', 40)->nullable(); // Bingo / Máquinas (en Anita agr_id_fallo 1/2; 0 = sin fallo)
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['descripcion', 'codigo']);
            $table->index('fallo_tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agrupamiento_sueldos');
    }
};
