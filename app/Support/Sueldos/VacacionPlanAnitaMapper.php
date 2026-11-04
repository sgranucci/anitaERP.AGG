<?php

namespace App\Support\Sueldos;

use Carbon\Carbon;

/**
 * Arma el tramo de vacaciones que se ve en el legajo a partir de una fila de vacempl.
 * vace_sale_vac es la fecha en que el empleado sale de vacaciones.
 */
class VacacionPlanAnitaMapper
{
    /**
     * @return array{anio:int, fecha_desde:string, fecha_hasta:string, dias:float, estado:string}|null
     */
    public static function evento(
        int $periodo,
        int $saleVac,
        float $diasTomados,
        float $diasATomar,
        bool $liquidada
    ): ?array {
        $anio = (int) substr((string) $periodo, 0, 4);
        if ($anio < 1990 || $anio > 2100) {
            return null;
        }

        $desde = VacacionFechaAnita::erpDesdeAnita($saleVac);
        if ($desde === null) {
            return null;
        }
        $anioSalida = (int) substr($desde, 0, 4);
        if ($anioSalida < 2000 || $anioSalida > 2100) {
            return null;
        }

        if ($diasATomar > 0) {
            $dias = $diasATomar;
        } elseif ($diasTomados > 0) {
            $dias = $diasTomados;
        } else {
            return null;
        }

        if ($liquidada) {
            $estado = 'liquidada';
        } elseif ($diasTomados > 0) {
            $estado = 'tomada';
        } else {
            $estado = 'planificada';
        }

        $hasta = Carbon::parse($desde)->addDays(max(0, (int) ceil($dias) - 1))->toDateString();

        return [
            'anio' => $anio,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'dias' => round($dias, 2),
            'estado' => $estado,
        ];
    }
}
