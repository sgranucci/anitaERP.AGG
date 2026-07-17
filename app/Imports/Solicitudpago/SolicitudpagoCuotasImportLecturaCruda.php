<?php

namespace App\Imports\Solicitudpago;

use Maatwebsite\Excel\Concerns\ToArray;

/** Lectura cruda de Excel de cuotas SP. */
final class SolicitudpagoCuotasImportLecturaCruda implements ToArray
{
    public function array(array $array): array
    {
        return $array;
    }
}
