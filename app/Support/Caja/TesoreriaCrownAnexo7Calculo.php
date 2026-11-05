<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;

/**
 * Anexo VII / l-anexo7.c: fallos, anticipos y excedentes de cajeros.
 *
 * Fallo = diferencia negativa del cierre; excedente = positiva.
 * Anticipos de sueldos (ctactes Anita) no están en el ERP: quedan en cero.
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
                'concepto' => TesoreriaCrownAsignacionCatalogo::TAREAS[$fila['tarea']] ?? $fila['tarea'],
                'fallos' => $fallos,
                'anticipos' => $anticipos,
                'excedentes' => $excedentes,
            ];
            $totales['fallos'] = round($totales['fallos'] + $fallos, 2);
            $totales['anticipos'] = round($totales['anticipos'] + $anticipos, 2);
            $totales['excedentes'] = round($totales['excedentes'] + $excedentes, 2);
        }

        $avisos = $filas === []
            ? ['No hay fallos ni excedentes en esta jornada.']
            : ['Los anticipos de sueldos de Anita (ctactes) no están cargados en el ERP: la columna queda en cero.'];

        return [
            'filas' => $filas,
            'totales' => $totales,
            'avisos' => $avisos,
        ];
    }
}
