<?php

namespace App\Imports\Contable;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/** Lee la hoja Excel/CSV sin interpretar encabezados (detección de fila de títulos). */
final class AsientoImportLecturaCruda implements ToArray, WithCustomCsvSettings
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
