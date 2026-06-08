<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionMovimientoStock;
use App\Services\Stock\MovimientoStockService;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Stock\DepmaeRepositoryInterface;
use App\Repositories\Stock\Tipotransaccion_StockRepository;
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
    private $tipotransaccionStockRepository;
    private $loteRepository;
    private $depmaeRepository;
    private $empresaRepository;
	
    public function __construct(MovimientoStockService $movimientoStockservice,
                                LoteRepositoryInterface $loterepository,
                                Tipotransaccion_StockRepository $tipotransaccionStockRepository,
                                DepmaeRepositoryInterface $depmaerepository,
                                EmpresaRepositoryInterface $empresarepository,
    							)
    {
        $this->movimientoStockService = $movimientoStockservice;
        $this->tipotransaccionStockRepository = $tipotransaccionStockRepository;
        $this->loteRepository = $loterepository;
        $this->depmaeRepository = $depmaerepository;
        $this->empresaRepository = $empresarepository;
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

        $tipotransacciondefault_id = $this->resolverTipotransaccionStockDefaultId();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = old('empresa_id', $empresa_query->first()->id ?? null);

        return view('stock.movimientostock.crear', compact(
            'mventa_query', 'articulo_query', 'modulo_query', 'listaprecio_query', 
            'articuloall_query', 'articuloxsku_query', 
            'tipotransaccion_query', 'tipotransacciondefault_id', 'deposito_query', 'lote_query',
            'empresa_query', 'empresa_id'));
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

		$tipotransacciondefault_id = $this->resolverTipotransaccionStockDefaultId();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $depositoActualId = (int) ($movimientostock->articulos_movimiento[0]->deposito_id ?? 0);
        $empresa_id = old(
            'empresa_id',
            $depositoActualId > 0
                ? (Depmae::query()->whereKey($depositoActualId)->value('empresa_id') ?? $empresa_query->first()->id)
                : ($empresa_query->first()->id ?? null)
        );

        return view('stock.movimientostock.editar', compact('movimientostock', 
			'mventa_query', 'articulo_query', 'modulo_query', 
			'listaprecio_query', 'articuloall_query', 'articuloxsku_query', 
			'tipotransaccion_query', 'tipotransacciondefault_id', 'deposito_query', 'lote_query',
            'empresa_query', 'empresa_id'));            
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
        $tipotransaccion_query = $this->tipotransaccionStockRepository->all(['E','S'], ['A']);
        $deposito_query = $this->depmaeRepository->allFiltrado();
    
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

    private function resolverTipotransaccionStockDefaultId(): ?int
    {
        $cached = cache()->get(generaKey('tipotransaccion'));
        if ($cached === null || $cached === '') {
            return null;
        }

        $resolved = $this->tipotransaccionStockRepository->resolveIdFromLegacy((int) $cached);

        return $resolved > 0 ? $resolved : null;
    }
}

