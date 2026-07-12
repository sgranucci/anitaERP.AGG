<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa el mapeo de códigos Anita para bingo (confirmado por operador 07/07/2026):
 * - Conceptos ambiguos: BUB_APE→10, BUB_CIE→11, PREMEFEC→5 (concbingo).
 * - Cartones (rendcarton.rendc_carton, por valor actual): C2000→3, C3000→5, C4000→6.
 */
return new class extends Migration
{
    /** ERP codigo => concbingo.concb_concepto */
    private const CONCEPTOS = [
        'BUB_APE' => 10,
        'BUB_CIE' => 11,
        'PREMEFEC' => 5,
    ];

    /** ERP codigo => rendc_carton */
    private const CARTONES = [
        'C2000' => 3,
        'C3000' => 5,
        'C4000' => 6,
    ];

    public function up(): void
    {
        if (Schema::hasTable('bingo_concepto_rendicion') && Schema::hasColumn('bingo_concepto_rendicion', 'codigo_anita')) {
            foreach (self::CONCEPTOS as $codigoErp => $codigoAnita) {
                DB::table('bingo_concepto_rendicion')
                    ->where('codigo', $codigoErp)
                    ->update(['codigo_anita' => $codigoAnita, 'updated_at' => now()]);
            }
        }

        if (Schema::hasTable('bingo_carton') && Schema::hasColumn('bingo_carton', 'codigo_anita')) {
            foreach (self::CARTONES as $codigoErp => $codigoAnita) {
                DB::table('bingo_carton')
                    ->where('codigo', $codigoErp)
                    ->update(['codigo_anita' => $codigoAnita, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bingo_concepto_rendicion') && Schema::hasColumn('bingo_concepto_rendicion', 'codigo_anita')) {
            DB::table('bingo_concepto_rendicion')
                ->whereIn('codigo', array_keys(self::CONCEPTOS))
                ->update(['codigo_anita' => null, 'updated_at' => now()]);
        }

        if (Schema::hasTable('bingo_carton') && Schema::hasColumn('bingo_carton', 'codigo_anita')) {
            DB::table('bingo_carton')
                ->whereIn('codigo', array_keys(self::CARTONES))
                ->update(['codigo_anita' => null, 'updated_at' => now()]);
        }
    }
};
