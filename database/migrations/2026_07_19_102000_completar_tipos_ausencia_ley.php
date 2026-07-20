<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Completa el catálogo de tipos de ausencia con las licencias especiales pagas
 * vigentes de la LCT (art. 158) y otras de uso habitual, de forma idempotente
 * (solo inserta las que falten por código; no pisa ediciones del operador).
 *
 * Nota legal: a nivel nacional la licencia por nacimiento (padre) es de 2 días
 * corridos (LCT art. 158 inc. a). Varias jurisdicciones y CCT amplían plazos;
 * eso se ajusta desde el ABM.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();
        $tipos = [
            ['codigo' => 25, 'nombre' => 'Licencia por fallecimiento de hermano', 'categoria' => 'licencia', 'goza_sueldo' => true, 'tipo_dias' => 'corridos', 'tope_dias_anio' => 1, 'orden' => 25],
            ['codigo' => 26, 'nombre' => 'Licencia por donación de sangre', 'categoria' => 'licencia', 'goza_sueldo' => true, 'tipo_dias' => 'corridos', 'tope_dias_anio' => null, 'orden' => 26],
            ['codigo' => 27, 'nombre' => 'Licencia por mudanza (CCT)', 'categoria' => 'licencia', 'goza_sueldo' => true, 'tipo_dias' => 'corridos', 'tope_dias_anio' => null, 'orden' => 27],
            ['codigo' => 28, 'nombre' => 'Licencia por violencia de género', 'categoria' => 'licencia', 'goza_sueldo' => true, 'requiere_certificado' => true, 'tipo_dias' => 'corridos', 'tope_dias_anio' => null, 'orden' => 28],
            ['codigo' => 12, 'nombre' => 'Excedencia (art. 183 LCT)', 'categoria' => 'licencia', 'goza_sueldo' => false, 'computa_antiguedad' => false, 'tipo_dias' => 'corridos', 'tope_dias_anio' => null, 'orden' => 12],
        ];

        foreach ($tipos as $tipo) {
            if (DB::table('tipo_ausencia_sueldos')->where('codigo', $tipo['codigo'])->exists()) {
                continue;
            }
            DB::table('tipo_ausencia_sueldos')->insert(array_merge([
                'afecta_saldo_vacaciones' => false,
                'goza_sueldo' => true,
                'computa_antiguedad' => true,
                'requiere_certificado' => false,
                'tipo_dias' => 'corridos',
                'tope_dias_anio' => null,
                'concepto_id' => null,
                'color' => null,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ], $tipo));
        }
    }

    public function down(): void
    {
        DB::table('tipo_ausencia_sueldos')->whereIn('codigo', [12, 25, 26, 27, 28])->delete();
    }
};
