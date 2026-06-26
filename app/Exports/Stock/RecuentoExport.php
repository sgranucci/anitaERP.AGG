<?php

namespace App\Exports\Stock;

use App\Repositories\Stock\RecuentoRepositoryInterface;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RecuentoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
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

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'I' => NumberFormat::FORMAT_NUMBER,
        ];
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
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A3');

                $highestRow = $sheet->getHighestRow();
                if ($highestRow >= 2) {
                    $sheet->getStyle('A2:I'.$highestRow)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                        ->setVertical(Alignment::VERTICAL_TOP);
                }
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
