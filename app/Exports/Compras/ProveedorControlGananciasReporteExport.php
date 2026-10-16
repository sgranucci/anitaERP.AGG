<?php

declare(strict_types=1);

namespace App\Exports\Compras;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProveedorControlGananciasReporteExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'N';

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $stats
     */
    public function __construct(
        private array $filas,
        private string $titulo,
        private string $subtitulo = '',
        private array $stats = [],
    ) {}

    public function view(): View
    {
        $this->filaCabecerasExcel = trim($this->subtitulo) !== '' ? 3 : 2;
        if ($this->stats !== []) {
            $this->filaCabecerasExcel++;
        }
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.compras.proveedor_control_ganancias_reporteindex', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'stats' => $this->stats,
            'para_excel' => true,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'J' => NumberFormat::FORMAT_TEXT,
            'L' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaCabecerasExcel => [
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
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,  // CUIT
            'B' => 36,  // Razón social
            'C' => 24,  // Condición IVA ABM
            'D' => 20,  // Segmento
            'E' => 12,  // Estado
            'F' => 16,  // Cód. ret. actual
            'G' => 34,  // Retención actual
            'H' => 16,  // Id actividad ARCA
            'I' => 48,  // Actividad principal ARCA
            'J' => 18,  // Cód. ret. propuesto
            'K' => 34,  // Retención propuesta
            'L' => 12,  // Padrón OK
            'M' => 13,  // Discrepancia
            'N' => 50,  // Observaciones
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:'.self::COL_ULTIMA.'1');
                if (trim($this->subtitulo) !== '') {
                    $sheet->mergeCells('A2:'.self::COL_ULTIMA.'2');
                }
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14,
                        'name' => 'Arial',
                        'color' => ['rgb' => '17202A'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $ultimaFila = max($this->filaPrimeraDatosExcel, $sheet->getHighestRow());
                $rangoDatos = 'A'.$this->filaCabecerasExcel.':'.self::COL_ULTIMA.$ultimaFila;
                $sheet->getStyle($rangoDatos)->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);
                $sheet->getRowDimension($this->filaCabecerasExcel)->setRowHeight(32);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Control ganancias';
    }
}
