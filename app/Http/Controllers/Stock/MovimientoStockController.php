<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionMovimientoStock;
use App\Services\Stock\MovimientoStockService;
use App\Repositories\Ventas\TipotransaccionRepository;
use App\Repositories\Stock\LoteRepositoryInterface;
use App\Models\Stock\Depmae;
use App\Models\Stock\Articulo;
use App\Models\Stock\Mventa;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Modulo;
use DB;

class MovimientoStockController extends Controller
{
	private $movimientoStockService;
    private $tipotransaccionRepository;
    private $loteRepository;
	
    public function __construct(MovimientoStockService $movimientoStockservice,
                                LoteRepositoryInterface $loterepository,
                                TipotransaccionRepository $tipotransaccionRepository
    							)
    {
        $this->movimientoStockService = $movimientoStockservice;
        $this->tipotransaccionRepository = $tipotransaccionRepository;
        $this->loteRepository = $loterepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-movimientos-de-stock');
        
		$datas = $this->movimientoStockService->all();
		$estado_enum = $this->movimientoStockService->estadoEnum();
        return view('stock.movimientostock.index', compact('datas', 'estado_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-movimientos-de-stock');

        $this->armarTablasVista($deposito_query,
                                $mventa_query, $articulo_query, $modulo_query, 
                                $listaprecio_query, $articuloall_query, $articuloxsku_query,
                                $tipotransaccion_query, $lote_query);

        $tipotransacciondefault_id = cache()->get(generaKey('tipotransaccion'));

        return view('stock.movimientostock.crear', compact(
            'mventa_query', 'articulo_query', 'modulo_query', 'listaprecio_query', 
            'articuloall_query', 'articuloxsku_query', 
            'tipotransaccion_query', 'tipotransacciondefault_id', 'deposito_query', 'lote_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionMovimientoStock $request)
    {
		$mensaje = '';
		try
		{
            $data = $this->movimientoStockService->guardaMovimientoStock($request->all(), 'create');
			if (is_array($data))
				$mensaje = "Movimiento de stock creado con exito";
			else
				if ($data)
					$mensaje = $data;
		} catch (\Exception $e)
		{
			$mensaje = $e->getMessage();
		}

        return redirect('stock/movimientostock')->with('mensaje', 'Movimiento de Stock actualizado con exito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-movimientos-de-stock');
    	$movimientostock = $this->movimientoStockService->leeMovimientoStock($id);
		$this->armarTablasVista($deposito_query,
                            $mventa_query, $articulo_query, $modulo_query, 
                            $listaprecio_query, $articuloall_query, $articuloxsku_query, 
                            $tipotransaccion_query, $lote_query, $movimientostock);

		$tipotransacciondefault_id = cache()->get(generaKey('tipotransaccion'));
        return view('stock.movimientostock.editar', compact('movimientostock', 
			'mventa_query', 'articulo_query', 'modulo_query', 
			'listaprecio_query', 'articuloall_query', 'articuloxsku_query', 
			'tipotransaccion_query', 'tipotransacciondefault_id', 'deposito_query', 'lote_query'));            
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionMovimientoStock $request, $id)
    {
        can('actualizar-movimientos-de-stock');

		$this->movimientoStockService->guardaMovimientoStock($request->all(), 'update', $id);

        return redirect('stock/movimientostock')->with('mensaje', 'Movimiento de Stock actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-movimientos-de-stock');

        if ($request->ajax()) {
			if ($this->movimientoStockService->borraMovimientoStock($id))
        	{
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    /* Lista movimiento de stock */

    public function listarMovimientoStock($id)
    {
        
    }

   	/*
	 * Arma tablas de select para enviar a vista
	 */
	private function armarTablasVista(&$deposito_query,
                &$mventa_query, &$articulo_query, &$modulo_query, &$listaprecio_query, 
                &$articuloall_query, &$articuloxsku_query, 
                &$tipotransaccion_query, &$lote_query, $movimientostock = null)
    {
        $mventa_query = Mventa::all();
        $tipotransaccion_query = $this->tipotransaccionRepository->all(['E','S'], ['A']);
        $deposito_query = Depmae::all();
    
        $articulo_ids = Array();
        if ($movimientostock != null)	
        {
            $articulo_ids[] = $movimientostock->articulo_id;
        }
        else
            $articulo_ids[] = 0;

        $articulo_query = Articulo::select('id', 'sku', 'descripcion', 'mventa_id')
                                ->orderBy('descripcion','ASC')
                                ->whereExists(function($query) 
                                {
                                    $query->select(DB::raw(1))
                                        ->from("combinacion")
                                        ->whereRaw("combinacion.articulo_id=articulo.id and combinacion.estado = 'A'");
                                })
                                ->orWhereIn('id', $articulo_ids)
                                ->get();

        $articuloall_query = Articulo::select('id', 'sku', 'descripcion', 'mventa_id')
                                ->orderBy('descripcion','ASC')
                                ->whereExists(function($query) 
                                {
                                    $query->select(DB::raw(1))
                                        ->from("combinacion")
                                        ->whereRaw("combinacion.articulo_id=articulo.id");
                                })
                                ->get();

        $articuloxsku_query = $articulo_query->sortBy('sku');

        $modulo_query = Modulo::all();
        $listaprecio_query = Listaprecio::all();
        $lote_query = $this->loteRepository->all();
    }
}

