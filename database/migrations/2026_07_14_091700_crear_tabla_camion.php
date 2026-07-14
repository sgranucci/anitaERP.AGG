<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 20);
            $table->string('dominio', 15)->nullable();
            $table->string('habilitacion', 30)->nullable();
            $table->string('tipo', 15)->nullable();
            $table->string('dominio_acoplado', 10)->nullable();
            $table->string('cuit_chofer', 13)->nullable();
            $table->unsignedInteger('cantidad_precinto')->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique('codigo');
            $table->index(['dominio', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camion');
    }
};
