<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\MovimientoStockListadoExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMovimientoStock;
use App\Services\Stock\MovimientoStockAsientoService;
use App\Services\Stock\MovimientoStockPdfService;
use App\Services\Stock\MovimientoStockRevertirService;
use App\Services\Stock\MovimientoStockService;
use App\Services\Stock\Surmar\MovimientoStockSurmarEtiquetaService;
use App\Services\Stock\TransferenciaMercaderiaPdfService;
use App\Support\Stock\Surmar\MovimientoSurmarPermisoSupport;
use App\Support\Stock\SurmarSupport;
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
use App\Support\Stock\ArticuloParteUnicaDisponibilidadSupport;
use App\Support\Stock\ArticuloPrecioMovimientoStockSupport;
use App\Support\Stock\AltaNpuMovimientoStockSupport;
use App\Support\Stock\BajaNpuMovimientoStockSupport;
use App\Support\Stock\MovimientoStockEdicionVentanaSupport;
use App\Support\Stock\MovimientoStockFormLineasSupport;
use App\Support\Stock\MovimientoStockFormulaConversionSupport;
use App\Support\Pdf\DompdfPaperSupport;
use App\Support\Stock\MovimientoStockListadoFiltros;
use App\Support\Stock\MovimientoStockPreferenciasUsuario;
use App\Support\Stock\MovimientoStockVisibilidadSupport;
use App\Support\Stock\NpuBajaConsultaSupport;
use App\Support\Stock\TransferenciaBienUsoSupport;
use App\Support\Stock\TransferenciaMercaderiaLineaContableSupport;
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
    private MovimientoStockPdfService $pdfService;
    private TransferenciaMercaderiaPdfService $transferenciaPdfService;
    private MovimientoStockRevertirService $revertirService;
	
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
        MovimientoStockPdfService $pdfService,
        TransferenciaMercaderiaPdfService $transferenciaPdfService,
        MovimientoStockRevertirService $revertirService,
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
        $this->pdfService = $pdfService;
        $this->transferenciaPdfService = $transferenciaPdfService;
        $this->revertirService = $revertirService;
    }

    public function index(Request $request)
    {
        MovimientoSurmarPermisoSupport::puedeListar();
        $this->aplicarModoSurmarAlRequest($request);

        $empresaDefault = $this->empresaDefaultParaListado($request);
        $modoSurmar = $this->esModoSurmar($request);

        if (! MovimientoStockListadoFiltros::requestTraeFiltros($request)) {
            $guardados = MovimientoStockListadoFiltros::guardados($modoSurmar);
            if ($guardados !== []) {
                return redirect()->route(
                    $modoSurmar ? 'movimiento_surmar' : 'movimientostock',
                    $guardados
                );
            }
        }

        $filtros = MovimientoStockListadoFiltros::resolverDesdeRequest(
            $request,
            null,
            $empresaDefault
        );
        $this->persistirFiltrosListado($request, $filtros, $modoSurmar);
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
            'alcance_centro_costo' => MovimientoStockVisibilidadSupport::etiquetaAlcanceActivo(
                isset($filtros['empresa_id']) ? (int) $filtros['empresa_id'] : null
            ),
            'modo_surmar' => $modoSurmar,
            'ruta_index_movimientostock' => $modoSurmar ? 'movimiento_surmar' : 'movimientostock',
            'ruta_crear_movimientostock' => $modoSurmar ? 'crear_movimiento_surmar' : 'crear_movimientostock',
            'ruta_lista_movimientostock' => $modoSurmar ? 'lista_movimiento_surmar' : 'lista_movimientostock',
        ]);
    }

    public function consultarTransferencia(int $id)
    {
        MovimientoStockVisibilidadSupport::abortSiNoPuedeConsultarTransferencia();

        MovimientoStockVisibilidadSupport::abortSiNoAccesibleTransferencia($id);

        $transferencia = \App\Models\Stock\Transferencia_Mercaderia::query()
            ->with([
                'tipotransaccion_stock:id,nombre,abreviatura',
                'depositoOrigen:'.implode(',', TransferenciaBienUsoSupport::DEPOSITO_RELATION_COLUMNS),
                'depositoDestino:'.implode(',', TransferenciaBienUsoSupport::DEPOSITO_RELATION_COLUMNS),
                'bienUsoOrigen:'.implode(',', TransferenciaBienUsoSupport::BIEN_USO_RELATION_COLUMNS),
                'bienUsoDestino:'.implode(',', TransferenciaBienUsoSupport::BIEN_USO_RELATION_COLUMNS),
                'usuarioOrigen:id,nombre',
                'usuarioDestino:id,nombre',
                'usuarioAprobador:id,nombre',
                'empresas:id,nombre',
                'articulos.articuloOrigen:id,sku,descripcion',
                'articulos.articuloDestino:id,sku,descripcion',
            ])
            ->findOrFail($id);

        return view('stock.movimientostock.consultar_transferencia', [
            'transferencia' => $transferencia,
            'estadosTransferencia' => \App\Support\Stock\TransferenciaMercaderiaEstados::etiquetas(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        MovimientoSurmarPermisoSupport::puedeListar();
        $this->aplicarModoSurmarAlRequest($request);

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaDefault = $this->empresaDefaultParaListado($request);
        $filtros = MovimientoStockListadoFiltros::resolverDesdeRequest(
            $request,
            $busqueda,
            $empresaDefault
        );
        $estado_enum = $this->movimientoStockService->estadoEnum();
        $modoSurmar = $this->esModoSurmar($request);

        switch ($formato) {
            case 'PDF':
                $datas = $this->movimientoStockRepository->leeMovimientoStock($filtros, false);
                $view = \View::make('stock.movimientostock.listado', compact('datas', 'estado_enum', 'filtros'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_movimientostock';

                $pdf = \App::make('dompdf.wrapper');
                DompdfPaperSupport::aplicar($pdf, DompdfPaperSupport::CONTEXTO_LISTADO);
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

        return redirect()->route(
            $modoSurmar ? 'movimiento_surmar' : 'movimientostock',
            MovimientoStockListadoFiltros::paraQueryString($filtros)
        );
    }

    public function crear()
    {
        MovimientoSurmarPermisoSupport::puedeCrear();
        $request = request();
        $this->aplicarModoSurmarAlRequest($request);
        $modoSurmar = $this->esModoSurmar($request);

        $this->armarTablasVista($deposito_query,
                                $mventa_query, $articulo_query, $modulo_query, 
                                $listaprecio_query, $articuloall_query, $articuloxsku_query,
                                $tipotransaccion_query, $lote_query);

        $tipotransacciondefault_id = $this->resolverTipotransaccionStockDefaultId();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = old(
            'empresa_id',
            $modoSurmar ? SurmarSupport::EMPRESA_ID : ($empresa_query->first()->id ?? null)
        );
        $centrocosto_query = $this->centrocostoRepository->all();
        $movimientostock = new MovimientoStock;
        $asientoPreview = ['activo' => false];
        $mostrarSolapaAsiento = $tipotransaccion_query->contains(fn ($t) => (bool) $t->maneja_contabilidad);
        $movimientoStockModoFerli = \App\Support\Stock\MovimientoStockFerliSupport::esCalzadosFerli();
        $bienesUsoActivos = $this->bienesUsoActivosParaTransferencia();
        $transferenciaVinculada = null;
        $color_query = \App\Models\Stock\Color::query()->orderBy('nombre')->get(['id', 'nombre']);
        $talle_query = \App\Models\Stock\Talle::query()->orderBy('nombre')->get(['id', 'nombre']);

        return view('stock.movimientostock.crear', compact(
            'mventa_query', 'articulo_query', 'modulo_query', 'listaprecio_query', 
            'articuloall_query', 'articuloxsku_query', 
            'tipotransaccion_query', 'tipotransacciondefault_id', 'deposito_query', 'lote_query',
            'empresa_query', 'empresa_id', 'centrocosto_query', 'movimientostock',
            'asientoPreview', 'mostrarSolapaAsiento', 'movimientoStockModoFerli', 'bienesUsoActivos', 'transferenciaVinculada',
            'color_query', 'talle_query') + [
            'modo_surmar' => $modoSurmar,
            'ruta_index_movimientostock' => $modoSurmar ? 'movimiento_surmar' : 'movimientostock',
            'ruta_guardar_movimientostock' => $modoSurmar ? 'guardar_movimiento_surmar' : 'guardar_movimientostock',
        ]);
    }

    public function guardar(ValidacionMovimientoStock $request)
    {
        MovimientoSurmarPermisoSupport::puedeCrear();
        $this->aplicarModoSurmarAlRequest($request);
        $urlIndex = $this->urlIndexMovimientoStock($request);

		$mensaje = '';
		try
		{
            $tipoStockId = (int) ($request->input('tipotransaccion_stock_id') ?: $request->input('tipotransaccion_id'));

            if ($this->requestEsTransferenciaStock($request)) {
                $this->assertSurmarTraEtiquetasAntesDeGrabar($request, $tipoStockId);

                $resultado = $this->grabarTransferenciaDesdeMovimientoStock($request);
                if (! ($resultado['ok'] ?? false)) {
                    throw new \Exception($resultado['mensaje'] ?? 'No se pudo registrar la transferencia.');
                }
                $tipoStockId = (int) ($resultado['tipotransaccion_stock_id'] ?? $tipoStockId);

                $surmarFlash = $this->procesarSurmarTrasTransferencia($request, $tipoStockId, $resultado);
                MovimientoStockPreferenciasUsuario::persistirTipoTransaccion($tipoStockId);

                $redirect = redirect($urlIndex)
                    ->with('mensaje', ($resultado['mensaje'] ?? 'Transferencia registrada.').($surmarFlash['mensaje_extra'] ?? ''));
                if (! empty($surmarFlash['hijas_ids'])) {
                    $redirect->with('surmar_imprimir_etiquetas', $surmarFlash['hijas_ids']);
                }

                return $redirect;
            }

            $data = $this->movimientoStockService->guardaMovimientoStock($request->all(), 'create');
			if (is_array($data)) {
				$mensaje = $data['mensaje'] ?? 'Movimiento de stock creado con éxito';
                MovimientoStockPreferenciasUsuario::persistirTipoTransaccion($tipoStockId);

                $redirect = redirect($urlIndex)->with('mensaje', $mensaje);
                $hijas = $data['surmar_hijas_ids'] ?? [];
                if (is_array($hijas) && $hijas !== [] && $request->boolean('imprimir_etiquetas_surmar', true)) {
                    $redirect->with('surmar_imprimir_etiquetas', array_values(array_map('intval', $hijas)));
                }

                return $redirect;
			}

            if ($data) {
                return redirect()->back()->withInput()->with('mensaje', $data);
            }
		} catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: $e->getMessage();

            return redirect()->back()->withInput()->with('mensaje-error', $msg);
        } catch (\Exception $e)
		{
			return redirect()->back()->withInput()->with('mensaje-error', $e->getMessage());
		}

        return redirect($urlIndex)->with('mensaje', $mensaje ?: 'Movimiento de stock creado con éxito');
    }

    public function editar($id)
    {
        MovimientoSurmarPermisoSupport::puedeEditar();
        $request = request();
        $this->aplicarModoSurmarAlRequest($request);
        $modoSurmar = $this->esModoSurmar($request);
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
            ->with([
                'depositoOrigen:'.implode(',', TransferenciaBienUsoSupport::DEPOSITO_RELATION_COLUMNS),
                'depositoDestino:'.implode(',', TransferenciaBienUsoSupport::DEPOSITO_RELATION_COLUMNS),
                'bienUsoOrigen',
                'bienUsoDestino',
            ])
            ->where(function ($q) use ($id) {
                $q->where('movimientostock_salida_id', (int) $id)
                    ->orWhere('movimientostock_entrada_id', (int) $id);
            })
            ->first();

        $puedeModificarVentana = MovimientoStockEdicionVentanaSupport::puedeModificar($movimientostock);
        $controlVentanaActivo = MovimientoStockEdicionVentanaSupport::controlActivo();
        $color_query = \App\Models\Stock\Color::query()->orderBy('nombre')->get(['id', 'nombre']);
        $talle_query = \App\Models\Stock\Talle::query()->orderBy('nombre')->get(['id', 'nombre']);

        $etiquetasSurmarPorLinea = [];
        if (SurmarSupport::esEmpresaSurmar((int) $empresa_id)) {
            $etiquetasSurmarPorLinea = app(MovimientoStockSurmarEtiquetaService::class)
                ->consumosPayloadPorLineaProducto((int) $movimientostock->id);
        }

        return view('stock.movimientostock.editar', compact('movimientostock', 
			'mventa_query', 'articulo_query', 'modulo_query', 
			'listaprecio_query', 'articuloall_query', 'articuloxsku_query', 
			'tipotransaccion_query', 'tipotransacciondefault_id', 'deposito_query', 'lote_query',
            'empresa_query', 'empresa_id', 'centrocosto_query', 'asientoPreview', 'mostrarSolapaAsiento',
            'movimientoStockModoFerli', 'bienesUsoActivos', 'transferenciaVinculada',
            'puedeModificarVentana', 'controlVentanaActivo',
            'color_query', 'talle_query', 'etiquetasSurmarPorLinea') + [
            'modo_surmar' => $modoSurmar,
            'ruta_index_movimientostock' => $modoSurmar ? 'movimiento_surmar' : 'movimientostock',
            'ruta_actualizar_movimientostock' => $modoSurmar ? 'actualizar_movimiento_surmar' : 'actualizar_movimientostock',
        ]);
    }

    public function actualizar(ValidacionMovimientoStock $request, $id)
    {
        MovimientoSurmarPermisoSupport::puedeActualizar();
        $this->aplicarModoSurmarAlRequest($request);
        $urlIndex = $this->urlIndexMovimientoStock($request);

        $movimientostock = $this->movimientoStockService->leeMovimientoStock($id);
        if (! MovimientoStockEdicionVentanaSupport::puedeModificar($movimientostock)) {
            return redirect()->back()->withInput()->with('mensaje', MovimientoStockEdicionVentanaSupport::mensajeBloqueo());
        }

		try {
			$this->movimientoStockService->guardaMovimientoStock($request->all(), 'update', $id);
            $tipoStockId = (int) ($request->input('tipotransaccion_stock_id') ?: $request->input('tipotransaccion_id'));
            MovimientoStockPreferenciasUsuario::persistirTipoTransaccion($tipoStockId);
		} catch (\Exception $e) {
			return redirect()->back()->withInput()->with('mensaje', $e->getMessage());
		}

        return redirect($urlIndex)->with('mensaje', 'Movimiento de Stock actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        MovimientoSurmarPermisoSupport::puedeAnular();

        if ($request->ajax()) {
            $movimientostock = $this->movimientoStockService->leeMovimientoStock($id);
            $movimientostock->loadMissing('tipotransaccion_stock');
            if (BajaNpuMovimientoStockSupport::esTipoBajaNpu($movimientostock->tipotransaccion_stock)) {
                return response()->json([
                    'mensaje' => 'ng',
                    'error' => 'Los movimientos de baja NPU no pueden eliminarse; use Revertir para reactivar los NPU.',
                ], 422);
            }
            if (AltaNpuMovimientoStockSupport::esTipoAltaNpu($movimientostock->tipotransaccion_stock)) {
                return response()->json([
                    'mensaje' => 'ng',
                    'error' => 'Los movimientos de alta NPU no pueden eliminarse; use Revertir para eliminar los NPU generados.',
                ], 422);
            }
            if (! MovimientoStockEdicionVentanaSupport::puedeModificar($movimientostock)) {
                return response()->json([
                    'mensaje' => 'ng',
                    'error' => MovimientoStockEdicionVentanaSupport::mensajeBloqueo(),
                ], 422);
            }

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

    public function revertirMovimiento(Request $request, int $id)
    {
        MovimientoSurmarPermisoSupport::puedeRevertir();

        try {
            $resultado = $this->revertirService->revertirMovimiento(
                $id,
                $request->input('fecha_reversion')
            );
        } catch (\Throwable $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['mensaje' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('mensaje', $e->getMessage());
        }

        return $this->respuestaRevertirOk($request, $resultado, 'Movimiento revertido.');
    }

    public function revertirTransferencia(Request $request, int $id)
    {
        MovimientoSurmarPermisoSupport::puedeRevertir();

        try {
            $resultado = $this->revertirService->revertirTransferencia(
                $id,
                $request->input('fecha_reversion')
            );
        } catch (\Throwable $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['mensaje' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('mensaje', $e->getMessage());
        }

        return $this->respuestaRevertirOk($request, $resultado, 'Transferencia revertida.');
    }

    public function previewAsientoContable(Request $request, ?int $id = null): JsonResponse
    {
        if ($id) {
            MovimientoSurmarPermisoSupport::puedeEditar();
        } else {
            MovimientoSurmarPermisoSupport::puedeCrear();
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
        if (! MovimientoSurmarPermisoSupport::puedeCrear(false) && ! MovimientoSurmarPermisoSupport::puedeEditar(false)) {
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
        if (! MovimientoSurmarPermisoSupport::puedeCrear(false) && ! MovimientoSurmarPermisoSupport::puedeEditar(false)) {
            return response()->json(['message' => 'No tiene permisos para esta consulta.'], 403);
        }

        $articuloId = (int) $request->query('articulo_id', 0);
        $depositoId = (int) $request->query('deposito_id', 0);
        $colorId = (int) $request->query('color_id', 0);
        $talleId = (int) $request->query('talle_id', 0);
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

        $saldo = ($colorId > 0 || $talleId > 0)
            ? $this->saldoDepositoRepository->saldoVariante(
                $articuloId,
                $depositoId,
                $colorId > 0 ? $colorId : null,
                $talleId > 0 ? $talleId : null,
            )
            : $this->saldoDepositoRepository->saldo($articuloId, $depositoId);

        // Artículo con flag: sin color/talle aún → saldo de variante 0/0 (no el total sumado).
        if ($colorId <= 0 && $talleId <= 0) {
            $maneja = (bool) (\App\Models\Stock\Articulo::query()
                ->whereKey($articuloId)
                ->value('maneja_stock_color_talle') ?? false);
            if ($maneja) {
                $saldo = $this->saldoDepositoRepository->saldoVariante($articuloId, $depositoId, null, null);
            }
        }

        return response()->json([
            'saldo' => $saldo,
        ]);
    }

    public function resolverEtiquetaSurmar(Request $request, MovimientoStockSurmarEtiquetaService $surmarEtiquetas): JsonResponse
    {
        if (! MovimientoSurmarPermisoSupport::puedeCrear(false) && ! MovimientoSurmarPermisoSupport::puedeEditar(false)) {
            return response()->json(['ok' => false, 'message' => 'No tiene permisos.'], 403);
        }

        $empresaId = (int) $request->input('empresa_id', SurmarSupport::EMPRESA_ID);
        $codigo = trim((string) $request->input('codigo', $request->input('etiqueta', '')));
        if ($codigo === '') {
            return response()->json(['ok' => false, 'message' => 'Indique ID o código de etiqueta.'], 422);
        }

        try {
            $etiqueta = $surmarEtiquetas->resolverEscaneo($codigo, $empresaId, true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Etiqueta inválida.';

            return response()->json(['ok' => false, 'message' => $msg, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'etiqueta' => $etiqueta]);
    }

    public function imprimirEtiquetaSurmar(int $etiquetaId, MovimientoStockSurmarEtiquetaService $surmarEtiquetas)
    {
        if (! MovimientoSurmarPermisoSupport::puedeImprimirEtiqueta(false)) {
            abort(403);
        }

        try {
            $zpl = $surmarEtiquetas->zplParaEtiqueta($etiquetaId);
        } catch (\Throwable $e) {
            abort(404, $e->getMessage());
        }

        return response($zpl, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="etiqueta_'.$etiquetaId.'.zpl"',
        ]);
    }

    public function zplEtiquetasSurmarBatch(Request $request, MovimientoStockSurmarEtiquetaService $surmarEtiquetas): JsonResponse
    {
        if (! MovimientoSurmarPermisoSupport::puedeCrear(false) && ! MovimientoSurmarPermisoSupport::puedeEditar(false)) {
            return response()->json(['ok' => false, 'message' => 'No tiene permisos.'], 403);
        }

        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return response()->json(['ok' => false, 'message' => 'Sin etiquetas.'], 422);
        }

        try {
            $items = $surmarEtiquetas->zplsParaIds($ids);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'etiquetas' => $items]);
    }

    private function assertSurmarTraEtiquetasAntesDeGrabar(Request $request, int $tipoStockId): void
    {
        $tipo = Tipotransaccion_Stock::query()->find($tipoStockId);
        $empresaId = (int) $request->input('empresa_id', 0);
        $surmar = app(MovimientoStockSurmarEtiquetaService::class);
        if (! $surmar->debeProcesar($empresaId, $tipo)) {
            return;
        }
        if (strtoupper(trim((string) ($tipo->abreviatura ?? ''))) !== 'TRA') {
            return;
        }
        $ids = $surmar->idsEtiquetasDesdeRequest($request->all());
        if ($ids === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'etiquetas_consumo_linea' => 'TRA Surmar: piqueá al menos una etiqueta DISPONIBLE por cada ítem.',
            ]);
        }
        $porLinea = $surmar->etiquetasPorLineaProductoDesdeRequest($request->all());
        foreach ($porLinea as $i => $idsLinea) {
            if ($idsLinea === []) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'etiquetas_consumo_linea' => 'TRA Surmar: el renglón '.((int) $i + 1).' no tiene etiquetas piqueadas.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array{mensaje_extra:string, hijas_ids:list<int>}
     */
    private function procesarSurmarTrasTransferencia(Request $request, int $tipoStockId, array $resultado): array
    {
        $out = ['mensaje_extra' => '', 'hijas_ids' => []];
        $tipo = Tipotransaccion_Stock::query()->find($tipoStockId);
        $empresaId = (int) $request->input('empresa_id', 0);
        $surmar = app(MovimientoStockSurmarEtiquetaService::class);
        if (! $surmar->debeProcesar($empresaId, $tipo)) {
            return $out;
        }

        $transferenciaId = (int) ($resultado['transferencia_id'] ?? 0);
        if ($transferenciaId <= 0) {
            return $out;
        }

        $transferencia = Transferencia_Mercaderia::query()->find($transferenciaId);
        if (! $transferencia) {
            return $out;
        }

        $stats = $surmar->procesarDespuesDeTransferencia($transferencia, $tipo, $request->all());
        $out['mensaje_extra'] = ' Surmar TRA: '.$stats['consumos'].' consumida(s), '.$stats['etiquetas_hijas'].' nueva(s).';
        $out['hijas_ids'] = $stats['hijas_ids'] ?? [];
        if ($out['hijas_ids'] !== [] && ! $request->boolean('imprimir_etiquetas_surmar', true)) {
            $out['hijas_ids'] = [];
        }

        return $out;
    }

    public function precioLineaArticulo(Request $request): JsonResponse
    {
        if (! MovimientoSurmarPermisoSupport::puedeCrear(false) && ! MovimientoSurmarPermisoSupport::puedeEditar(false)) {
            return response()->json(['message' => 'No tiene permisos para esta consulta.'], 403);
        }

        $articuloId = (int) $request->query('articulo_id', 0);
        $tipoId = (int) $request->query('tipotransaccion_stock_id', 0);
        if ($articuloId <= 0 || $tipoId <= 0) {
            return response()->json(['precio' => null]);
        }

        $tipo = Tipotransaccion_Stock::query()->find($tipoId);
        if ($tipo === null) {
            return response()->json(['error' => 'Tipo de transacción no encontrado.'], 404);
        }

        $fechaRaw = trim((string) $request->query('fecha', ''));
        $fecha = $fechaRaw !== '' ? \Carbon\Carbon::parse($fechaRaw) : \Carbon\Carbon::today();

        $dato = ArticuloPrecioMovimientoStockSupport::resolverParaLinea($articuloId, $tipo, $fecha);

        return response()->json([
            'precio' => $dato['precio'],
            'listaprecio_id' => $dato['listaprecio_id'],
            'moneda_id' => $dato['moneda_id'],
            'incluyeimpuesto' => $dato['incluyeimpuesto'],
            'criterio' => $dato['criterio'],
            'origen_ultima_compra' => $dato['origen_ultima_compra'],
            'origen_ultima_compra_etiqueta' => ArticuloPrecioMovimientoStockSupport::etiquetaOrigenUltimaCompra(
                $dato['origen_ultima_compra']
            ),
            'criterio_etiqueta' => $dato['criterio'] === ArticuloPrecioMovimientoStockSupport::CRITERIO_VENTA
                ? 'Precio de venta (lista vigente)'
                : 'Precio de última compra',
        ]);
    }

    public function sugerirTipoTransferenciaContable(Request $request): JsonResponse
    {
        if (! MovimientoSurmarPermisoSupport::puedeCrear(false)
            && ! MovimientoSurmarPermisoSupport::puedeEditar(false)) {
            return response()->json(['ok' => false, 'mensaje' => 'No tiene permisos para esta consulta.'], 403);
        }

        $articuloId = (int) $request->query('articulo_id', 0);
        $empresaId = (int) $request->query('empresa_id', 0);
        if ($articuloId <= 0 || $empresaId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Artículo o empresa no indicados.'], 422);
        }

        $articulo = Articulo::query()
            ->with('articulo_cuentacontables')
            ->find($articuloId);
        if ($articulo === null) {
            return response()->json(['ok' => false, 'mensaje' => 'Artículo no encontrado.'], 404);
        }

        $tipoTrcont = $this->tipotransaccionStockRepository
            ->all(['T'], ['A'])
            ->first(static fn ($tipo): bool => strtoupper(trim((string) ($tipo->abreviatura ?? ''))) === 'TRCONT'
                && (bool) ($tipo->maneja_contabilidad ?? false));

        return response()->json([
            'ok' => true,
            'es_contabilizable' => TransferenciaMercaderiaLineaContableSupport::esContabilizable(
                $articulo,
                $empresaId
            ),
            'familia' => TransferenciaMercaderiaLineaContableSupport::resolverFamilia($articulo, $empresaId),
            'tipo_trcont' => $tipoTrcont === null ? null : $this->payloadTipoTransaccionStock($tipoTrcont),
        ]);
    }

    public function resolverNpuBaja(Request $request): JsonResponse
    {
        if (! MovimientoSurmarPermisoSupport::puedeCrear(false) && ! MovimientoSurmarPermisoSupport::puedeEditar(false)) {
            return response()->json(['ok' => false, 'mensaje' => 'No tiene permisos para esta consulta.'], 403);
        }

        $npu = trim((string) $request->query('npu', $request->input('npu', '')));
        if ($npu === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Indique el NPU.'], 422);
        }

        try {
            $parte = ArticuloParteUnicaDisponibilidadSupport::assertActivaParaUso($npu);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        $articulo = $parte->articulos;
        if ($articulo === null) {
            $articulo = Articulo::query()->find((int) $parte->articulo_id);
        }

        $tipoId = (int) $request->input('tipotransaccion_stock_id', 0);
        $tipo = $tipoId > 0
            ? Tipotransaccion_Stock::query()->find($tipoId)
            : null;

        try {
            $datoPrecio = ArticuloPrecioMovimientoStockSupport::resolverParaLinea(
                (int) $parte->articulo_id,
                $tipo,
                null,
            );
        } catch (\Throwable) {
            $datoPrecio = ArticuloPrecioMovimientoStockSupport::resolverParaLinea(
                (int) $parte->articulo_id,
                null,
                null,
            );
        }

        return response()->json([
            'ok' => true,
            'numeroparte' => (int) $parte->numeroparte,
            'articulo_id' => (int) $parte->articulo_id,
            'sku' => (string) ($articulo->sku ?? ''),
            'descripcion' => (string) ($articulo->descripcion ?? ''),
            'precio' => $datoPrecio['precio'],
            'moneda_id' => $datoPrecio['moneda_id'],
            'listaprecio_id' => $datoPrecio['listaprecio_id'],
            'incluyeimpuesto' => $datoPrecio['incluyeimpuesto'],
            'criterio' => $datoPrecio['criterio'],
            'origen_ultima_compra' => $datoPrecio['origen_ultima_compra'],
            'origen_ultima_compra_etiqueta' => ArticuloPrecioMovimientoStockSupport::etiquetaOrigenUltimaCompra(
                $datoPrecio['origen_ultima_compra']
            ),
        ]);
    }

    public function consultaNpuBaja(Request $request)
    {
        if (! MovimientoSurmarPermisoSupport::puedeCrear(false) && ! MovimientoSurmarPermisoSupport::puedeEditar(false)) {
            abort(403);
        }

        $consulta = trim((string) ($request->input('consulta') ?? ''));
        $empresaId = (int) $request->input('empresa_id', 0);

        $support = app(NpuBajaConsultaSupport::class);
        $filas = $support->queryActivos($consulta, $empresaId)
            ->limit(200)
            ->get();

        $puedeVerArticulo = can('editar-articulos', false) || can('listar-articulos', false);
        $colspanVacio = 4;

        $output = ['data' => ''];
        if ($filas->isEmpty()) {
            $output['data'] = '<tr><td colspan="'.$colspanVacio.'">Sin resultados</td></tr>';
        } else {
            foreach ($filas as $row) {
                $articulo = $row->articulos;
                $sku = (string) ($articulo->sku ?? '');
                $descripcion = (string) ($articulo->descripcion ?? '');
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="numeroparte">'.e((string) $row->numeroparte).'</td>';
                $output['data'] .= '<td class="sku">'.e($sku).'</td>';
                $output['data'] .= '<td class="descripcion">'.e($descripcion).'</td>';
                $output['data'] .= '<td class="articulo-id d-none">'.e((string) $row->articulo_id).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultanpubaja">Elegir</a>';
                if ($puedeVerArticulo && (int) $row->articulo_id > 0) {
                    $urlConsulta = route('editar_articulo', [
                        'id' => (int) $row->articulo_id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($urlConsulta).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
            }
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    public function imprimirCom(Request $request, int $id)
    {
        MovimientoSurmarPermisoSupport::puedeListar();

        MovimientoStockVisibilidadSupport::abortSiNoAccesibleMovimiento($id);

        return $this->pdfService->descargarCom($id, $request->boolean('inline'));
    }

    public function imprimirTransferenciaCom(Request $request, int $id)
    {
        MovimientoStockVisibilidadSupport::abortSiNoPuedeConsultarTransferencia();

        MovimientoStockVisibilidadSupport::abortSiNoAccesibleTransferencia($id);

        return $this->transferenciaPdfService->descargarCom($id, $request->boolean('inline'));
    }

    public function listarMovimientoStock($id)
    {
        MovimientoSurmarPermisoSupport::puedeListar();

        MovimientoStockVisibilidadSupport::abortSiNoAccesibleMovimiento((int) $id);

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
        return MovimientoStockPreferenciasUsuario::resolverTipoTransaccionDefaultId();
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
            ->orderByRaw('COALESCE(uid, hostname)')
            ->get(TransferenciaBienUsoSupport::BIEN_USO_RELATION_COLUMNS);
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
                'centrocosto_destino_id' => (int) $request->input('centrocosto_destino_id'),
                'usuario_destino_id' => (int) $request->input('usuario_destino_id'),
                'seleccion_automatica_trcont' => true,
                'enviar_aviso' => $request->has('enviar_aviso') ? $request->input('enviar_aviso') : null,
                'observacion' => trim((string) $request->input('leyenda', '')),
            ],
            $lineas
        );
    }

    private function payloadTipoTransaccionStock(Tipotransaccion_Stock $tipo): array
    {
        return [
            'id' => (int) $tipo->id,
            'nombre' => (string) $tipo->nombre,
            'abreviatura' => (string) $tipo->abreviatura,
            'operacion' => (string) $tipo->operacion,
            'maneja_contabilidad' => (bool) $tipo->maneja_contabilidad,
            'origen_bien_uso' => (bool) $tipo->origen_bien_uso,
            'destino_bien_uso' => (bool) $tipo->destino_bien_uso,
            'requiere_aprobacion' => (bool) $tipo->requiere_aprobacion,
            'aviso_opcional' => (bool) $tipo->aviso_opcional,
            'baja_npu' => (bool) $tipo->baja_npu,
            'alta_npu' => (bool) $tipo->alta_npu,
        ];
    }

    private function esModoSurmar(?Request $request = null): bool
    {
        $request = $request ?? request();

        return $request->boolean('modo_surmar')
            || MovimientoSurmarPermisoSupport::soloModoSurmar()
            || str_contains((string) $request->path(), 'movimiento-surmar');
    }

    private function aplicarModoSurmarAlRequest(Request $request): void
    {
        if (! $this->esModoSurmar($request)) {
            return;
        }

        SurmarSupport::abortSiNoSurmar(SurmarSupport::EMPRESA_ID);
        $request->merge([
            'empresa_id' => SurmarSupport::EMPRESA_ID,
            'empresa_scope' => 'una',
            'empresa_todas' => 0,
            'modo_surmar' => 1,
        ]);
    }

    private function empresaDefaultParaListado(Request $request): ?int
    {
        if ($this->esModoSurmar($request)) {
            return SurmarSupport::EMPRESA_ID;
        }

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;

        return $empresaDefault ? (int) $empresaDefault : null;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function persistirFiltrosListado(Request $request, array $filtros, bool $modoSurmar): void
    {
        if (! MovimientoStockListadoFiltros::requestTraeFiltros($request)) {
            return;
        }

        $filtrosQuery = MovimientoStockListadoFiltros::paraQueryString($filtros);
        $page = (int) $request->input('page', 0);
        if ($page > 1) {
            $filtrosQuery['page'] = $page;
        }

        MovimientoStockListadoFiltros::persistir($filtrosQuery, $modoSurmar);
    }

    private function urlIndexMovimientoStock(?Request $request = null): string
    {
        $modoSurmar = $this->esModoSurmar($request);
        $query = MovimientoStockListadoFiltros::guardados($modoSurmar);

        if ($modoSurmar) {
            return route('movimiento_surmar', array_merge(
                ['empresa_id' => SurmarSupport::EMPRESA_ID],
                $query
            ));
        }

        return route('movimientostock', $query);
    }

    /**
     * @param  array{mensaje?: string}  $resultado
     */
    private function respuestaRevertirOk(Request $request, array $resultado, string $fallback): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $urlIndex = $this->urlIndexMovimientoStock($request);
        $texto = (string) ($resultado['mensaje'] ?? $fallback);

        if ($request->ajax() || $request->wantsJson()) {
            session()->flash('mensaje-aviso', $texto);

            return response()->json([
                'mensaje' => 'ok',
                'redirect' => $urlIndex,
                'resultado' => $resultado,
            ]);
        }

        return redirect($urlIndex)->with('mensaje-aviso', $texto);
    }
}
