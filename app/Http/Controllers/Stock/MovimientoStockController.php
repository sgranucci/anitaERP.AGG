<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\MovimientoStockListadoExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMovimientoStock;
use App\Services\Stock\MovimientoStockAsientoService;
use App\Services\Stock\MovimientoStockService;
use App\Services\Stock\TransferenciaMercaderiaService;
use App\Models\Contable\BienUso;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use App\Repositories\Stock\DepmaeRepositoryInterface;
use App\Repositories\Stock\MovimientoStockRepositoryInterface;
use App\Repositories\Stock\Tipotransaccion_StockRepository;
use App\Repositories\Stock\LoteRepositoryInterface;
use App\Support\Stock\MovimientoStockFormulaConversionSupport;
use App\Support\Stock\MovimientoStockListadoFiltros;
use App\Support\Stock\UsuarioDepositoAutorizado;
use App\Models\Stock\Depmae;
use App\Models\Stock\Articulo;
use App\Models\Stock\MovimientoStock;
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
    private $movimientoStockRepository;
    private $centrocostoRepository;
    private $asientoService;
    private TransferenciaMercaderiaService $transferenciaMercaderiaService;
    private Articulo_Saldo_DepositoRepositoryInterface $saldoDepositoRepository;
	
    public function __construct(
        MovimientoStockService $movimientoStockservice,
        LoteRepositoryInterface $loterepository,
        Tipotransaccion_StockRepository $tipotransaccionStockRepository,
        DepmaeRepositoryInterface $depmaerepository,
        EmpresaRepositoryInterface $empresarepository,
        MovimientoStockRepositoryInterface $movimientostockrepository,
        CentrocostoRepositoryInterface $centrocostorepository,
        MovimientoStockAsientoService $asientoService,
        TransferenciaMercaderiaService $transferenciaMercaderiaService,
        Articulo_Saldo_DepositoRepositoryInterface $saldoDepositoRepository,
    ) {
        $this->movimientoStockService = $movimientoStockservice;
        $this->tipotransaccionStockRepository = $tipotransaccionStockRepository;
        $this->loteRepository = $loterepository;
        $this->depmaeRepository = $depmaerepository;
        $this->empresaRepository = $empresarepository;
        $this->movimientoStockRepository = $movimientostockrepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->asientoService = $asientoService;
        $this->transferenciaMercaderiaService = $transferenciaMercaderiaService;
        $this->saldoDepositoRepository = $saldoDepositoRepository;
    }

    public function index(Request $request)
    {
        can('listar-movimientos-de-stock');

        $filtros = MovimientoStockListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->movimientoStockService->leeMovimientoStockListado($filtros, true);
        $estado_enum = $this->movimientoStockService->estadoEnum();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $deposito_query = $this->depmaeRepository->allFiltrado();

        return view('stock.movimientostock.index', [
            'datas' => $datas,
            'estado_enum' => $estado_enum,
            'filtros' => $filtros,
            'filtrosQuery' => MovimientoStockListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => MovimientoStockListadoFiltros::CAMPOS,
            'empresa_query' => $empresa_query,
            'deposito_query' => $deposito_query,
            'mostrarFiltroDeposito' => $this->mostrarFiltroDeposito($deposito_query),
        ]);
    }

    public function consultarTransferencia(int $id)
    {
        can('listar-movimientos-de-stock');

        $transferencia = \App\Models\Stock\Transferencia_Mercaderia::query()
            ->with([
                'tipotransaccion_stock:id,nombre,abreviatura',
                'depositoOrigen:id,nombre',
                'depositoDestino:id,nombre',
                'bienUsoOrigen:id,codigo_inventario,hostname,modelo',
                'bienUsoDestino:id,codigo_inventario,hostname,modelo',
                'usuarioOrigen:id,nombre',
                'usuarioDestino:id,nombre',
                'usuarioAprobador:id,nombre',
                'empresas:id,nombre',
                'articulos.articuloOrigen:id,sku,nombre',
                'articulos.articuloDestino:id,sku,nombre',
            ])
            ->findOrFail($id);

        return view('stock.movimientostock.consultar_transferencia', [
            'transferencia' => $transferencia,
            'estadosTransferencia' => \App\Support\Stock\TransferenciaMercaderiaEstados::etiquetas(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-movimientos-de-stock');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = MovimientoStockListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $estado_enum = $this->movimientoStockService->estadoEnum();

        switch ($formato) {
            case 'PDF':
                $datas = $this->movimientoStockRepository->leeMovimientoStock($filtros, false);
                $view = \View::make('stock.movimientostock.listado', compact('datas', 'estado_enum', 'filtros'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_movimientostock';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new MovimientoStockListadoExport($this->movimientoStockRepository))
                    ->parametros($filtros, $estado_enum)
                    ->download('movimientos_stock.xlsx');

            case 'CSV':
                return (new MovimientoStockListadoExport($this->movimientoStockRepository))
                    ->parametros($filtros, $estado_enum)
                    ->download('movimientos_stock.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('movimientostock', MovimientoStockListadoFiltros::paraQueryString($filtros));
    }

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
        $centrocosto_query = $this->centrocostoRepository->all();
        $movimientostock = new MovimientoStock;
        $asientoPreview = ['activo' => false];
        $mostrarSolapaAsiento = $tipotransaccion_query->contains(fn ($t) => (bool) $t->maneja_contabilidad);
        $movimientoStockModoFerli = \App\Support\Stock\MovimientoStockFerliSupport::esCalzadosFerli();
        $bienesUsoActivos = $this->bienesUsoActivosParaTransferencia();
        $transferenciaVinculada = null;

        return view('stock.movimientostock.crear', compact(
            'mventa_query', 'articulo_query', 'modulo_query', 'listaprecio_query', 
            'articuloall_query', 'articuloxsku_query', 
            'tipotransaccion_query', 'tipotransacciondefault_id', 'deposito_query', 'lote_query',
            'empresa_query', 'empresa_id', 'centrocosto_query', 'movimientostock',
            'asientoPreview', 'mostrarSolapaAsiento', 'movimientoStockModoFerli', 'bienesUsoActivos', 'transferenciaVinculada'));
    }

    public function guardar(ValidacionMovimientoStock $request)
    {
		$mensaje = '';
		try
		{
            if ($this->requestEsTransferenciaStock($request)) {
                $resultado = $this->grabarTransferenciaDesdeMovimientoStock($request);
                if (! ($resultado['ok'] ?? false)) {
                    throw new \Exception($resultado['mensaje'] ?? 'No se pudo registrar la transferencia.');
                }

                return redirect('stock/movimientostock')->with('mensaje', $resultado['mensaje'] ?? 'Transferencia registrada.');
            }

            $data = $this->movimientoStockService->guardaMovimientoStock($request->all(), 'create');
			if (is_array($data))
				$mensaje = 'Movimiento de stock creado con éxito';
			else
				if ($data)
					$mensaje = $data;
		} catch (\Exception $e)
		{
			return redirect()->back()->withInput()->with('mensaje', $e->getMessage());
		}

        return redirect('stock/movimientostock')->with('mensaje', $mensaje ?: 'Movimiento de stock creado con éxito');
    }

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
        $centrocosto_query = $this->centrocostoRepository->all();
        $movimientostock->loadMissing([
            'tipotransaccion_stock',
            'articulos_movimiento.articulos.unidadesdemedidas',
            'articulos_movimiento.articulos.unidadesdemedidasalternativas',
            'articulos_movimiento.combinaciones',
        ]);
        $asientoPreview = $this->asientoService->previewParaVista($movimientostock);
        $mostrarSolapaAsiento = ! empty($asientoPreview['activo'])
            || $tipotransaccion_query->contains(fn ($t) => (bool) $t->maneja_contabilidad);
        $movimientoStockModoFerli = \App\Support\Stock\MovimientoStockFerliSupport::esCalzadosFerli();
        $bienesUsoActivos = $this->bienesUsoActivosParaTransferencia();
        $transferenciaVinculada = Transferencia_Mercaderia::query()
            ->with(['depositoOrigen:id,nombre,codigo', 'depositoDestino:id,nombre,codigo', 'bienUsoOrigen', 'bienUsoDestino'])
            ->where(function ($q) use ($id) {
                $q->where('movimientostock_salida_id', (int) $id)
                    ->orWhere('movimientostock_entrada_id', (int) $id);
            })
            ->first();

        return view('stock.movimientostock.editar', compact('movimientostock', 
			'mventa_query', 'articulo_query', 'modulo_query', 
			'listaprecio_query', 'articuloall_query', 'articuloxsku_query', 
			'tipotransaccion_query', 'tipotransacciondefault_id', 'deposito_query', 'lote_query',
            'empresa_query', 'empresa_id', 'centrocosto_query', 'asientoPreview', 'mostrarSolapaAsiento',
            'movimientoStockModoFerli', 'bienesUsoActivos', 'transferenciaVinculada'));
    }

    public function actualizar(ValidacionMovimientoStock $request, $id)
    {
        can('actualizar-movimientos-de-stock');

		try {
			$this->movimientoStockService->guardaMovimientoStock($request->all(), 'update', $id);
		} catch (\Exception $e) {
			return redirect()->back()->withInput()->with('mensaje', $e->getMessage());
		}

        return redirect('stock/movimientostock')->with('mensaje', 'Movimiento de Stock actualizado con éxito');
    }

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

    public function previewAsientoContable(Request $request, ?int $id = null): JsonResponse
    {
        if ($id) {
            can('editar-movimientos-de-stock');
        } else {
            can('crear-movimientos-de-stock');
        }

        $existente = null;
        if ($id) {
            $existente = $this->movimientoStockService->leeMovimientoStock($id);
        }

        $asientoPreview = $this->asientoService->previewDesdeRequest($request, $existente);

        $html = view('stock.movimientostock.partials.solapa_asiento_contable_body', [
            'asientoPreview' => $asientoPreview,
        ])->render();

        return response()->json([
            'html' => $html,
            'activo' => ! empty($asientoPreview['activo']),
            'error' => $asientoPreview['error'] ?? null,
            'es_preview' => $asientoPreview['es_preview'] ?? true,
            'cuadra' => empty($asientoPreview['error']),
        ]);
    }

    public function previewConversionFormula(Request $request): JsonResponse
    {
        if (! can('crear-movimientos-de-stock', false) && ! can('editar-movimientos-de-stock', false)) {
            return response()->json(['message' => 'No tiene permisos para esta consulta.'], 403);
        }

        $resultado = MovimientoStockFormulaConversionSupport::preview(
            (int) $request->input('articulo_id'),
            (int) $request->input('deposito_id'),
            (string) $request->input('sentido', ''),
            (float) $request->input('cantidad', 0),
            (int) $request->input('empresa_id') ?: null,
            (int) $request->input('articulo_compra_id') ?: null
        );

        $status = ($resultado['ok'] ?? false) ? 200 : 422;

        return response()->json($resultado, $status);
    }

    public function saldoArticuloDeposito(Request $request): JsonResponse
    {
        if (! can('crear-movimientos-de-stock', false) && ! can('editar-movimientos-de-stock', false)) {
            return response()->json(['message' => 'No tiene permisos para esta consulta.'], 403);
        }

        $articuloId = (int) $request->query('articulo_id', 0);
        $depositoId = (int) $request->query('deposito_id', 0);
        if ($articuloId <= 0 || $depositoId <= 0) {
            return response()->json(['saldo' => null]);
        }

        $deposito = Depmae::query()->find($depositoId);
        if ($deposito === null) {
            return response()->json(['error' => 'Depósito no encontrado.'], 404);
        }
        if (! Depmae::autorizadoParaUsuarioYEmpresa((int) $deposito->id, (int) $deposito->empresa_id)) {
            return response()->json(['error' => 'Depósito no autorizado.'], 403);
        }
        if (! UsuarioDepositoAutorizado::depositoAutorizado((int) $deposito->id)) {
            return response()->json(['error' => 'No tiene permiso para operar sobre este depósito.'], 403);
        }

        return response()->json([
            'saldo' => $this->saldoDepositoRepository->saldo($articuloId, $depositoId),
        ]);
    }

    public function listarMovimientoStock($id)
    {
        can('listar-movimientos-de-stock');

        return redirect()->route('editar_movimientostock', ['id' => $id]);
    }

   	private function armarTablasVista(&$deposito_query,
                &$mventa_query, &$articulo_query, &$modulo_query, &$listaprecio_query, 
                &$articuloall_query, &$articuloxsku_query, 
                &$tipotransaccion_query, &$lote_query, $movimientostock = null)
    {
        $mventa_query = Mventa::all();
        $tipotransaccion_query = $this->tipotransaccionStockRepository->all(['E', 'S', 'T'], ['A']);
        if ($movimientostock !== null) {
            $tipoActualId = (int) ($movimientostock->tipotransaccion_stock_id ?? 0);
            if ($tipoActualId > 0 && ! $tipotransaccion_query->contains('id', $tipoActualId)) {
                try {
                    $tipotransaccion_query = $tipotransaccion_query
                        ->push($this->tipotransaccionStockRepository->find($tipoActualId))
                        ->sortBy('nombre')
                        ->values();
                } catch (\Throwable) {
                    // tipo histórico inexistente: se mantiene el listado operativo
                }
            }
        }
        $deposito_query = $this->depmaeRepository->allFiltrado();
    
        $articulo_ids = Array();
        if ($movimientostock != null)	
        {
            $articulo_ids[] = $movimientostock->articulo_id;
        }
        else
            $articulo_ids[] = 0;

        $articulo_query = Articulo::select('id', 'sku', 'descripcion', 'mventa_id')
            ->orderBy('descripcion', 'ASC')
            ->where(function ($q) use ($articulo_ids) {
                $q->where(function ($qActivos) {
                    \App\Support\Stock\ArticuloSeleccionOperativaSupport::aplicarSoloActivosTablaArticulo($qActivos)
                        ->whereExists(function ($query) {
                            $query->select(DB::raw(1))
                                ->from('combinacion')
                                ->whereRaw("combinacion.articulo_id=articulo.id and combinacion.estado = 'A'");
                        });
                })->orWhereIn('id', $articulo_ids);
            })
            ->get();

        $articuloall_query = \App\Support\Stock\ArticuloSeleccionOperativaSupport::aplicarSoloActivosTablaArticulo(
            Articulo::select('id', 'sku', 'descripcion', 'mventa_id')
                ->orderBy('descripcion', 'ASC')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('combinacion')
                        ->whereRaw('combinacion.articulo_id=articulo.id');
                })
        )->get();

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

    private function mostrarFiltroDeposito($depositoQuery): bool
    {
        if (! UsuarioDepositoAutorizado::tieneRestriccion()) {
            return $depositoQuery->count() > 1;
        }

        return $depositoQuery->count() > 1;
    }

    private function bienesUsoActivosParaTransferencia()
    {
        return BienUso::query()
            ->where('estado', 'A')
            ->orderBy('hostname')
            ->get(['id', 'codigo_inventario', 'hostname', 'modelo']);
    }

    private function requestEsTransferenciaStock(Request $request): bool
    {
        $tipoId = (int) ($request->input('tipotransaccion_stock_id') ?: $request->input('tipotransaccion_id'));
        if ($tipoId <= 0) {
            return false;
        }

        $tipo = Tipotransaccion_Stock::query()->find($tipoId);

        return $tipo !== null && $tipo->operacion === 'T';
    }

    /**
     * @return array{ok: bool, mensaje?: string, codigo?: string, transferencia_id?: int}
     */
    private function grabarTransferenciaDesdeMovimientoStock(Request $request): array
    {
        $lineas = [];
        foreach ($request->input('articulos_id', []) as $i => $articuloId) {
            $cantidad = abs((float) ($request->input('cantidades', [])[$i] ?? 0));
            if ((int) $articuloId > 0 && $cantidad > 0) {
                $lineas[] = [
                    'articulo_id' => (int) $articuloId,
                    'cantidad' => $cantidad,
                ];
            }
        }

        return $this->transferenciaMercaderiaService->grabarTransferencia(
            [
                'empresa_id' => (int) $request->input('empresa_id'),
                'deposito_salida_id' => (int) ($request->input('deposito_salida_id') ?: $request->input('deposito_id')),
                'deposito_entrada_id' => (int) $request->input('deposito_entrada_id'),
                'bien_uso_destino_id' => (int) $request->input('bien_uso_destino_id'),
                'bien_uso_origen_id' => (int) $request->input('bien_uso_origen_id'),
                'tipotransaccion_stock_id' => (int) $request->input('tipotransaccion_stock_id'),
                'observacion' => trim((string) $request->input('leyenda', '')),
            ],
            $lineas
        );
    }
}
