<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicion_estado', function (Blueprint $table) {
            $table->dateTime('fecha')->change();
        });
        Schema::table('ordencompra_estado', function (Blueprint $table) {
            $table->dateTime('fecha')->change();
        });
    }

    public function down(): void
    {
        Schema::table('requisicion_estado', function (Blueprint $table) {
            $table->date('fecha')->change();
        });
        Schema::table('ordencompra_estado', function (Blueprint $table) {
            $table->date('fecha')->change();
        });
    }
};
