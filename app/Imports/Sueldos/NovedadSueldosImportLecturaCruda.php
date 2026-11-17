<?php

namespace App\Imports\Sueldos;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/** Lectura cruda de hoja Excel/CSV de novedades (sin interpretar encabezados). */
final class NovedadSueldosImportLecturaCruda implements ToArray, WithCustomCsvSettings
{
    public function __construct(
        private string $delimiter = ';'
    ) {
    }

    public function array(array $array): array
    {
        return $array;
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => $this->delimiter,
            'enclosure' => '"',
            'escape_character' => '\\',
            'contiguous' => false,
            'input_encoding' => 'UTF-8',
        ];
    }
}
