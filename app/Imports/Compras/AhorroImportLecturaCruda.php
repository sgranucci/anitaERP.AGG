<?php

namespace App\Imports\Compras;

use Maatwebsite\Excel\Concerns\ToArray;

/** Lee la hoja de ahorros sin interpretar encabezados (el servicio detecta la fila de títulos). */
final class AhorroImportLecturaCruda implements ToArray
{
    public function array(array $array): array
    {
        return $array;
    }
}
