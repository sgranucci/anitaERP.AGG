<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mapea el código Anita (concbingo.concb_concepto) de los conceptos de bingo del ERP
 * cuya correspondencia es inequívoca (descripción + porcentaje). Relevado desde Anita
 * (tabla concbingo) el 07/07/2026.
 *
 * Los conceptos SOBRANTE, VALES, REDONDEO, REFUERZO y DEPOSITO NO llevan código: se
 * graban en columnas de la cabecera rendbingo (rendb_sobrante, rendb_vales,
 * rendb_redondeo, rendb_refuer_prest, rendb_deposito), no en rendpremio.
 */
return new class extends Migration
{
    /** ERP codigo => concbingo.concb_concepto */
    private const MAPEO = [
        'BINGO47' => 1,
        'LINEA6' => 2,
        'PANTALLAS' => 4,
        'PREM2' => 20,
        'PREM5' => 30,
        'PREM10' => 40,
        'PREM15' => 50,
        'PREM65' => 55,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('bingo_concepto_rendicion') || ! Schema::hasColumn('bingo_concepto_rendicion', 'codigo_anita')) {
            return;
        }

        foreach (self::MAPEO as $codigoErp => $codigoAnita) {
            DB::table('bingo_concepto_rendicion')
                ->where('codigo', $codigoErp)
                ->update(['codigo_anita' => $codigoAnita, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('bingo_concepto_rendicion') || ! Schema::hasColumn('bingo_concepto_rendicion', 'codigo_anita')) {
            return;
        }

        DB::table('bingo_concepto_rendicion')
            ->whereIn('codigo', array_keys(self::MAPEO))
            ->update(['codigo_anita' => null, 'updated_at' => now()]);
    }
};
