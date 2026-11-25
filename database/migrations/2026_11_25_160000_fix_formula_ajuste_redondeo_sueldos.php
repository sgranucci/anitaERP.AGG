<?php

use App\Support\Sueldos\ConceptoAnitaMapeo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige el concepto 3000 (Ajuste por redondeo).
 *
 * Anita: `_d:=REDON(tg)`. La traducción previa quedó como
 * `redondear(acum("NETO"), 2)`, que da 0 (no existe acumulador NETO) y
 * además devolvería el neto completo, no el ajuste de centavos.
 *
 * Fórmula correcta: diferencia al peso entero hacia arriba, coherente con
 * los recibos importados de Anita (importe 3000 entre 0,01 y 0,99).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('concepto_sueldos')
            ->where('codigo', ConceptoAnitaMapeo::CODIGO_AJUSTE_REDONDEO)
            ->update([
                'formula' => ConceptoAnitaMapeo::FORMULA_AJUSTE_REDONDEO,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('concepto_sueldos')
            ->where('codigo', ConceptoAnitaMapeo::CODIGO_AJUSTE_REDONDEO)
            ->update([
                'formula' => 'redondear(acum("NETO"), 2)',
                'updated_at' => now(),
            ]);
    }
};
