<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Exports\Stock\StockOtExport;
use App\Services\Stock\Articulo_MovimientoService;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Models\Stock\Linea;
use App\Models\Stock\Mventa;
use App\Models\Stock\Categoria;
use App\Models\Stock\Depmae;
use PDF;

class RepStockOtController extends Controller
{
    protected $articulo_movimientoService;
    protected $articuloQuery;

    public function __construct(Articulo_MovimientoService $articulo_movimientoservice,
                                ArticuloQueryInterface $articuloquery)
    {
        $this->middleware('auth');
        
        $this->articulo_movimientoService = $articulo_movimientoservice;
        $this->articuloQuery = $articuloquery;
    }

    public function index()
    {
        $estado_enum = [
			'ACTIVAS' => 'Combinaciones activas',
			'INACTIVAS' => 'Combinaciones inactivas',
			'TODAS' => 'Todas las combinaciones'
        ];
        $foto_enum = [
            'SIN_FOTO' => 'Sin fotos',
			'CON_FOTO' => 'Con fotos',
        ];
        $apertura_enum = [
            'TOTALIZADO' => 'Totalizado',
			'MOVIMIENTOS' => 'Movimientos',
        ];
        $estadoOt_enum = [
            'TODAS' => 'Todas las OT',
			'ENTREGA' => 'Entrega inmediata',
            'PRODUCCION' => 'En producción',
        ];
        $articulo_query = $this->articuloQuery->allQueryConCombinacion(['id','sku','descripcion'], 'descripcion');
        $articulo_query->prepend((object) ['id'=>'0','descripcion'=>'Primero']);
        $articulo_query->push((object) ['id'=>'99999999','descripcion'=>'Ultimo']);
        $linea_query = Linea::all();
        $linea_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
        $linea_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
        $categoria_query = Categoria::all();
        $categoria_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
        $categoria_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
        $mventa_query = Mventa::all();
        $mventa_query->prepend((object) ['id'=>'0','nombre'=>'Todas las marcas']);
        $deposito_query = Depmae::all();
        $deposito_query->prepend((object) ['id'=>'0','nombre'=>'Todos los depositos']);

        return view('stock.repstockot.create', compact('estado_enum', 'articulo_query', 'linea_query', 
                                                        'mventa_query', 'categoria_query', 'foto_enum', 
                                                        'apertura_enum', 'estadoOt_enum', 'deposito_query'));
    }

    public function crearReporteStockOt(Request $request)
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
		return (new StockOtExport($this->articuloQuery, $this->articulo_movimientoService))
                                    ->parametros($request->estado, 
                                                $request->mventa_id,
                                                $request->desdearticulo_id, $request->hastaarticulo_id,
                                                $request->desdelinea_id, $request->hastalinea_id,
                                                $request->desdecategoria_id, $request->hastacategoria_id,
                                                $request->desdelote, $request->hastalote,
                                                $request->imprimefoto, $request->estadoOt, $request->apertura,
                                                $request->deposito_id)
                                    ->download('stockot.'.$extension);
    }
}
