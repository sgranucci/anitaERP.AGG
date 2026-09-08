<?php

namespace App\Support\Tesoreria\PosicionBancaria;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Solo detalle de cheques (cols A-K, filas de cabecera + detalle).
 * Evita cargar las ~600 columnas de aging del Excel Posición.
 */
final class PosicionBancariaChequeExcelReadFilter implements IReadFilter
{
    public function readCell($columnAddress, $row, $worksheetName = '')
    {
        if ((int) $row > 750) {
            return false;
        }
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString((string) $columnAddress);

        return $col >= 1 && $col <= 11;
    }
}
