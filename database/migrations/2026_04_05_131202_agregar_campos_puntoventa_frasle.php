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
        Schema::table('puntoventa', function (Blueprint $table) {
            $table->unsignedBigInteger('division')->after('actividad_arca_id')->nullable();
            $table->string('numeropoliza',255)->after('division')->nullable();
            $table->unsignedBigInteger('puntoventa_remito')->after('numeropoliza')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('puntoventa', function (Blueprint $table) {
            $table->dropForeign('fk_puntoventa_remito_puntoventa');
            $table->dropColumn('puntoventa_remito_id');
            $table->dropColumn('numeropoliza');
            $table->dropColumn('division_id');
        });
    }
};
