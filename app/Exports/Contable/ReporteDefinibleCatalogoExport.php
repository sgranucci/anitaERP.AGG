<?php

namespace App\Exports\Contable;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteDefinibleCatalogoExport implements FromView, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    private Collection $filas;

    public function __construct()
    {
        $this->filas = collect();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  Collection|iterable  $filas
     */
    public function parametros(array $filtros, $filas): self
    {
        $this->filtros = $filtros;
        $this->filas = collect($filas);

        return $this;
    }

    public function view(): View
    {
        return view('exports.contable.reporte_definible_catalogoindex', [
            'filas' => $this->filas,
            'filtros' => $this->filtros,
        ]);
    }

    public function title(): string
    {
        return 'Reportes definibles';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 36,
            'C' => 28,
            'D' => 16,
            'E' => 12,
            'F' => 10,
            'G' => 10,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A2:G2')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '85C1E9'],
            ],
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '17202A'],
            ],
        ]);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A3');
            },
        ];
    }
}
