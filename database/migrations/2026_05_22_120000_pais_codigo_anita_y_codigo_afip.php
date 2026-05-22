<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PaisCodigoAnitaYCodigoAfip extends Migration
{
    public function up(): void
    {
        Schema::table('pais', function (Blueprint $table) {
            $table->string('codigo_afip', 3)->nullable()->after('codigo');
        });

        Schema::table('pais', function (Blueprint $table) {
            $table->string('codigo', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pais', function (Blueprint $table) {
            $table->dropColumn('codigo_afip');
        });
    }
}
