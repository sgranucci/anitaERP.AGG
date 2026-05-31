<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('juego_uif', function (Blueprint $table) {
            $table->string('riesgo', 50)->nullable()->change();
            $table->unsignedBigInteger('puntaje')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('juego_uif', function (Blueprint $table) {
            $table->string('riesgo', 50)->nullable(false)->change();
            $table->unsignedBigInteger('puntaje')->nullable(false)->change();
        });
    }
};
