<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Serie FACPCE RT 6 / Res. JG 539-18 publicada hasta junio de 2026.
     * El ABM permite reemplazar cualquier valor si FACPCE publica una precisión mayor.
     */
    private const INDICES = [
        '2024-12-01' => 7694.0075,
        '2025-01-01' => 7864.1257,
        '2025-02-01' => 8052.9927,
        '2025-03-01' => 8353.3158,
        '2025-04-01' => 8585.6078,
        '2025-05-01' => 8714.4871,
        '2025-06-01' => 8855.5681,
        '2025-07-01' => 9023.9730,
        '2025-08-01' => 9193.2441,
        '2025-09-01' => 9384.0900,
        '2025-10-01' => 9603.8623,
        '2025-11-01' => 9841.3581,
        '2025-12-01' => 10121.3715,
        '2026-01-01' => 10413.0309,
        '2026-02-01' => 10714.6255,
        '2026-03-01' => 11077.0608,
        '2026-04-01' => 11363.0904,
        '2026-05-01' => 11607.3937,
        '2026-06-01' => 11826.4100,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('ajuste_inflacion_indice')) {
            return;
        }

        foreach (self::INDICES as $periodo => $valor) {
            if (DB::table('ajuste_inflacion_indice')->where('periodo', $periodo)->exists()) {
                continue;
            }

            DB::table('ajuste_inflacion_indice')->insert([
                'periodo' => $periodo,
                'valor' => $valor,
                'fuente' => 'FACPCE RT 6 — Res. JG 539-18',
                'provisorio' => false,
                'usuario_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No borrar índices: pueden haber sido usados o corregidos por el operador.
    }
};
