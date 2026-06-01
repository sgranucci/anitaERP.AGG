<?php

namespace App\Exports\Ventas;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GastronomiaArticulosVendidosExport implements FromView, ShouldAutoSize, WithColumnWidths, WithStyles, WithTitle
{
    use Exportable;

    private const ULTIMA_COL = 'H';

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function __construct(
        private Collection $filas,
        private array $filtros = [],
    ) {}

    public function view(): View
    {
        return view('exports.ventas.gastronomia_articulos_vendidos', [
            'filas' => $this->filas,
            'filtros' => $this->filtros,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            2 => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 36,
            'C' => 22,
            'D' => 22,
            'E' => 14,
            'F' => 14,
            'G' => 12,
            'H' => 12,
        ];
    }

    public function title(): string
    {
        return 'Artículos vendidos';
    }
}
