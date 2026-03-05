<?php

namespace App\Exports\Contable;

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
use App\Queries\Contable\AsientoQueryInterface;
use Carbon\Carbon;
use App\ApiAnita;

class AsientoExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $origen;
	protected $dates = ['fecha'];
	private $asientoQuery;

	public function __construct(
								AsientoQueryInterface $asientoquery
								)
	{
		$this->asientoQuery = $asientoquery;
	}

	public function view(): View
	{
		$asientos = $this->asientoQuery->leeAsiento($this->busqueda, false);

		return view('exports.contable.asientoindex', ['asientos' => $asientos]);
	}

	public function columnFormats(): array
    {
		return [
			'A' => NumberFormat::FORMAT_TEXT,
			'B' => NumberFormat::FORMAT_TEXT,
			'G' => '0.00',
			'K' => '0.00',
			'L' => '0.00',
			'N' => '0.0000',
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
			'A' => 8,
			'C' => 10,
			'D' => 10,
			'E' => 10,
			'F' => 20,
			'N' => 15,
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
        return 'Reporte de Asientos';
    }

	public function parametros($busqueda)
	{
		$this->busqueda = $busqueda;

		return $this;
	}
}
