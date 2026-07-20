<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fallocaja_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tipo', 40);            // Bingo / Máquinas (texto completo; en Anita tblf_id 1/2)
            $table->integer('orden');
            $table->decimal('desde', 15, 2)->default(0);
            $table->decimal('hasta', 15, 2)->default(0);
            $table->string('sancion', 40)->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['tipo', 'orden']);
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fallocaja_sueldos');
    }
};
