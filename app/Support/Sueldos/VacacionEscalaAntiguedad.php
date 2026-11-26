<?php

namespace App\Support\Sueldos;

use Carbon\Carbon;

/**
 * Escala de dias de vacaciones por antiguedad (LCT art. 150) y devengamiento
 * proporcional del primer año (LCT art. 151/153).
 *
 * Es el criterio de mercado (parametrizable). Anita resolvia lo mismo con
 * vacmov (rango de antiguedad -> dias); aca queda centralizado y determinista.
 */
class VacacionEscalaAntiguedad
{
    /**
     * Tramos [antiguedad_minima_anios => dias_corridos]. LCT art. 150.
     *
     * @var array<int, int>
     */
    public const ESCALA_LCT = [
        0 => 14,   // menos de 5 años
        5 => 21,   // 5 a 10 años
        10 => 28,  // 10 a 20 años
        20 => 35,  // más de 20 años
    ];

    /** Umbral de dias trabajados en el año para tener derecho al total (LCT art. 151). */
    public const DIAS_MINIMO_ANIO_COMPLETO = 183;

    /** Proporcional primer año: 1 dia cada N dias trabajados (LCT art. 153). */
    public const DIAS_POR_DIA_PROPORCIONAL = 20;

    /**
     * Dias segun antiguedad (en años cumplidos al 31/12 del periodo).
     *
     * @param  list<array{anios_desde:int, dias:int}>|null  $escala  Tramos de la tabla de vacaciones del
     *                                                              empleado. Null = escala de la ley (LCT).
     */
    public static function diasPorAntiguedad(int $aniosAntiguedad, ?array $escala = null): int
    {
        if ($escala !== null && $escala !== []) {
            usort($escala, fn ($a, $b) => $a['anios_desde'] <=> $b['anios_desde']);
            $dias = (int) $escala[0]['dias'];
            foreach ($escala as $tramo) {
                if ($aniosAntiguedad >= (int) $tramo['anios_desde']) {
                    $dias = (int) $tramo['dias'];
                }
            }

            return $dias;
        }

        $dias = self::ESCALA_LCT[0];
        foreach (self::ESCALA_LCT as $minimo => $valor) {
            if ($aniosAntiguedad >= $minimo) {
                $dias = $valor;
            }
        }

        return $dias;
    }

    /**
     * Fecha de ingreso "computable": el ingreso menos la antigüedad reconocida (Anita aa-mm-dd).
     * Restar la duración completa (años + meses + días) evita perder los meses al medir.
     */
    public static function fechaIngresoComputable(Carbon $fechaIngreso, string|int|null $antiguedadAnterior = null): Carbon
    {
        if (is_int($antiguedadAnterior)) {
            $componentes = ['anios' => max(0, $antiguedadAnterior), 'meses' => 0, 'dias' => 0];
        } else {
            $componentes = self::componentesAntiguedadAnterior($antiguedadAnterior);
        }

        return $fechaIngreso->copy()->startOfDay()
            ->subYearsNoOverflow($componentes['anios'])
            ->subMonthsNoOverflow($componentes['meses'])
            ->subDays($componentes['dias']);
    }

    /**
     * Años de antiguedad al cierre del periodo (31/12/$anio), sumando la antiguedad anterior (aa-mm-dd).
     */
    public static function aniosAntiguedadAlCierre(Carbon $fechaIngreso, int $anio, string|int|null $antiguedadAnterior = null): int
    {
        $cierre = Carbon::create($anio, 12, 31)->startOfDay();
        $computable = self::fechaIngresoComputable($fechaIngreso, $antiguedadAnterior);
        if ($fechaIngreso->copy()->startOfDay()->greaterThan($cierre)) {
            // Aun no ingreso: solo cuenta lo reconocido de antes.
            return (int) $computable->diffInYears($fechaIngreso->copy()->startOfDay());
        }

        return (int) $computable->diffInYears($cierre);
    }

    /**
     * Dias devengados para un periodo (año calendario), contemplando proporcionalidad.
     *
     * @param  list<array{anios_desde:int, dias:int}>|null  $escala  Tabla de vacaciones del empleado (null = LCT)
     * @return array{dias: float, dias_escala: int, dias_trabajados: int, proporcional: bool}
     */
    public static function devengadoPeriodo(
        Carbon $fechaIngreso,
        int $anio,
        string|int|null $antiguedadAnterior = null,
        ?Carbon $fechaEgreso = null,
        ?array $escala = null
    ): array {
        $inicioAnio = Carbon::create($anio, 1, 1)->startOfDay();
        $finAnio = Carbon::create($anio, 12, 31)->startOfDay();

        $desde = $fechaIngreso->greaterThan($inicioAnio) ? $fechaIngreso->copy()->startOfDay() : $inicioAnio;
        $hasta = $finAnio;
        if ($fechaEgreso !== null && $fechaEgreso->copy()->startOfDay()->lessThan($hasta)) {
            $hasta = $fechaEgreso->copy()->startOfDay();
        }

        if ($hasta->lessThan($desde)) {
            return ['dias' => 0.0, 'dias_escala' => 0, 'dias_trabajados' => 0, 'proporcional' => false];
        }

        $diasTrabajados = (int) $desde->diffInDays($hasta) + 1;
        $aniosAntiguedad = self::aniosAntiguedadAlCierre($fechaIngreso, $anio, $antiguedadAnterior);
        $diasEscala = self::diasPorAntiguedad($aniosAntiguedad, $escala);

        if ($diasTrabajados >= self::DIAS_MINIMO_ANIO_COMPLETO) {
            return [
                'dias' => (float) $diasEscala,
                'dias_escala' => $diasEscala,
                'dias_trabajados' => $diasTrabajados,
                'proporcional' => false,
            ];
        }

        $dias = (float) floor($diasTrabajados / self::DIAS_POR_DIA_PROPORCIONAL);

        return [
            'dias' => $dias,
            'dias_escala' => $diasEscala,
            'dias_trabajados' => $diasTrabajados,
            'proporcional' => true,
        ];
    }

    /**
     * Convierte "aa-mm-dd" (antiguedad anterior Anita) a años enteros (redondeo hacia abajo).
     */
    public static function aniosDesdeAntiguedadAnterior(?string $antiguedadAnterior): int
    {
        return self::componentesAntiguedadAnterior($antiguedadAnterior)['anios'];
    }

    /**
     * Convierte la duración Anita "aa-mm-dd" sin perder meses ni días.
     *
     * @return array{anios: int, meses: int, dias: int}
     */
    public static function componentesAntiguedadAnterior(?string $antiguedadAnterior): array
    {
        $vacio = ['anios' => 0, 'meses' => 0, 'dias' => 0];
        if ($antiguedadAnterior === null || trim($antiguedadAnterior) === '') {
            return $vacio;
        }
        $partes = preg_split('/[-\/]/', trim($antiguedadAnterior));
        if (! is_array($partes) || $partes === []) {
            return $vacio;
        }

        return [
            'anios' => max(0, (int) ($partes[0] ?? 0)),
            'meses' => max(0, (int) ($partes[1] ?? 0)),
            'dias' => max(0, (int) ($partes[2] ?? 0)),
        ];
    }
}
