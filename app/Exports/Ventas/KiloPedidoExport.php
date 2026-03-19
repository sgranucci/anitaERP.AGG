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
use App\Services\Ventas\PedidoService;
use Carbon\Carbon;

class KiloPedidoExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdefecha, $hastafecha;
	private $estado, $tipolistado;
	protected $dates = ['fecha'];
	private $pedidoService;
	private $desdetransporte, $hastatransporte;

	public function __construct(
								PedidoService $pedidoservice
								)
	{
		$this->pedidoService = $pedidoservice;
	}

	public function view(): View
	{
		$fecha = strtotime($this->desdefecha);
		$desde_fecha = date('Ymd', $fecha);
		$fecha = strtotime($this->hastafecha);
		$hasta_fecha = date('Ymd', $fecha);

		$datas = $this->pedidoService->generaDatosKiloPedido($this->tipolistado, $this->estado, $desde_fecha, $hasta_fecha, 
														$this->desdetransporte, $this->hastatransporte);

		if ($this->tipolistado == "ABRE")
			return view('exports.ventas.reportepedido.reportepedidoabre', ['comprobantes' => $datas, 'transporte' => $this->desdetransporte.' al '.$this->hastatransporte, 'desdefecha' => $this->desdefecha, 'hastafecha' => $this->hastafecha, 'nombre_transporte' => '']);
		else
			return view('exports.ventas.reportepedido.reportepedidototal', ['comprobantes' => $datas, 'transporte' => $this->desdetransporte.' al '.$this->hastatransporte, 'desdefecha' => $this->desdefecha, 'hastafecha' => $this->hastafecha, 'nombre_transporte' => '']);
	}

	public function columnFormats(): array
    {
		if ($this->flDesdeIndex)
			return [
				'A' => NumberFormat::FORMAT_TEXT,
				'C' => NumberFormat::FORMAT_TEXT,
				'E' => NumberFormat::FORMAT_GENERAL,
			];
		else
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
		if ($this->flDesdeIndex)
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
			];
		else
			return [
				2   => ['font' => ['bold' => true,
									'color' => array('rgb' => '17202A'),
									'size'  => 12,
									'name'  => 'Arial'
									],
						],
				3   => ['font' => ['bold' => true,
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
				'G' => ['font' => ['bold' => true]],
				'H' => ['font' => ['bold' => true]],
				'J' => ['font' => ['bold' => true]],
				'M' => ['font' => ['bold' => true]],
			];		
    }

	public function columnWidths(): array
    {
		if ($this->flDesdeIndex)
			return [
				'A' => 8,
				'C' => 40,
				'D' => 10,
				'E' => 10,
			];
		else
			return [
				'A' => 10,
				'C' => 15,
				'D' => 10,
				'E' => 15,
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
        return 'Reporte de Pedidos';
    }

	public function rangoFecha($desdefecha, $hastafecha)
	{
		$this->desdefecha = $desdefecha;
		$this->hastafecha = $hastafecha;

		return $this;
	}

	public function asignaRangoTransporte($desdetransporte, $hastatransporte)
	{
		$this->desdetransporte = $desdetransporte;
		$this->hastatransporte = $hastatransporte;

		return $this;
	}

	public function asignaTipoListado($tipolistado, $estado)
	{
		$this->tipolistado = $tipolistado;
		$this->estado = $estado;

		return $this;
	}

	public function parametros($busqueda)
	{
		$this->busqueda = $busqueda;
		$this->flDesdeIndex = true;

		return $this;
	}
}
