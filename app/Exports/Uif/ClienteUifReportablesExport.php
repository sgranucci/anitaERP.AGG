<?php

namespace App\Exports\Uif;

use App\Support\Uif\ClienteUifInformeReportablesSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClienteUifReportablesExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'AC';

    private bool $flActivo = false;

    private string $periodo = '';

    private ?string $nombreEmpresa = null;

    /** @var Collection<int, object> */
    private Collection $premios;

    public function __construct()
    {
        $this->premios = collect();
    }

    /**
     * @param  Collection<int, object>  $premios
     */
    public function parametros(string $periodo, Collection $premios, ?string $nombreEmpresa = null): self
    {
        $this->periodo = $periodo;
        $this->premios = $premios;
        $this->nombreEmpresa = $nombreEmpresa;
        $this->flActivo = true;

        return $this;
    }

    public function view(): View
    {
        return view('exports.uif.cliente_uif_reportablesindex', [
            'titulo' => ClienteUifInformeReportablesSupport::tituloInformeExcel($this->periodo, $this->nombreEmpresa),
            'premios' => $this->premios,
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flActivo) {
            return [];
        }

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'M' => NumberFormat::FORMAT_TEXT,
            'U' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        if (! $this->flActivo) {
            return [];
        }

        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'name' => 'Arial',
                ],
            ],
            2 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 11,
                    'name' => 'Arial',
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => 'FFFF00'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        if (! $this->flActivo) {
            return [];
        }

        return [
            'A' => 8,
            'B' => 28,
            'C' => 16,
            'D' => 14,
            'E' => 14,
            'F' => 12,
            'G' => 20,
            'H' => 16,
            'I' => 12,
            'J' => 12,
            'K' => 24,
            'L' => 22,
            'M' => 12,
            'N' => 14,
            'O' => 18,
            'P' => 10,
            'Q' => 14,
            'R' => 14,
            'S' => 14,
            'T' => 16,
            'U' => 14,
            'V' => 10,
            'W' => 22,
            'X' => 14,
            'Y' => 14,
            'Z' => 10,
            'AA' => 18,
            'AB' => 10,
            'AC' => 10,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (! $this->flActivo) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:'.self::COL_ULTIMA.'1');
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getStyle('A1:'.self::COL_ULTIMA.'1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'name' => 'Arial',
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->freezePane('A3');
            },
        ];
    }

    public function title(): string
    {
        return 'Reportables UIF';
    }
}
