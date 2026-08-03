<?php

namespace App\Imports\Sueldos;

use Maatwebsite\Excel\Concerns\ToArray;

/** Lectura cruda de hoja Excel de novedades (sin interpretar encabezados). */
final class NovedadSueldosImportLecturaCruda implements ToArray
{
    public function array(array $array): array
    {
        return $array;
    }
}
