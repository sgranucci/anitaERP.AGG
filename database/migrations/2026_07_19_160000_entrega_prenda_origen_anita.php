<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotencia del importador de histórico de entregas desde Anita (entrprenda):
 * guarda el id original para no duplicar en reimportaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entrega_prenda_sueldos', function (Blueprint $table) {
            if (! Schema::hasColumn('entrega_prenda_sueldos', 'origen_anita_id')) {
                $table->unsignedBigInteger('origen_anita_id')->nullable()->after('usuario_id');
                $table->index('origen_anita_id', 'entrega_prenda_origen_anita_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entrega_prenda_sueldos', function (Blueprint $table) {
            if (Schema::hasColumn('entrega_prenda_sueldos', 'origen_anita_id')) {
                $table->dropIndex('entrega_prenda_origen_anita_idx');
                $table->dropColumn('origen_anita_id');
            }
        });
    }
};
