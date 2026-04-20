<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('profesion_uif', function (Blueprint $table) {
            if (!Schema::hasColumn('profesion_uif', 'riesgo')) {
                $table->string('riesgo', 50)->nullable()->after('nombre');
                $table->unsignedBigInteger('puntaje')->nullable()->after('riesgo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profesion_uif', function (Blueprint $table) {
            if (Schema::hasColumn('profesion_uif', 'puntaje')) {
                $table->dropColumn('puntaje');
                $table->dropColumn('riesgo');
            }
        });
    }
};
