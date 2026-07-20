<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obrasocial_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->unique();
            $table->string('descripcion', 30);
            $table->string('numero', 15)->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['descripcion', 'codigo']);
        });

        Schema::create('sindicato_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->unique();
            $table->string('descripcion', 30);
            $table->string('numero', 15)->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['descripcion', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sindicato_sueldos');
        Schema::dropIfExists('obrasocial_sueldos');
    }
};
