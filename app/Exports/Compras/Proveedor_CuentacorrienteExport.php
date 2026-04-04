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
use App\Repositories\Compras\Proveedor_CuentacorrienteRepositoryInterface;
use Carbon\Carbon;
use App\ApiAnita;

class Proveedor_CuentacorrienteExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $proveedor_cuentacorrienteRepository;
	private $busqueda, $moneda_query, $proveedor_id, $nombreproveedor, $codigoproveedor;

	public function __construct(
								Proveedor_CuentacorrienteRepositoryInterface $proveedor_cuentacorrienteRepository
								)
	{
		$this->proveedor_cuentacorrienteRepository = $proveedor_cuentacorrienteRepository;
	}

	public function view(): View
	{
		$datas = $this->proveedor_cuentacorrienteRepository->listarCuentaCorriente($this->busqueda, $this->proveedor_id);

		return view('exports.compras.listadocuentacorrienteproveedor', ['cuentacorriente' => $datas, 'moneda_query' => $this->moneda_query,
																		'nombreproveedor' => $this->nombreproveedor, 
																		'codigoproveedor' => $this->codigoproveedor]);
	}

	public function columnFormats(): array
    {
		return [
				'A' => NumberFormat::FORMAT_TEXT,
				'G' => NumberFormat::FORMAT_TEXT,
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
				'J' => ['font' => ['bold' => true]],
			];		
    }

	public function columnWidths(): array
    {
		return [
				'A' => 8,
				'G' => 15,
				'H' => 20,
				'I' => 20,
				'J' => 20
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

	public function parametros($busqueda, $moneda_query, $proveedor_id, $nombreproveedor, $codigoproveedor)
	{
		$this->busqueda = $busqueda;
		$this->moneda_query = $moneda_query;
		$this->proveedor_id = $proveedor_id;
		$this->nombreproveedor = $nombreproveedor;
		$this->codigoproveedor = $codigoproveedor;

		return $this;
	}
}
