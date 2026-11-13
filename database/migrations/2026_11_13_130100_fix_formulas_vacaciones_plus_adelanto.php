<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plus/adelanto vacacional (422/420): no liquidar saldo TVAC sin días tomados.
 * - Importe: el fallback TVAC del else pasa a 0 (solo paga si novedad(420|422) > 0.5).
 * - Cantidad: muestra los días de la novedad, no el saldo de cuota hardcodeado 2023-2025.
 */
return new class extends Migration
{
    private const TVAC_422 = 'si((periodo.tipo_vacaciones == 1), ((total_vacaciones(2023, 4) + total_vacaciones(2024, 4)) + total_vacaciones(2025, 4)), 0)';

    private const TVAC_420 = 'si((periodo.tipo_vacaciones == 2), si((((total_vacaciones(2023, 4) + total_vacaciones(2024, 4)) + total_vacaciones(2025, 4)) < 0), 0, ((total_vacaciones(2023, 4) + total_vacaciones(2024, 4)) + total_vacaciones(2025, 4))), 0)';

    public function up(): void
    {
        if (! Schema::hasTable('concepto_sueldos')) {
            return;
        }

        $this->parchear(422, self::TVAC_422, 'novedad(422)');
        $this->parchear(420, self::TVAC_420, 'novedad(420)');
    }

    public function down(): void
    {
        // No se restaura el fallback TVAC: era el bug (210 recibos / cantidad errónea).
    }

    private function parchear(int $codigo, string $exprTvac, string $formulaCantidad): void
    {
        $row = DB::table('concepto_sueldos')->where('codigo', $codigo)->first(['id', 'formula', 'formula_cantidad']);
        if (! $row) {
            return;
        }

        $formula = (string) ($row->formula ?? '');
        if ($formula !== '' && str_contains($formula, $exprTvac)) {
            $formula = str_replace($exprTvac, '0', $formula);
        }

        DB::table('concepto_sueldos')->where('id', $row->id)->update([
            'formula' => $formula !== '' ? $formula : $row->formula,
            'formula_cantidad' => $formulaCantidad,
            'updated_at' => now(),
        ]);
    }
};
