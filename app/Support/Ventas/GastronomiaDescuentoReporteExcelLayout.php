<?php

namespace App\Support\Ventas;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class GastronomiaDescuentoReporteExcelLayout
{
    public function __construct(
        public readonly bool $hayFilaLogos,
        public readonly int $filasMetaEncabezado,
        public readonly int $filasAntesThead = 0,
        public readonly int $filasThead = 1,
    ) {}

    public function filaInicioMeta(): int
    {
        return ($this->hayFilaLogos ? 1 : 0) + 1;
    }

    public function filaCabecerasExcel(): int
    {
        return ($this->hayFilaLogos ? 1 : 0)
            + $this->filasMetaEncabezado
            + $this->filasAntesThead
            + 1;
    }

    public function filaPrimeraDatosExcel(): int
    {
        return $this->filaCabecerasExcel() + $this->filasThead;
    }

    public function filaFinMeta(): int
    {
        return $this->filaInicioMeta() + $this->filasMetaEncabezado - 1;
    }

    /**
     * @param  list<string>  $rutasLogos
     */
    public function aplicarLogos(Worksheet $sheet, array $rutasLogos): void
    {
        if (! $this->hayFilaLogos || $rutasLogos === []) {
            return;
        }

        $sheet->getRowDimension(1)->setRowHeight(54);
        foreach ($rutasLogos as $idx => $ruta) {
            if (! is_string($ruta) || ! is_readable($ruta)) {
                continue;
            }

            $drawing = new Drawing;
            $drawing->setName('Logo');
            $drawing->setDescription('Logo empresa');
            $drawing->setPath($ruta);
            $drawing->setResizeProportional(true);
            $drawing->setHeight(46);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(6 + $idx * 160);
            $drawing->setOffsetY(4);
            $drawing->setWorksheet($sheet);
        }
    }

    public function aplicarMetaEncabezado(Worksheet $sheet, string $colUltima, int $filaSubtitulo = 0): void
    {
        $filaInicio = $this->filaInicioMeta();
        $filaFin = $this->filaFinMeta();

        for ($fila = $filaInicio; $fila <= $filaFin; $fila++) {
            $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
        }

        $sheet->getRowDimension($filaInicio)->setRowHeight(28);
        $sheet->getStyle('A'.$filaInicio.':'.$colUltima.$filaInicio)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'name' => 'Arial',
                'color' => ['rgb' => '17202A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        for ($fila = $filaInicio + 1; $fila <= $filaFin; $fila++) {
            $altura = ($filaSubtitulo > 0 && $fila === $filaSubtitulo) ? 42 : 20;
            $sheet->getRowDimension($fila)->setRowHeight($altura);
            $sheet->getStyle('A'.$fila.':'.$colUltima.$fila)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 10,
                    'name' => 'Arial',
                    'color' => ['rgb' => '444444'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ]);
        }
    }

    public function aplicarEstiloThead(Worksheet $sheet, string $colUltima): void
    {
        $filaCab = $this->filaCabecerasExcel();
        $filaFinThead = $filaCab + $this->filasThead - 1;

        for ($fila = $filaCab; $fila <= $filaFinThead; $fila++) {
            $sheet->getStyle('A'.$fila.':'.$colUltima.$fila)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 11,
                    'name' => 'Arial',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }
    }

    public function congelarDebajoThead(Worksheet $sheet): void
    {
        $sheet->freezePane('A'.$this->filaPrimeraDatosExcel());
    }

    /** Congela solo filas meta (logo + título reporte); el detalle por bloque/cliente scrollea. */
    public function congelarDebajoMeta(Worksheet $sheet): void
    {
        $sheet->freezePane('A'.($this->filaFinMeta() + 1));
    }

    /**
     * Aplica estilo thead (#85C1E9) en cada fila de cabecera de columnas del listado.
     */
    public function aplicarEstiloTheadsColumnas(Worksheet $sheet, string $colUltima, int $filaDesde = 0): void
    {
        if ($filaDesde <= 0) {
            $filaDesde = $this->filaFinMeta() + 1;
        }

        $highest = (int) $sheet->getHighestRow();
        for ($fila = $filaDesde; $fila <= $highest; $fila++) {
            $colA = trim((string) $sheet->getCell('A'.$fila)->getValue());
            $colB = trim((string) $sheet->getCell('B'.$fila)->getValue());
            if ($colA !== 'Artículo' || $colB !== 'Descripción') {
                continue;
            }

            $filaFinThead = $fila + $this->filasThead - 1;
            for ($f = $fila; $f <= $filaFinThead; $f++) {
                $sheet->getStyle('A'.$f.':'.$colUltima.$f)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                        'size' => 11,
                        'name' => 'Arial',
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
            }
        }
    }

    public static function contarFilasMeta(string $subtitulo, bool $conResumenTotales, bool $conContadorBloques): int
    {
        $filas = 2;

        if (trim($subtitulo) !== '') {
            $filas++;
        }

        if ($conResumenTotales) {
            $filas++;
        }

        if ($conContadorBloques) {
            $filas++;
        }

        return $filas;
    }
}
