<?php

namespace App\Exports\Stock;

use App\Services\Stock\PrecioService;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Models\Stock\Linea;
use App\Models\Stock\Categoria;
use App\Models\Stock\Mventa;
use App\Models\Stock\Listaprecio;
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

class ListaPrecioExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdearticulo_id, $hastaarticulo_id,
			$desdecategoria_id, $hastacategoria_id,
			$estado, $mventa_id, $listasprecio, $nofactura;

	protected $dates = ['fecha'];
    private $precioService;

    public function __construct(
								ArticuloQueryInterface $articuloquery,
                                PrecioService $precioservice
								)
    {
		$this->articuloQuery = $articuloquery;
        $this->precioService = $precioservice;
    }

	public function view(): View
	{
		// Prepara titulos de rangos
		$ret = generaRangoArticulo($this->desdearticulo_id, $this->hastaarticulo_id, $this->articuloQuery);
		
		$desdeArticulo = $ret['desdearticulotitulo'];
		$hastaArticulo = $ret['hastaarticulotitulo'];
		$desdeArticuloRango = $ret['desdearticulorango'];
		$hastaArticuloRango = $ret['hastaarticulorango'];
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
		$data = $this->precioService->generaDatosRepListaPrecio($this->estado, $this->mventa_id,
				$this->desdearticulo_id, $this->hastaarticulo_id,
				$this->desdecategoria_id, $this->hastacategoria_id,
				$this->listasprecio, $this->nofactura);

		// Genera listas de precio
		$listas = explode(',', $this->listasprecio);
		$listaprecio = Listaprecio::select('id', 'nombre')->get();

		$listasPrecio = [];
		foreach($listaprecio as $lista)
		{
			for ($i = 0; $i < count($listas); $i++)
			{
				if ($lista->id == $listas[$i])
				{
					$listasPrecio[] = ['id' => $lista->id,
									'nombre' => $lista->nombre];
				}
			}
		}

		$txtNoFactura = "";
		switch($this->nofactura)
		{
			case 0:
				$txtNoFactura = "Articulos facturables";
				break;
			case 1:
				$txtNoFactura = "Articulos no facturables";
				break;
			case 2:
				$txtNoFactura = "Articulos facturables y no facturables";
		}

		return view('exports.stock.reportelistaprecio.reportelistaprecio', 
					['data' => $data, 
					'estado' => $this->estado,
					'nombremarca' => $nombremarca,
					'desdearticulo' => $desdeArticulo, 'hastaarticulo' => $hastaArticulo, 
					'desdecategoria' => $desdeCategoria, 'hastacategoria' => $hastaCategoria,
					'listasprecio' => $listasPrecio,
					'nofactura' => $txtNoFactura
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
            'A' => ['font' => ['bold' => true]],
            'B' => ['font' => ['bold' => true]],
        ];
    }

	public function columnWidths(): array
    {
        return [
            'A' => 20,

		];
    }

	public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {

                $event->sheet->getDelegate()->freezePane('A6');
				$event->sheet->getDelegate()->getStyle('A:AP')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            },
        ];
    }

	public function title(): string
    {
        return 'Reporte de Listas de Precios';
    }

	public function parametros($estado, $mventa_id,
							$desdearticulo_id, $hastaarticulo_id,
							$desdecategoria_id, $hastacategoria_id,
							$listasprecio, $nofactura)
	{
		$this->estado = $estado;
		$this->mventa_id = $mventa_id;
		$this->desdearticulo_id = $desdearticulo_id;
		$this->hastaarticulo_id = $hastaarticulo_id;
		$this->desdecategoria_id = $desdecategoria_id;
		$this->hastacategoria_id = $hastacategoria_id;
		$this->listasprecio = $listasprecio;
		$this->nofactura = $nofactura;
		
		return $this;
	}
}
