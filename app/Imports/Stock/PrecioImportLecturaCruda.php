<?php

namespace App\Imports\Stock;

use Maatwebsite\Excel\Concerns\ToArray;

/** Lee la hoja Excel sin interpretar encabezados (detección de fila de títulos). */
final class PrecioImportLecturaCruda implements ToArray
{
    public function array(array $array): array
    {
        return $array;
    }
}
