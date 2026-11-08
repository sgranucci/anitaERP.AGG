<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;

/**
 * Anexo VII / l-anexo7.c: fallos, anticipos y excedentes de cajeros.
 *
 * Fallo = diferencia negativa del cierre; excedente = positiva.
 * Concepto (como l-anexo7.c): "Fallo", "Excedente", "Excedente/Fallo"
 * o "Anticipo de sueldo" — no el tipo de tarea/caja.
 *
 * Anticipos de sueldo: fuera de alcance. No hay maestro/proceso en Tesorería
 * Crown ni lectura viva de Anita; la columna queda siempre en 0 a propósito.
 */
final class TesoreriaCrownAnexo7Calculo
{
    /**
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     totales: array{fallos: float, anticipos: float, excedentes: float},
     *     avisos: list<string>
     * }
     */
    public static function deJornada(TesoreriaCrownJornada $jornada): array
    {
        $base = TesoreriaCrownResumenCajeroCalculo::deJornada($jornada);
        $filas = [];
        $totales = ['fallos' => 0.0, 'anticipos' => 0.0, 'excedentes' => 0.0];
        foreach ($base['filas'] as $fila) {
            $dif = (float) $fila['diferencia'];
            $falloMozo = (float) ($fila['fallo_mozo'] ?? 0);
            $fallos = 0.0;
            $excedentes = 0.0;
            if ($dif < -0.001) {
                $fallos = abs($dif);
            } elseif ($dif > 0.001) {
                $excedentes = $dif;
            }
            if ($falloMozo > 0.001) {
                $fallos = round($fallos + $falloMozo, 2);
            }
            $anticipos = 0.0;
            if ($fallos < 0.001 && $excedentes < 0.001 && $anticipos < 0.001) {
                continue;
            }
            $filas[] = [
                'caja' => $fila['caja'],
                'cajero' => $fila['cajero'],
                'legajo' => $fila['legajo'],
                'concepto' => self::concepto($fallos, $excedentes, $anticipos),
                'fallos' => $fallos,
                'anticipos' => $anticipos,
                'excedentes' => $excedentes,
            ];
            $totales['fallos'] = round($totales['fallos'] + $fallos, 2);
            $totales['anticipos'] = round($totales['anticipos'] + $anticipos, 2);
            $totales['excedentes'] = round($totales['excedentes'] + $excedentes, 2);
        }

        $avisos = [];
        if ($filas === []) {
            $avisos[] = 'No hay fallos ni excedentes en esta jornada.';
        }

        return [
            'filas' => $filas,
            'totales' => $totales,
            'avisos' => $avisos,
        ];
    }

    /**
     * Etiqueta de concepto de l-anexo7.c (FORM1 / Anticipo de sueldo).
     */
    private static function concepto(float $fallos, float $excedentes, float $anticipos): string
    {
        $hayFallo = $fallos > 0.001;
        $hayExcedente = $excedentes > 0.001;
        if ($hayFallo && $hayExcedente) {
            return 'Excedente/Fallo';
        }
        if ($hayExcedente) {
            return 'Excedente';
        }
        if ($hayFallo) {
            return 'Fallo';
        }
        if ($anticipos > 0.001 || $anticipos < -0.001) {
            return 'Anticipo de sueldo';
        }

        return '';
    }
}
