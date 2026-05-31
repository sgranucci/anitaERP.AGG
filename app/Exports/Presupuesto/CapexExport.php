<?php

namespace App\Exports\Presupuesto;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Queries\Presupuesto\CapexQueryInterface;
use Carbon\Carbon;
use App\ApiAnita;

class CapexExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $origen;
	protected $dates = ['fecha'];
	private $capexQuery;
	private $filtros;

	public function __construct(
								CapexQueryInterface $capexquery,
								)
	{
		$this->capexQuery = $capexquery;
	}

	public function view(): View
	{
		$capexs = $this->capexQuery->leeCapex($this->filtros, false);

		return view('exports.presupuesto.capexindex', ['capex' => $capexs]);
	}

	public function columnFormats(): array
    {
		return [
		];
    }

	public function map($row): array
    {
        return [
        ];
    }

    public function styles(Worksheet $sheet)
    {
		return [
			2   => ['font' => ['bold' => true,
								'color' => array('rgb' => '17202A'),
								'size'  => 12,
								'name'  => 'Arial'
								],
					'fill' => [
								'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
								'color' => array('rgb' => '85C1E9'),
					]
					],
			'B' => ['font' => ['bold' => true]],
			'C' => ['font' => ['bold' => true]],
			'E' => ['font' => ['bold' => true]],
			'F' => ['font' => ['bold' => true]],
			'K' => ['font' => ['bold' => true]],
			'L' => ['font' => ['bold' => true]],
		];
    }

	public function columnWidths(): array
    {
		return [
		];
    }

	public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {

                $event->sheet->getDelegate()->freezePane('A3');

            },
        ];
    }

	public function title(): string
    {
        return 'Reporte de Ordenes de Venta';
    }

	public function parametros($filtros)
	{
		$this->filtros = $filtros;

		return $this;
	}
}
