<?php

namespace App\Exports\Stock;

use App\Queries\Stock\ArticuloQueryInterface;
use App\Models\Stock\Linea;
use App\Models\Stock\Mventa;
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

class CombinacionExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdearticulo_id, $hastaarticulo_id,
			$desdelinea_id, $hastalinea_id,
			$estado, $mventa_id;

	protected $dates = ['fecha'];
    private $articuloQuery;

    public function __construct(
                                ArticuloQueryInterface $articuloquery
								)
    {
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
		
		if ($this->desdelinea_id == 0)
			$desdeLinea = 'Primera';
		else
		{
			$linea = Linea::find($this->desdelinea_id);
			if ($linea)
				$desdeLinea = $linea->nombre;
			else	
				$desdeLinea = '--';
		}
		
		if ($this->hastalinea_id == 99999999)
			$hastaLinea = 'Ultima';
		else
		{
			$Linea = Linea::find($this->hastalinea_id);
			if ($Linea)
				$hastaLinea = $linea->nombre;
			else	
				$hastaLinea = '--';
		}

		$nombremarca = 'Todas las marcas';
		if ($this->mventa_id != 0)
		{
			$marca = Mventa::find($this->mventa_id);
			if ($marca)
				$nombremarca = $marca->nombre;
			else	
				$nombremarca = '--';
		}

		// Lee informacion del listado
		$data = $this->articuloQuery->generaDatosRepCombinacion($this->estado, $this->mventa_id,
				$desdeArticuloRango, $hastaArticuloRango,
				$this->desdelinea_id, $this->hastalinea_id);
	
		return view('exports.stock.reportecombinacion.reportecombinacion', 
					['data' => $data, 
					'estado' => $this->estado,
					'nombremarca' => $nombremarca,
					'desdearticulo' => $desdeArticulo, 'hastaarticulo' => $hastaArticulo, 
					'desdelinea' => $desdeLinea, 'hastalinea' => $hastaLinea, 
					]);
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
					],
			3   => ['font' => ['bold' => true,
						'color' => array('rgb' => '17202A'),
						'size'  => 12,
						'name'  => 'Arial'
						],
					],
			4   => ['font' => ['bold' => true,
						'color' => array('rgb' => '17202A'),
						'size'  => 12,
						'name'  => 'Arial'
						],
					],
            5   => ['font' => ['bold' => true,
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
            'D' => ['font' => ['bold' => true]],
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

                $event->sheet->getDelegate()->freezePane('A6');

            },
        ];
    }

	public function title(): string
    {
        return 'Reporte de Combinaciones';
    }

	public function parametros($estado, $mventa_id,
							$desdearticulo_id, $hastaarticulo_id,
							$desdelinea_id, $hastalinea_id)
	{
		$this->estado = $estado;
		$this->mventa_id = $mventa_id;
		$this->desdearticulo_id = $desdearticulo_id;
		$this->hastaarticulo_id = $hastaarticulo_id;
		$this->desdelinea_id = $desdelinea_id;
		$this->hastalinea_id = $hastalinea_id;
		
		return $this;
	}
}
