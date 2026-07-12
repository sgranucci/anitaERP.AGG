<?php

namespace App\Exports\Stock;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ParteUnicaBajaReporteExport implements FromView, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'H';

    /**
     * @param  Collection<int, object>|iterable  $filas
     * @param  array<string, mixed>  $totales
     */
    public function __construct(
        private iterable $filas,
        private string $titulo,
        private string $subtitulo = '',
        private array $totales = [],
    ) {}

    public function view(): View
    {
        return view('exports.stock.parte_unica_baja_reporteindex', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totales' => $this->totales,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            4 => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '85C1E9']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:'.self::COL_ULTIMA.'1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->freezePane('A5');
            },
        ];
    }

    public function title(): string
    {
        return 'Bajas NPU';
    }
}
