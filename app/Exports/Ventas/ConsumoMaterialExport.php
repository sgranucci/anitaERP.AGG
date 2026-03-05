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

class ConsumoMaterialExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdefecha, $hastafecha,
		$desdematerialcapellada_id, $hastamaterialcapellada_id,
		$desdematerialavio_id, $hastamaterialavio_id,
		$desdecliente_id, $hastacliente_id,
		$desdearticulo_id, $hastaarticulo_id,
		$desdelinea_id, $hastalinea_id,
		$desdecolor_id, $hastacolor_id,
		$tipolistado, $estado,
		$tipocapellada, $tipoavio;

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
		$data = $this->pedidoService->generaDatosRepConsumoMateriales($this->tipolistado, $this->estado,
			$this->tipocapellada, $this->tipoavio,
			$this->desdefecha, $this->hastafecha, 
			$this->desdecliente_id, $this->hastacliente_id,
			$this->desdearticulo_id, $this->hastaarticulo_id,
			$this->desdelinea_id, $this->hastalinea_id,
			$this->desdecolor_id, $this->hastacolor_id,
			$this->desdematerialcapellada_id, $this->hastamaterialcapellada_id,
			$this->desdematerialavio_id, $this->hastamaterialavio_id);

		return view('exports.ventas.reporteconsumomaterial.reporteconsumomaterial', 
					['comprobantes' => $data, 
					'tipolistado' => $this->tipolistado, 'estado' => $this->estado,
					'tipocapellada' => $this->tipocapellada, 'tipoavio' => $this->tipoavio,
					'desdematerialcapellada_id' => $this->desdematerialcapellada_id, 
					'hastamaterialcapellada_id' => $this->hastamaterialcapellada_id, 
					'desdematerialavio_id' => $this->desdematerialavio_id, 
					'hastamaterialavio_id' => $this->hastamaterialavio_id, 
					'desdecolor_id' => $this->desdecolor_id,
					'hastacolor_id' => $this->hastacolor_id,
					'desdecliente_id' => $this->desdecliente_id, 'hastacliente_id' => $this->hastacliente_id, 
					'desdearticulo_id' => $this->desdearticulo_id, 'hastaarticulo_id' => $this->hastaarticulo_id, 
					'desdelinea_id' => $this->desdelinea_id, 'hastalinea_id' => $this->hastalinea_id, 
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
					]
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
			4   => ['font' => ['bold' => true,
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
            'I' => ['font' => ['bold' => true]],
			'AN' => ['font' => ['bold' => true]],
			'BP' => ['font' => ['bold' => true]],
        ];
    }

	public function columnWidths(): array
    {
        return [
            'A' => 10,
            'C' => 15,
			'M' => 8,
			'N' => 8,
			'O' => 8,
			'P' => 8,
			'Q' => 8,
			'R' => 8,
			'S' => 8,
			'T' => 8,
			'U' => 8,
			'V' => 8,
			'W' => 8,
			'X' => 8,
			'Y' => 8,
			'Z' => 8,
			'AA' => 8,
			'AB' => 8,
			'AC' => 8,
			'AD' => 8,
			'AE' => 8,
			'AF' => 8,
			'AG' => 8,
			'AH' => 8,
			'AI' => 8,
			'AJ' => 8,
			'AK' => 8,
			'AL' => 8,
			'AM' => 8,
			'AN' => 10,
			'AO' => 5,
			'AP' => 5,
			'AQ' => 5,
			'AR' => 5,
			'AS' => 5,
			'AT' => 5,
			'AU' => 5,
			'AV' => 5,
			'AW' => 5,
			'AX' => 5,
			'AY' => 5,
			'AZ' => 5,
			'BA' => 5,
			'BB' => 5,
			'BC' => 5,
			'BD' => 5,
			'BE' => 5,
			'BF' => 5,
			'BG' => 5,
			'BH' => 5,
			'BI' => 5,
			'BJ' => 5,
			'BK' => 5,
			'BL' => 5,
			'BM' => 5,
			'BN' => 5,
			'BO' => 5,
			'BP' => 8,
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

	public function parametros($tipolistado, $estado,
							$tipocapellada, $tipoavio,
							$desdefecha, $hastafecha, 
							$desdematerialcapellada_id, $hastamaterialcapellada_id,
							$desdematerialavio_id, $hastamaterialavio_id,
							$desdecliente_id, $hastacliente_id,
							$desdearticulo_id, $hastaarticulo_id,
							$desdelinea_id, $hastalinea_id,
							$desdecolor_id, $hastacolor_id)
	{
		$this->tipolistado = $tipolistado;
		$this->estado = $estado;
		$this->tipocapellada = $tipocapellada;
		$this->tipoavio = $tipoavio;
		$this->desdefecha = $desdefecha;
		$this->hastafecha = $hastafecha;
		$this->desdecliente_id = $desdecliente_id;
		$this->hastacliente_id = $hastacliente_id;
		$this->desdearticulo_id = $desdearticulo_id;
		$this->hastaarticulo_id = $hastaarticulo_id;
		$this->desdelinea_id = $desdelinea_id;
		$this->hastalinea_id = $hastalinea_id;
		$this->desdecolor_id = $desdecolor_id;
		$this->hastacolor_id = $hastacolor_id;
		$this->desdematerialcapellada_id = $desdematerialcapellada_id;
		$this->hastamaterialcapellada_id = $hastamaterialcapellada_id;
		$this->desdematerialavio_id = $desdematerialavio_id;
		$this->hastamaterialavio_id = $hastamaterialavio_id;
		
		return $this;
	}
}
