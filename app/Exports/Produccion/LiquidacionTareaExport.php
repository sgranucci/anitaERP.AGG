<?php

namespace App\Exports\Produccion;

use App\Services\Ventas\OrdentrabajoService;
use App\Queries\Stock\ArticuloQueryInterface;
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
use Carbon\Carbon;
use App\ApiAnita;

class LiquidacionTareaExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdefecha, $hastafecha,
		$desdeempleado_id, $hastaempleado_id,
		$desdecliente_id, $hastacliente_id,
		$desdearticulo_id, $hastaarticulo_id,
		$desdelinea_id, $hastalinea_id,
		$desdetarea_id, $hastatarea_id;

	protected $dates = ['fecha'];
    private $ordentrabajoService;
	private $articuloQuery;
	
    public function __construct(
                                OrdentrabajoService $ordentrabajoservice,
								ArticuloQueryInterface $articuloquery
								)
    {
        $this->ordentrabajoService = $ordentrabajoservice;
		$this->articuloQuery = $articuloquery;
    }

	public function view(): View
	{
		// Prepara titulos de rangos
		$ret = generaRangoArticulo($this->desdearticulo_id, $this->hastaarticulo_id, $this->articuloQuery);

		$desdeArticulo = $ret['desdearticulotitulo'];
		$hastaArticulo = $ret['hastaarticulotitulo'];
		$desdeArticuloRango = $ret['desdearticulorango'];
		$hastaArticuloRango = $ret['hastaarticulorango'];
		
		// Lee informacion del listado
		$data = $this->ordentrabajoService->generaDatosRepLiquidacionTarea($this->estadoot,
			$this->desdefecha, $this->hastafecha, 
			$this->desdetarea_id, $this->hastatarea_id,
			$this->desdecliente_id, $this->hastacliente_id,
			$desdeArticuloRango, $hastaArticuloRango,
			$this->desdeempleado_id, $this->hastaempleado_id);

		return view('exports.produccion.reporteliquidaciontarea.reporteliquidaciontarea', 
					['tareas' => $data, 
					'desdeempleado_id' => $this->desdeempleado_id, 'hastaempleado_id' => $this->hastaempleado_id, 
					'desdecliente_id' => $this->desdecliente_id, 'hastacliente_id' => $this->hastacliente_id, 
					'desdearticulo_id' => $this->desdearticulo_id, 'hastaarticulo_id' => $this->hastaarticulo_id, 
					'desdetarea_id' => $this->desdetarea_id, 'hastatarea_id' => $this->hastatarea_id, 
					'desdefecha' => $this->desdefecha, 'hastafecha' => $this->hastafecha,
					'nombredesdeempleado' => '', 'nombrehastaempleado' => '',
					'estado' => $this->estadoot]);
	}

	public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
			'J' => NumberFormat::FORMAT_NUMBER,
			'K' => NumberFormat::FORMAT_NUMBER_00,
			'L' => NumberFormat::FORMAT_NUMBER_00,
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
            'J' => ['font' => ['bold' => true]],
			'L' => ['font' => ['bold' => true]],
        ];
    }

	public function columnWidths(): array
    {
        return [
            'A' => 10,
            ];
    }

	public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {

                $event->sheet->getDelegate()->freezePane('A4');

            },
        ];
    }

	public function title(): string
    {
        return 'Reporte de Liquidación de Tareas';
    }

	public function parametros($estadoot,
							$desdefecha, $hastafecha, 
							$desdecliente_id, $hastacliente_id,
							$desdetarea_id, $hastatarea_id,
							$desdeempleado_id, $hastaempleado_id,
							$desdearticulo_id, $hastaarticulo_id)
	{
		$this->estadoot = $estadoot;
		$this->desdefecha = $desdefecha;
		$this->hastafecha = $hastafecha;
		$this->desdecliente_id = $desdecliente_id;
		$this->hastacliente_id = $hastacliente_id;
		$this->desdetarea_id = $desdetarea_id;
		$this->hastatarea_id = $hastatarea_id;
		$this->desdeempleado_id = $desdeempleado_id;
		$this->hastaempleado_id = $hastaempleado_id;

		return $this;
	}
}
