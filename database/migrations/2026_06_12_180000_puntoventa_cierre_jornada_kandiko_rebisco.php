<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PV del proceso cierre Waitry: Kandiko CAEA 00031, Rebisco CAEA 00030.
     */
    public function up(): void
    {
        if (! Schema::hasTable('gastronomia_cierre_jornada_config')) {
            return;
        }

        $mapa = [
            2 => 104, // CAEA KSA 31 (00031)
            3 => 4,   // CAEA Rebisco (00030)
        ];

        foreach ($mapa as $empresaId => $puntoventaId) {
            DB::table('gastronomia_cierre_jornada_config')
                ->where('empresa_id', $empresaId)
                ->update([
                    'puntoventa_id' => $puntoventaId,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('gastronomia_cierre_jornada_config')) {
            return;
        }

        DB::table('gastronomia_cierre_jornada_config')
            ->whereIn('empresa_id', [2, 3])
            ->update([
                'puntoventa_id' => null,
                'updated_at' => now(),
            ]);
    }
};
