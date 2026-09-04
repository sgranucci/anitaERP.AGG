<?php

namespace App\Exports\Uif;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClienteUifMatrizRiesgoExplicacionExport implements FromView, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'E';

    /** @var array<string, mixed> */
    private array $reporte = [];

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  array<string, mixed>  $reporte
     */
    public function parametros(array $reporte): self
    {
        $this->reporte = $reporte;
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion([]);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;

        return $this;
    }

    public function view(): View
    {
        return view('exports.uif.cliente_uif_matriz_riesgo_explicacionindex', [
            'reporte' => $this->reporte,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'esExcel' => true,
        ]);
    }

    public function title(): string
    {
        return 'Matriz riesgo';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 36,
            'B' => 28,
            'C' => 12,
            'D' => 12,
            'E' => 14,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaTituloExcel => [
                'font' => [
                    'bold' => true,
                    'size' => 16,
                    'name' => 'Arial',
                    'color' => ['rgb' => '17202A'],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 5;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_string($ruta) || $ruta === '' || ! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 120;
                    }
                }

                $sheet->mergeCells('A'.$this->filaTituloExcel.':'.self::COL_ULTIMA.$this->filaTituloExcel);
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

                // Pintar theads (#85C1E9) detectando filas con "Factor" en col A
                $highestRow = (int) $sheet->getHighestRow();
                for ($row = 1; $row <= $highestRow; $row++) {
                    $val = trim((string) $sheet->getCell('A'.$row)->getValue());
                    if ($val === 'Factor') {
                        $sheet->getStyle('A'.$row.':'.self::COL_ULTIMA.$row)->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'name' => 'Arial',
                                'size' => 11,
                                'color' => ['rgb' => '17202A'],
                            ],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => '85C1E9'],
                            ],
                        ]);
                    }
                }

                $sheet->freezePane('A'.($this->filaTituloExcel + 3));
            },
        ];
    }
}
