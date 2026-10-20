<?php

namespace App\Exports\Stock;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ControlInventarioItemsReporteExport implements FromView, ShouldAutoSize, WithStyles, WithTitle
{
    use Exportable;

    /**
     * @param  Collection<int, array<string, mixed>>|iterable  $filas
     * @param  array<string, mixed>  $totales
     * @param  list<string>  $avisos
     */
    public function __construct(
        private iterable $filas,
        private string $titulo,
        private string $subtitulo = '',
        private array $totales = [],
        private array $avisos = [],
    ) {}

    public function view(): View
    {
        return view('exports.stock.control_inventario_items_reporteindex', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totales' => $this->totales,
            'avisos' => $this->avisos,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            3 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Control inventario';
    }
}
