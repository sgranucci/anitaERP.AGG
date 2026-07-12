<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código entero de Anita (concbingo.concb_concepto / código de cartón) para poder
 * grabar rendpremio y rendcarton en la réplica Informix junto a rendbingo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bingo_concepto_rendicion') && ! Schema::hasColumn('bingo_concepto_rendicion', 'codigo_anita')) {
            Schema::table('bingo_concepto_rendicion', function (Blueprint $table) {
                $table->integer('codigo_anita')->nullable()->after('codigo');
            });
        }

        if (Schema::hasTable('bingo_carton') && ! Schema::hasColumn('bingo_carton', 'codigo_anita')) {
            Schema::table('bingo_carton', function (Blueprint $table) {
                $table->integer('codigo_anita')->nullable()->after('codigo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bingo_concepto_rendicion') && Schema::hasColumn('bingo_concepto_rendicion', 'codigo_anita')) {
            Schema::table('bingo_concepto_rendicion', function (Blueprint $table) {
                $table->dropColumn('codigo_anita');
            });
        }

        if (Schema::hasTable('bingo_carton') && Schema::hasColumn('bingo_carton', 'codigo_anita')) {
            Schema::table('bingo_carton', function (Blueprint $table) {
                $table->dropColumn('codigo_anita');
            });
        }
    }
};
