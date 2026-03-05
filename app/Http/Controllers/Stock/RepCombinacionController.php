<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Exports\Stock\CombinacionExport;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Models\Stock\Linea;
use App\Models\Stock\Mventa;
use PDF;

class RepCombinacionController extends Controller
{
    protected $articuloQuery;

    public function __construct(ArticuloQueryInterface $articuloquery)
    {
        $this->middleware('auth');
        
        $this->articuloQuery = $articuloquery;
    }

    public function index()
    {
        $estado_enum = [
			'ACTIVAS' => 'Combinaciones activas',
			'INACTIVAS' => 'Combinaciones inactivas',
			'TODAS' => 'Todas las combinaciones'
        ];
        $articulo_query = $this->articuloQuery->allQueryConCombinacion(['id','sku','descripcion'], 'descripcion');
        $articulo_query->prepend((object) ['id'=>'0','descripcion'=>'Primero']);
        $articulo_query->push((object) ['id'=>'99999999','descripcion'=>'Ultimo']);
        $linea_query = Linea::all();
        $linea_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
        $linea_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
        $mventa_query = Mventa::all();
        $mventa_query->prepend((object) ['id'=>'0','nombre'=>'Todas las marcas']);

        return view('stock.repcombinacion.create', compact('estado_enum', 'articulo_query', 'linea_query', 'mventa_query'));
    }

    public function crearReporteCombinacion(Request $request)
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
		return (new CombinacionExport($this->articuloQuery))
                                    ->parametros($request->estado, 
                                                $request->mventa_id,
                                                $request->desdearticulo_id, $request->hastaarticulo_id,
                                                $request->desdelinea_id, $request->hastalinea_id)
                                    ->download('combinacion.'.$extension);
    }
}
