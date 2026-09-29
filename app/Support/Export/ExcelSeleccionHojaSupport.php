<?php

namespace App\Support\Export;

use Maatwebsite\Excel\Events\BeforeWriting;
use Maatwebsite\Excel\Writer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel rechaza copiar la hoja («Esta acción no funcionará en selecciones
 * múltiples») cuando el panel congelado queda apuntando a otra celda.
 * PhpSpreadsheet deja la activa en A1 y marca el panel de abajo: al abrir,
 * la planilla ya tiene dos selecciones.
 */
final class ExcelSeleccionHojaSupport
{
    public static function registrar(): void
    {
        static $registrado = false;
        if ($registrado) {
            return;
        }
        $registrado = true;

        Writer::listen(BeforeWriting::class, static function (BeforeWriting $event): void {
            $libro = $event->getWriter()->getDelegate();
            if (! $libro instanceof Spreadsheet) {
                return;
            }
            foreach ($libro->getAllSheets() as $hoja) {
                self::alinearSeleccionConPanelCongelado($hoja);
            }
        });
    }

    public static function alinearSeleccionConPanelCongelado(Worksheet $hoja): void
    {
        $freeze = $hoja->getFreezePane();
        if (! is_string($freeze) || $freeze === '') {
            return;
        }

        [$colFreeze, $filaFreeze] = Coordinate::indexesFromString($freeze);
        $seleccion = trim((string) $hoja->getSelectedCells());
        if ($seleccion === '' || ! self::rangoCaeEnPanelDescongelado($seleccion, $colFreeze, $filaFreeze)) {
            $hoja->setSelectedCells($freeze);
        }
    }

    private static function rangoCaeEnPanelDescongelado(string $seleccion, int $colFreeze, int $filaFreeze): bool
    {
        if (str_contains($seleccion, ' ')) {
            return false;
        }

        $bloques = Coordinate::splitRange($seleccion);
        if (count($bloques) !== 1 || ! isset($bloques[0][0])) {
            return false;
        }

        foreach ($bloques[0] as $esquina) {
            [$col, $fila] = Coordinate::indexesFromString((string) $esquina);
            if ($col < $colFreeze || $fila < $filaFreeze) {
                return false;
            }
        }

        return true;
    }
}
