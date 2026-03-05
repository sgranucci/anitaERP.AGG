<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Exports\Stock\ListaPrecioExport;
use App\Services\Stock\PrecioService;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Models\Stock\Linea;
use App\Models\Stock\Mventa;
use App\Models\Stock\Categoria;
use PDF;

class RepListaPrecioController extends Controller
{
    protected $precioService;
    protected $articuloQuery;

    public function __construct(PrecioService $precioservice,
                                ArticuloQueryInterface $articuloquery)
    {
        $this->middleware('auth');
        
        $this->precioService = $precioservice;
        $this->articuloQuery = $articuloquery;
    }

    public function index()
    {
        $estado_enum = [
			'ACTIVAS' => 'Combinaciones activas',
			'INACTIVAS' => 'Combinaciones inactivas',
			'TODAS' => 'Todas las combinaciones'
        ];
        $nofactura_enum = [
            ['id' => '0', 'nombre' => 'Facturables'],
            ['id' => '1', 'nombre' => 'No facturables'],
            ['id' => '2', 'nombre' => 'Todos']
                 ];

        $articulo_query = $this->articuloQuery->allQueryConCombinacion(['id','sku','descripcion'], 'descripcion');
        $articulo_query->prepend((object) ['id'=>'0','descripcion'=>'Primero']);
        $articulo_query->push((object) ['id'=>'99999999','descripcion'=>'Ultimo']);
        $categoria_query = Categoria::all();
        $categoria_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
        $categoria_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
        $mventa_query = Mventa::all();
        $mventa_query->prepend((object) ['id'=>'0','nombre'=>'Todas las marcas']);

        return view('stock.replistaprecio.create', compact('estado_enum', 'articulo_query',
                                                        'mventa_query', 'categoria_query', 'nofactura_enum'
                                                        ));
    }

    public function crearReporteListaPrecio(Request $request)
    {
        switch($request->extension)
		{
		case "Genera Reporte en Excel":
			$extension = "xlsx";
			break;
		case "Genera Reporte en PDF":
			$extension = "pdf";
			break;
		case "Genera Reporte en CSV":
			$extension = "csv";
			break;
		}
		return (new ListaPrecioExport($this->articuloQuery, $this->precioService))
                                    ->parametros($request->estado, 
                                                $request->mventa_id,
                                                $request->desdearticulo_id, $request->hastaarticulo_id,
                                                $request->desdecategoria_id, $request->hastacategoria_id,
                                                $request->listasprecio,
                                                $request->nofactura)
                                    ->download('listaprecio.'.$extension);
    }
}
