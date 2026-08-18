<?php

namespace App\Support\Sueldos\ReporteDefinible;

use App\Models\Sueldos\ReporteSueldosDefinible;
use App\Models\Sueldos\ReporteSueldosDefinibleColumna;
use App\Models\Sueldos\ReporteSueldosDefinibleConcepto;
use App\Models\Sueldos\ReporteSueldosDefinibleVersion;

/**
 * Rehidrata un reporte transient desde snapshot de versión (sin mutar definición viva).
 */
final class ReporteSueldosDefinibleSnapshotSupport
{
    public function desdeVersion(ReporteSueldosDefinible $vivo, ?ReporteSueldosDefinibleVersion $version): ReporteSueldosDefinible
    {
        if ($version === null) {
            $vivo->loadMissing('columnas.conceptos');

            return $vivo;
        }

        $snap = (array) ($version->snapshot ?? []);
        $reporte = $vivo->replicate(['id', 'created_at', 'updated_at']);
        $reporte->id = $vivo->id;
        $reporte->exists = true;
        $reporte->titulo = (string) ($snap['titulo'] ?? $vivo->titulo);
        $reporte->tipo = (string) ($snap['tipo'] ?? $vivo->tipo);
        $reporte->asociado_codigo = $snap['asociado_codigo'] ?? $vivo->asociado_codigo;

        $columnas = collect();
        foreach ((array) ($snap['columnas'] ?? []) as $i => $col) {
            $columna = new ReporteSueldosDefinibleColumna([
                'reporte_sueldos_definible_id' => $vivo->id,
                'nro_columna' => (int) ($col['nro_columna'] ?? ($i + 1)),
                'descripcion' => (string) ($col['descripcion'] ?? ''),
                'contenido' => (string) ($col['contenido'] ?? ReporteSueldosDefinibleSupport::CONTENIDO_IMPORTE),
                'campo_empleado' => $col['campo_empleado'] ?? null,
                'largo' => $col['largo'] ?? null,
                'formula' => $col['formula'] ?? null,
                'orden' => (int) ($col['orden'] ?? 0),
            ]);
            $columna->id = -1 * ($i + 1);
            $columna->exists = false;
            $conceptos = collect();
            foreach ((array) ($col['conceptos'] ?? []) as $j => $con) {
                $concepto = new ReporteSueldosDefinibleConcepto([
                    'columna_id' => $columna->id,
                    'concepto_codigo' => (int) ($con['concepto_codigo'] ?? 0),
                    'orden' => (int) ($con['orden'] ?? ($j + 1)),
                    'signo' => (($con['signo'] ?? '+') === '-') ? '-' : '+',
                ]);
                $conceptos->push($concepto);
            }
            $columna->setRelation('conceptos', $conceptos);
            $columnas->push($columna);
        }
        $reporte->setRelation('columnas', $columnas->sortBy('orden')->values());

        return $reporte;
    }
}
