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
use App\Queries\Presupuesto\PartidagastoQueryInterface;
use Carbon\Carbon;
use App\ApiAnita;

class PartidagastoExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $origen;
	protected $dates = ['fecha'];
	private $partidagastoQuery;

	public function __construct(
								PartidagastoQueryInterface $partidagastoquery,
								)
	{
		$this->partidagastoQuery = $partidagastoquery;
	}

	public function view(): View
	{
		$partidagastos = $this->partidagastoQuery->leePartidagasto($this->busqueda, false);

		return view('exports.presupuesto.partidagastoindex', ['partidagasto' => $partidagastos]);
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
			'A' => 10
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

	public function parametros($busqueda)
	{
		$this->busqueda = $busqueda;

		return $this;
	}
}
