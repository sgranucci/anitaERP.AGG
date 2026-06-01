<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobranza', function (Blueprint $table) {
            $table->string('numerotransaccion', 32)->change();
        });

        Schema::table('caja_movimiento', function (Blueprint $table) {
            $table->string('numerotransaccion', 32)->change();
        });
    }

    public function down(): void
    {
        Schema::table('cobranza', function (Blueprint $table) {
            $table->unsignedBigInteger('numerotransaccion')->change();
        });

        Schema::table('caja_movimiento', function (Blueprint $table) {
            $table->unsignedBigInteger('numerotransaccion')->change();
        });
    }
};
