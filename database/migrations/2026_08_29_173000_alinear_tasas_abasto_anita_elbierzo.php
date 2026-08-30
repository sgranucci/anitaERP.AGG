<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tasas de abasto desfasadas respecto de Anita (aba_tasa).
 * Incidente: FAC A 10-1854 Cinco Saltos 65 vs ERP 60.
 */
return new class extends Migration
{
    /** @var array<int, array{anita: float, erp: float}> */
    private const TASAS = [
        10 => ['anita' => 0.082, 'erp' => 0.08],
        30 => ['anita' => 65.0, 'erp' => 60.0],
        31 => ['anita' => 140.0, 'erp' => 40.0],
        402 => ['anita' => 340.0, 'erp' => 310.0],
        310 => ['anita' => 400.0, 'erp' => 261.0],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $now = now();
        foreach (self::TASAS as $codigo => $tasas) {
            DB::table('abasto')
                ->where('codigo', (string) $codigo)
                ->update([
                    'tasa' => $tasas['anita'],
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $now = now();
        foreach (self::TASAS as $codigo => $tasas) {
            DB::table('abasto')
                ->where('codigo', (string) $codigo)
                ->update([
                    'tasa' => $tasas['erp'],
                    'updated_at' => $now,
                ]);
        }
    }
};
