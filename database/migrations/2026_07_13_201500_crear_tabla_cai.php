<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cai', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('orden')->unique();
            $table->string('tipo', 3)->default('REM');
            $table->string('descripcion', 30)->default('Remito');
            $table->char('letra', 1)->default('R');
            $table->unsignedInteger('sucursal')->default(1);
            $table->string('numero_cai', 18);
            $table->date('fecha_vencimiento');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['tipo', 'letra', 'sucursal']);
            $table->index(['letra', 'fecha_vencimiento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cai');
    }
};
