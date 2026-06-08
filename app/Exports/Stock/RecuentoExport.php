<?php

namespace App\Exports\Stock;

use App\Repositories\Stock\RecuentoRepositoryInterface;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RecuentoExport implements FromView, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private RecuentoRepositoryInterface $recuentoRepository;

    /** @var array<string, mixed> */
    private array $filtros = [];

    public function __construct(RecuentoRepositoryInterface $recuentoRepository)
    {
        $this->recuentoRepository = $recuentoRepository;
    }

    public function view(): View
    {
        $recuentos = $this->recuentoRepository->leeRecuentos($this->filtros, false);

        return view('exports.stock.recuentoindex', compact('recuentos'));
    }

    public function styles(Worksheet $sheet)
    {
        return [
            2 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 12,
                    'name' => 'Arial',
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A3');
            },
        ];
    }

    public function title(): string
    {
        return 'Recuentos de inventario';
    }

    /**
     * @param  array<string, mixed>|string  $filtros
     */
    public function parametros($filtros): self
    {
        $this->filtros = is_array($filtros) ? $filtros : ['busqueda' => (string) $filtros];

        return $this;
    }
}
