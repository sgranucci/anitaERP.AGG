<?php

namespace App\Exports\Stock;

use App\Services\Stock\Articulo_MovimientoService;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Models\Stock\Linea;
use App\Models\Stock\Categoria;
use App\Models\Stock\Mventa;
use App\Models\Stock\Depmae;
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

class StockOtExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdearticulo_id, $hastaarticulo_id,
			$desdelinea_id, $hastalinea_id,
			$estado, $mventa_id, $desdelote, $hastalote, $imprimeFoto, $estadoOt, $apertura, $deposito_id;

	protected $dates = ['fecha'];
    private $articulo_movimientoService;

    public function __construct(
								ArticuloQueryInterface $articuloquery,
                                Articulo_MovimientoService $articulo_movimientoservice
								)
    {
		$this->articuloQuery = $articuloquery;
        $this->articulo_movimientoService = $articulo_movimientoservice;
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

		if ($this->desdecategoria_id == 0)
			$desdeCategoria = 'Primera';
		else
		{
			$categoria = Categoria::find($this->desdecategoria_id);
			if ($categoria)
				$desdeCategoria = $categoria->nombre;
			else	
				$desdeCategoria = '--';
		}
		
		if ($this->hastacategoria_id == 99999999)
			$hastaCategoria = 'Ultima';
		else
		{
			$categoria = Categoria::find($this->hastacategoria_id);
			if ($categoria)
				$hastaCategoria = $categoria->nombre;
			else	
				$hastaCategoria = '--';
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
		$data = $this->articulo_movimientoService->generaDatosRepStockOt($this->estado, $this->mventa_id,
				$desdeArticuloRango, $hastaArticuloRango,
				$this->desdelinea_id, $this->hastalinea_id,
				$this->desdecategoria_id, $this->hastacategoria_id,
				$this->desdelote, $this->hastalote,
				$this->estadoOt, $this->apertura, $this->deposito_id);

		if ($this->deposito_id != 0)
		{
			$deposito = Depmae::find($this->deposito_id);

			if ($deposito)
				$txtDeposito = "Deposito: ".$deposito->nombre;
		}
		else	
			$txtDeposito = "Todos los depositos";

		return view('exports.stock.reportestockot.reportestockot', 
					['data' => $data, 
					'estado' => $this->estado,
					'nombremarca' => $nombremarca,
					'desdearticulo' => $desdeArticulo, 'hastaarticulo' => $hastaArticulo, 
					'desdelinea' => $desdeLinea, 'hastalinea' => $hastaLinea, 
					'desdecategoria' => $desdeCategoria, 'hastacategoria' => $hastaCategoria,
					'desdelote' => $this->desdelote, 'hastalote' => $this->hastalote,
					'imprimefoto' => $this->imprimeFoto,
					'estadoot' => $this->estadoOt,
					'deposito' => $txtDeposito
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
				],
			6   => ['font' => ['bold' => true,
				'color' => array('rgb' => '17202A'),
				'size'  => 12,
				'name'  => 'Arial'
				],
			],
            7   => ['font' => ['bold' => true,
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
            'A' => 20,
			'G' => 4,
			'H' => 4,
			'I' => 4,
			'J' => 4,
			'K' => 4,
			'L' => 4,
			'M' => 4,
			'N' => 4,
			'O' => 4,
			'P' => 4,
			'Q' => 4,
			'R' => 4,
			'S' => 4,
			'T' => 4,
			'U' => 4,
			'V' => 4,
			'W' => 4,
			'X' => 4,
			'Y' => 4,
			'Z' => 4,
			'AA' => 4,
			'AB' => 4,
			'AC' => 4,
			'AD' => 4,
			'AE' => 4,
			'AF' => 4,
			'AG' => 4,
			'AI' => 3,
			'AK' => 4,
			'AL' => 4,
			'AM' => 4,
			'AN' => 4,
			'AO' => 4,
			'AP' => 4,
			'AQ' => 4,
			'AR' => 4,
			'AS' => 4,
			'AT' => 4,
			'AU' => 4,
			'AV' => 4,
			'AW' => 4,
			'AX' => 4,
			'AY' => 4,
			'AZ' => 4,
			'BA' => 4,
			'BB' => 4,
			'BC' => 4,
			'BD' => 4,
			'BE' => 4,
			'BF' => 4,
			'BG' => 4,
			'BI' => 3,
			'BK' => 4,
		];
    }

	public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {

                $event->sheet->getDelegate()->freezePane('A8');
				$event->sheet->getDelegate()->getStyle('A:AP')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            },
        ];
    }

	public function title(): string
    {
        return 'Reporte de Stock por OT';
    }

	public function parametros($estado, $mventa_id,
							$desdearticulo_id, $hastaarticulo_id,
							$desdelinea_id, $hastalinea_id,
							$desdecategoria_id, $hastacategoria_id,
							$desdelote, $hastalote,
							$imprimefoto, $estadoot, $apertura,
							$deposito_id)
	{
		$this->estado = $estado;
		$this->mventa_id = $mventa_id;
		$this->desdearticulo_id = $desdearticulo_id;
		$this->hastaarticulo_id = $hastaarticulo_id;
		$this->desdelinea_id = $desdelinea_id;
		$this->hastalinea_id = $hastalinea_id;
		$this->desdecategoria_id = $desdecategoria_id;
		$this->hastacategoria_id = $hastacategoria_id;
		$this->desdelote = $desdelote;
		$this->hastalote = $hastalote;
		$this->imprimeFoto = $imprimefoto;
		$this->estadoOt = $estadoot;
		$this->apertura = $apertura;
		$this->deposito_id = $deposito_id;
		
		return $this;
	}
}
