<?php

namespace App\Exports\Stock;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PickingPedidoFerliExport implements FromView, ShouldAutoSize, WithColumnWidths, WithEvents, WithTitle
{
    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private bool $conFoto = true;

    /** @param list<array<string, mixed>> $filas */
    public function __construct(
        private array $filas,
        private string $subtitulo = '',
        bool $conFoto = true,
    ) {
        $this->conFoto = $conFoto;
    }

    public function view(): View
    {
        $desde = (int) config('consprod.DESDE_MEDIDA');
        $hasta = (int) config('consprod.HASTA_MEDIDA');

        return view('exports.stock.picking_pedido.picking_fragola', [
            'filas' => $this->filas,
            'subtitulo' => $this->subtitulo,
            'desdeMedida' => $desde,
            'hastaMedida' => $hasta,
            'conFoto' => $this->conFoto,
        ]);
    }

    public function title(): string
    {
        return 'PICKING';
    }

    public function columnWidths(): array
    {
        $widths = [];
        $col = 'A';
        if ($this->conFoto) {
            $widths[$col] = 14;
            $col++;
        }
        $widths[$col++] = 14; // Linea
        $widths[$col++] = 12; // Art
        $widths[$col++] = 28; // Descripcion

        $desde = (int) config('consprod.DESDE_MEDIDA');
        $hasta = (int) config('consprod.HASTA_MEDIDA');
        for ($i = $desde; $i <= $hasta; $i++) {
            $widths[$col] = 5;
            $col++;
        }
        // T, QM, TT, Precio, SITUACION, NUMERO OT, deposito
        foreach ([6, 6, 6, 10, 18, 18, 12] as $w) {
            $widths[$col] = $w;
            $col++;
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        $filaCab = $this->filaCabecerasExcel;
        $filaDatos = $this->filaPrimeraDatosExcel;
        $desde = (int) config('consprod.DESDE_MEDIDA');
        $hasta = (int) config('consprod.HASTA_MEDIDA');
        $colsMedidas = ($hasta - $desde) + 1;
        $conFoto = $this->conFoto;
        // Foto? + Linea Art Desc + medidas + T QM TT Precio SITUACION NUMERO OT deposito
        $totalCols = ($conFoto ? 1 : 0) + 3 + $colsMedidas + 7;
        $colUltima = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);
        $filas = $this->filas;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($filaCab, $filaDatos, $colUltima, $conFoto, $filas) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();

                $sheet->mergeCells('A1:'.$colUltima.'1');
                $sheet->getStyle('A1')->getFont()->setName('Arial')->setBold(true)->setSize(14)->getColor()->setRGB('17202A');
                $sheet->getRowDimension(1)->setRowHeight(24);

                $rangoCab = 'A'.$filaCab.':'.$colUltima.$filaCab;
                $sheet->getStyle($rangoCab)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle($rangoCab)->getFont()
                    ->setName('Arial')->setBold(true)->setSize(11)->getColor()->setRGB('17202A');
                $sheet->getStyle($rangoCab)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->freezePane('A'.$filaDatos);

                if (! $conFoto) {
                    return;
                }

                foreach ($filas as $idx => $fila) {
                    $excelRow = $filaDatos + $idx;
                    $path = $fila['foto_path'] ?? null;
                    if (! is_string($path) || $path === '' || ! is_file($path)) {
                        continue;
                    }
                    $sheet->getRowDimension($excelRow)->setRowHeight(78);
                    try {
                        $drawing = new Drawing;
                        $drawing->setName('foto-'.$excelRow);
                        $drawing->setDescription((string) ($fila['sku'] ?? ''));
                        $drawing->setPath($path);
                        $drawing->setHeight(70);
                        $drawing->setCoordinates('A'.$excelRow);
                        $drawing->setOffsetX(4);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    } catch (\Throwable $e) {
                        // sin foto si el archivo no es imagen válida
                    }
                }
            },
        ];
    }
}
