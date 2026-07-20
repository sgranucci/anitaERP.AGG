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
     */
    public static function diasPorAntiguedad(int $aniosAntiguedad): int
    {
        $dias = self::ESCALA_LCT[0];
        foreach (self::ESCALA_LCT as $minimo => $valor) {
            if ($aniosAntiguedad >= $minimo) {
                $dias = $valor;
            }
        }

        return $dias;
    }

    /**
     * Años de antiguedad al cierre del periodo (31/12/$anio), sumando antiguedad anterior en años.
     */
    public static function aniosAntiguedadAlCierre(Carbon $fechaIngreso, int $anio, int $aniosPrevios = 0): int
    {
        $cierre = Carbon::create($anio, 12, 31);
        if ($fechaIngreso->greaterThan($cierre)) {
            return $aniosPrevios;
        }

        return $aniosPrevios + (int) $fechaIngreso->diffInYears($cierre);
    }

    /**
     * Dias devengados para un periodo (año calendario), contemplando proporcionalidad.
     *
     * @return array{dias: float, dias_escala: int, dias_trabajados: int, proporcional: bool}
     */
    public static function devengadoPeriodo(
        Carbon $fechaIngreso,
        int $anio,
        int $aniosPrevios = 0,
        ?Carbon $fechaEgreso = null
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
        $aniosAntiguedad = self::aniosAntiguedadAlCierre($fechaIngreso, $anio, $aniosPrevios);
        $diasEscala = self::diasPorAntiguedad($aniosAntiguedad);

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
        if ($antiguedadAnterior === null || trim($antiguedadAnterior) === '') {
            return 0;
        }
        $partes = preg_split('/[-\/]/', trim($antiguedadAnterior));
        if (! is_array($partes) || $partes === []) {
            return 0;
        }

        return max(0, (int) $partes[0]);
    }
}
