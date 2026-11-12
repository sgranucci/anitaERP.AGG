<?php

namespace App\Support\Export;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * FromView a menudo deja importes como string: Excel no los suma.
 * Fuerza TYPE_NUMERIC + máscara en columnas/rangos de datos.
 */
final class ExcelNumericCellsSupport
{
    /**
     * @param  list<string>  $columnas  Letras de columna (p. ej. ['D','E','F'])
     */
    public static function forzarColumnas(
        Worksheet $sheet,
        array $columnas,
        int $filaDesde,
        ?int $filaHasta = null,
        string $formato = '#,##0.00',
    ): void {
        $filaHasta ??= (int) $sheet->getHighestRow();
        if ($filaHasta < $filaDesde || $columnas === []) {
            return;
        }

        foreach ($columnas as $col) {
            $col = strtoupper(trim($col));
            if ($col === '') {
                continue;
            }
            $rango = $col.$filaDesde.':'.$col.$filaHasta;
            $sheet->getStyle($rango)->getNumberFormat()->setFormatCode($formato);
            $sheet->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            for ($r = $filaDesde; $r <= $filaHasta; $r++) {
                self::forzarCelda($sheet, $col.$r);
            }
        }
    }

    /**
     * Todas las columnas desde $colDesdeIdx (1-based) hasta la última usada,
     * excepto las listadas en $excluir (letras).
     *
     * @param  list<string>  $excluir
     */
    public static function forzarRangoDatos(
        Worksheet $sheet,
        int $filaDesde,
        int $colDesdeIdx = 1,
        array $excluir = [],
        string $formato = '#,##0.00',
        ?int $filaHasta = null,
    ): void {
        $filaHasta ??= (int) $sheet->getHighestRow();
        if ($filaHasta < $filaDesde) {
            return;
        }

        $excluirMap = [];
        foreach ($excluir as $letra) {
            $excluirMap[strtoupper(trim((string) $letra))] = true;
        }

        $ultimaCol = Coordinate::columnIndexFromString($sheet->getHighestColumn($filaDesde));
        for ($idx = $colDesdeIdx; $idx <= $ultimaCol; $idx++) {
            $letra = Coordinate::stringFromColumnIndex($idx);
            if (isset($excluirMap[$letra])) {
                continue;
            }
            self::forzarColumnas($sheet, [$letra], $filaDesde, $filaHasta, $formato);
        }
    }

    public static function forzarCelda(Worksheet $sheet, string $coord): void
    {
        $cell = $sheet->getCell($coord);
        $raw = $cell->getValue();
        if ($raw === null || $raw === '') {
            return;
        }
        if (is_numeric($raw)) {
            $cell->setValueExplicit((float) $raw, DataType::TYPE_NUMERIC);

            return;
        }
        if (! is_string($raw)) {
            return;
        }
        $limpio = trim(str_replace(['%', ' '], '', $raw));
        // Texto AR 1.234,56 o intl 1,234.56 → número
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $limpio)) {
            $limpio = str_replace('.', '', $limpio);
            $limpio = str_replace(',', '.', $limpio);
        } elseif (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $limpio)) {
            $limpio = str_replace(',', '', $limpio);
        } elseif (str_contains($limpio, ',') && ! str_contains($limpio, '.')) {
            $limpio = str_replace(',', '.', $limpio);
        }
        if (is_numeric($limpio)) {
            $cell->setValueExplicit((float) $limpio, DataType::TYPE_NUMERIC);
            if ($cell->getStyle()->getNumberFormat()->getFormatCode() === NumberFormat::FORMAT_GENERAL) {
                $cell->getStyle()->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }
    }
}
