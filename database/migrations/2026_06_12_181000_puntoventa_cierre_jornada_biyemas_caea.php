<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PV CAEA Biyemas (00020) para proceso cierre Waitry.
     */
    public function up(): void
    {
        if (! Schema::hasTable('gastronomia_cierre_jornada_config')) {
            return;
        }

        DB::table('gastronomia_cierre_jornada_config')
            ->where('empresa_id', 1)
            ->update([
                'puntoventa_id' => 24,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('gastronomia_cierre_jornada_config')) {
            return;
        }

        DB::table('gastronomia_cierre_jornada_config')
            ->where('empresa_id', 1)
            ->update([
                'puntoventa_id' => null,
                'updated_at' => now(),
            ]);
    }
};
