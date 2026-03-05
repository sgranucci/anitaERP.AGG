<?php

namespace App\Exports\Ventas;

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
use App\Services\Ventas\OrdentrabajoService;
use App\Models\Ventas\Vendedor;
use Carbon\Carbon;
use App\ApiAnita;

class OrdentrabajoExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	protected $dates = ['fecha'];
	private $ordentrabajoService;

	public function __construct(
								OrdentrabajoService $ordentrabajoservice
								)
	{
		$this->ordentrabajoService = $ordentrabajoservice;
	}

	public function view(): View
	{
		$ordentrabajo = $this->ordentrabajoService->leeOrdenestrabajoPaginando($this->busqueda, false);

		return view('exports.ventas.ordentrabajoindex', ['ordentrabajo' => $ordentrabajo]);
	}

	public function columnFormats(): array
    {
		return [
				'A' => NumberFormat::FORMAT_TEXT,
				'C' => NumberFormat::FORMAT_TEXT,
				'D' => NumberFormat::FORMAT_TEXT,
				'F' => NumberFormat::FORMAT_GENERAL,
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
				'F' => ['font' => ['bold' => true]],
			];
    }

	public function columnWidths(): array
    {
		return [
				'A' => 8,
				'C' => 40,
				'D' => 30,
				'E' => 30,
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
        return 'Reporte de Ordenes de Trabajo';
    }

	public function parametros($busqueda)
	{
		$this->busqueda = $busqueda;

		return $this;
	}
}
