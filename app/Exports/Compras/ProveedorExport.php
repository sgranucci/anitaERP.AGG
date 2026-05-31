<?php

namespace App\Exports\Compras;

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
use App\Repositories\Compras\ProveedorRepositoryInterface;
use Carbon\Carbon;
use App\ApiAnita;

class ProveedorExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $proveedorRepository;
	private $filtros;

	public function __construct(
								ProveedorRepositoryInterface $proveedorRepository
								)
	{
		$this->proveedorRepository = $proveedorRepository;
	}

	public function view(): View
	{
		$datas = $this->proveedorRepository->leeProveedor($this->filtros, false);

		return view('exports.compras.listadoproveedor', ['proveedores' => $datas]);
	}

	public function columnFormats(): array
    {
		return [
				'A' => NumberFormat::FORMAT_TEXT,
				'H' => NumberFormat::FORMAT_TEXT,
				'I' => NumberFormat::FORMAT_TEXT,
				'J' => NumberFormat::FORMAT_TEXT,
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
				'G' => ['font' => ['bold' => true]],
			];		
    }

	public function columnWidths(): array
    {
		return [
				'A' => 15,
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
        return 'Reporte de Proveedores';
    }

	public function parametros($filtros)
	{
		$this->filtros = $filtros;

		return $this;
	}
}
