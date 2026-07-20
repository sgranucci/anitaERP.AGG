<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Conceptos estándar en sintaxis ERP (NO fórmulas Anita).
 * Incluye retención de Ganancias vía ganancias().
 * Idempotente: solo inserta si el código no existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();
        $conceptos = [
            [
                'codigo' => 100,
                'descripcion' => 'Sueldo básico',
                'tipo' => 'remunerativo',
                'suma_a' => 'remunerativo',
                'momento' => 'mensual',
                'factor' => null,
                'formula' => 'empleado.sueldo_basico',
                'formula_cantidad' => null,
                'formula_valor' => null,
                'va_recibo' => true,
                'concepto_afip' => null,
                'orden' => 100,
            ],
            [
                'codigo' => 110,
                'descripcion' => 'Antigüedad',
                'tipo' => 'remunerativo',
                'suma_a' => 'remunerativo',
                'momento' => 'mensual',
                'factor' => null,
                'formula' => 'si(empleado.antiguedad_anios >= 1, redondear(empleado.sueldo_basico * 0.01 * empleado.antiguedad_anios, 2), 0)',
                'formula_cantidad' => null,
                'formula_valor' => null,
                'va_recibo' => true,
                'concepto_afip' => null,
                'orden' => 110,
            ],
            [
                'codigo' => 200,
                'descripcion' => 'Aporte jubilatorio',
                'tipo' => 'aporte',
                'suma_a' => 'descuentos',
                'momento' => 'mensual',
                'factor' => null,
                // Importe positivo: el tipo aporte lo resta del neto en el recibo.
                'formula' => 'redondear(acum("REM") * param("ALIC_JUBILACION") / 100, 2)',
                'formula_cantidad' => null,
                'formula_valor' => null,
                'va_recibo' => true,
                'concepto_afip' => null,
                'orden' => 200,
            ],
            [
                'codigo' => 210,
                'descripcion' => 'Aporte Ley 19032',
                'tipo' => 'aporte',
                'suma_a' => 'descuentos',
                'momento' => 'mensual',
                'factor' => null,
                'formula' => 'redondear(acum("REM") * param("ALIC_LEY19032") / 100, 2)',
                'formula_cantidad' => null,
                'formula_valor' => null,
                'va_recibo' => true,
                'concepto_afip' => null,
                'orden' => 210,
            ],
            [
                'codigo' => 220,
                'descripcion' => 'Aporte obra social',
                'tipo' => 'aporte',
                'suma_a' => 'descuentos',
                'momento' => 'mensual',
                'factor' => null,
                'formula' => 'redondear(acum("REM") * param("ALIC_OBRASOCIAL") / 100, 2)',
                'formula_cantidad' => null,
                'formula_valor' => null,
                'va_recibo' => true,
                'concepto_afip' => null,
                'orden' => 220,
            ],
            [
                'codigo' => 900,
                'descripcion' => 'Retención Imp. Ganancias',
                'tipo' => 'retencion',
                'suma_a' => 'descuentos',
                'momento' => 'mensual',
                'factor' => null,
                // El puente corre justo antes de evaluar esta fórmula.
                // Importe positivo (= retención del mes); tipo retencion lo resta del neto.
                'formula' => 'ganancias()',
                'formula_cantidad' => null,
                'formula_valor' => null,
                'va_recibo' => true,
                'concepto_afip' => null,
                'orden' => 900,
            ],
        ];

        foreach ($conceptos as $c) {
            $existe = DB::table('concepto_sueldos')->where('codigo', $c['codigo'])->exists();
            if ($existe) {
                continue;
            }
            DB::table('concepto_sueldos')->insert(array_merge($c, [
                'mes_retroactivo' => 0,
                'leyenda_recibo' => null,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]));
        }
    }

    public function down(): void
    {
        DB::table('concepto_sueldos')->whereIn('codigo', [100, 110, 200, 210, 220, 900])->delete();
    }
};
