<?php

namespace App\Exports\Ventas;

use App\Services\Ventas\PedidoService;
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
use App\Models\Ventas\Vendedor;
use Carbon\Carbon;
use App\ApiAnita;

class GeneralPedidoExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdefecha, $hastafecha,
		$desdevendedor_id, $hastavendedor_id,
		$desdecliente_id, $hastacliente_id,
		$desdearticulo_id, $hastaarticulo_id,
		$desdelinea_id, $hastalinea_id,
		$tipolistado, $estado, $mventa_id, $nombremventa;

	protected $dates = ['fecha'];
    private $pedidoService;

    public function __construct(
                                PedidoService $pedidoservice
								)
    {
        $this->pedidoService = $pedidoservice;
    }

	public function view(): View
	{
		// Lee informacion del listado
		$data = $this->pedidoService->generaDatosRepGeneralPedidos($this->tipolistado, $this->estado,
			$this->mventa_id,
			$this->desdefecha, $this->hastafecha, 
			$this->desdevendedor_id, $this->hastavendedor_id,
			$this->desdecliente_id, $this->hastacliente_id,
			$this->desdearticulo_id, $this->hastaarticulo_id,
			$this->desdelinea_id, $this->hastalinea_id,
			$this->desdefondo_id, $this->hastafondo_id);

		return view('exports.ventas.reportegeneralpedido.reportegeneralpedido', 
					['comprobantes' => $data, 
					'tipolistado' => $this->tipolistado, 'estado' => $this->estado,
					'marca' => $this->nombremventa,
					'desdevendedor_id' => $this->desdevendedor_id, 'hastavendedor_id' => $this->hastavendedor_id, 
					'desdecliente_id' => $this->desdecliente_id, 'hastacliente_id' => $this->hastacliente_id, 
					'desdearticulo_id' => $this->desdearticulo_id, 'hastaarticulo_id' => $this->hastaarticulo_id, 
					'desdelinea_id' => $this->desdelinea_id, 'hastalinea_id' => $this->hastalinea_id, 
					'desdefondo_id' => $this->desdefondo_id, 'hastafondo_id' => $this->hastafondo_id, 
					'desdefecha' => $this->desdefecha, 'hastafecha' => $this->hastafecha, 
					]);
	}

	public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
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
            'A' => ['font' => ['bold' => true]],
            'E' => ['font' => ['bold' => true]],
            'F' => ['font' => ['bold' => true]],
            'H' => ['font' => ['bold' => true]],
			'J' => ['font' => ['bold' => true]],
			'AN' => ['font' => ['bold' => true]],
        ];
    }

	public function columnWidths(): array
    {
        return [
            'A' => 10,
            'C' => 15,
			'M' => 5,
			'N' => 5,
			'O' => 5,
			'P' => 5,
			'Q' => 5,
			'R' => 5,
			'S' => 5,
			'T' => 5,
			'U' => 5,
			'V' => 5,
			'W' => 5,
			'X' => 5,
			'Y' => 5,
			'Z' => 5,
			'AA' => 5,
			'AB' => 5,
			'AC' => 5,
			'AD' => 5,
			'AE' => 5,
			'AF' => 5,
			'AG' => 5,
			'AH' => 5,
			'AI' => 5,
			'AJ' => 5,
			'AK' => 5,
			'AL' => 5,
			'AM' => 5,
			'AN' => 8,
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
        return 'Reporte General de Pedidos';
    }

	public function parametros($tipolistado, $estado, $mventa_id, $nombremventa,
							$desdefecha, $hastafecha, 
							$desdevendedor_id, $hastavendedor_id,
							$desdecliente_id, $hastacliente_id,
							$desdearticulo_id, $hastaarticulo_id,
							$desdelinea_id, $hastalinea_id,
							$desdefondo_id, $hastafondo_id)
	{
		$this->tipolistado = $tipolistado;
		$this->estado = $estado;
		$this->mventa_id = $mventa_id;
		$this->nombremventa = $nombremventa;
		$this->desdefecha = $desdefecha;
		$this->hastafecha = $hastafecha;
		$this->desdevendedor_id = $desdevendedor_id;
		$this->hastavendedor_id = $hastavendedor_id;
		$this->desdecliente_id = $desdecliente_id;
		$this->hastacliente_id = $hastacliente_id;
		$this->desdearticulo_id = $desdearticulo_id;
		$this->hastaarticulo_id = $hastaarticulo_id;
		$this->desdelinea_id = $desdelinea_id;
		$this->hastalinea_id = $hastalinea_id;
		$this->desdefondo_id = $desdefondo_id;
		$this->hastafondo_id = $hastafondo_id;
		
		return $this;
	}
}
