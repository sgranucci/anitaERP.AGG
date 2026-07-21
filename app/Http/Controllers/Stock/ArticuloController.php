<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\ArticuloExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionArticulo;
use App\Mail\Stock\AltaArticulo;
use App\Models\Configuracion\Impuesto;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Cuentacontable;
use App\Models\Stock\Articulo_Estado;
use App\Models\Stock\Caja;
use App\Models\Stock\Capacidad;
use App\Models\Stock\Categoria;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Depmae;
use App\Models\Stock\Linea;
use App\Models\Stock\Mventa;
use App\Models\Stock\Precio;
use App\Models\Stock\Subcategoria;
use App\Models\Stock\Tipoarticulo;
use App\Models\Stock\Tipoliquido;
use App\Models\Stock\Tipoproducto;
use App\Models\Stock\Unidadmedida;
use App\Models\Stock\Usoarticulo;
use App\Repositories\Compras\CondicionentregaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\OficinacompraRepositoryInterface;
use App\Repositories\Configuracion\PeriodicidadcompraRepositoryInterface;
use App\Repositories\Configuracion\SeteoModeloetiquetaRepositoryInterface;
use App\Repositories\Configuracion\SeteosalidaRepositoryInterface;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Stock\Articulo_ArchivoRepositoryInterface;
use App\Repositories\Stock\Articulo_CajaRepositoryInterface;
use App\Repositories\Stock\Articulo_CostoRepositoryInterface;
use App\Repositories\Stock\Articulo_CuentacontableRepositoryInterface;
use App\Repositories\Stock\Articulo_EstadoRepositoryInterface;
use App\Repositories\Stock\Articulo_ProveedorRepositoryInterface;
use App\Repositories\Stock\ArticuloRepositoryInterface;
use App\Repositories\Ventas\DescuentoventaRepositoryInterface;
use App\Support\Stock\PrecioListaVigenteSupport;
use App\Services\Stock\ArticuloAnitaSyncService;
use App\Services\Stock\PrecioService;
use App\Services\Stock\StkdepSaldoAnitaService;
use App\Services\Stock\ArticuloParteUnicaService;
use App\Support\Stock\ArticuloConsultaDesdeModal;
use App\Support\Stock\ArticuloSaldosDepositoSupport;
use App\Support\Stock\MovimientosArticuloDepositoSupport;
use App\Support\Stock\TransferenciaMercaderiaRepararCostosSupport;
use App\Support\Stock\ArticuloEtiquetaNpuRangoSupport;
use App\Support\Stock\ArticuloEtiquetaNpuSupport;
use App\Support\Stock\ArticuloEtiquetaZplSupport;
use App\Support\Stock\ArticuloListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Compras\ArticuloProveedorMatchSupport;
use App\Support\Compras\ArticuloProveedorPrecioListaSupport;
use App\Support\Stock\ArticuloProveedorLineasSupport;
use App\Support\Stock\ArticuloUltimoCreatePrefill;
use App\Support\Stock\RecepcionProveedorParteUnicaSupport;
use Auth;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mail;

class ArticuloController extends Controller
{
    private $articuloRepository;

    private $articulo_cajaRepository;

    private $articulo_costoRepository;

    private $articulo_estadoRepository;

    private $articulo_archivoRepository;

    private $articulo_cuentacontableRepository;

    private $articulo_proveedorRepository;

    private $cuentacontableRepository;

    private $empresaRepository;

    private $descuentoventaRepository;

    private $oficinacompraRepository;

    private $condicionentregaRepository;

    private $peridicidadcompraRepository;

    private $seteoModeloetiquetaRepository;

    private $seteosalidaRepository;

    protected $precioService;

    private ArticuloAnitaSyncService $articuloAnitaSyncService;

    private StkdepSaldoAnitaService $stkdepSaldoAnitaService;

    public function __construct(Articulo_CajaRepositoryInterface $articulo_cajaRepository,
        Articulo_CostoRepositoryInterface $articulo_costoRepository,
        Articulo_EstadoRepositoryInterface $articulo_estadoRepository,
        Articulo_ArchivoRepositoryInterface $articulo_archivoRepository,
        Articulo_CuentacontableRepositoryInterface $articulo_cuentacontableRepository,
        Articulo_ProveedorRepositoryInterface $articulo_proveedorRepository,
        CuentacontableRepositoryInterface $cuentacontableRepository,
        ArticuloRepositoryInterface $articuloRepository,
        OficinacompraRepositoryInterface $oficinacompraRepository,
        PeriodicidadcompraRepositoryInterface $periodicidadcompraRepository,
        CondicionentregaRepositoryInterface $condicionentregaRepository,
        DescuentoventaRepositoryInterface $descuentoventarepository,
        EmpresaRepositoryInterface $empresaRepository,
        SeteoModeloetiquetaRepositoryInterface $seteoModeloetiquetaRepository,
        SeteosalidaRepositoryInterface $seteosalidarepository,
        PrecioService $precioservice,
        ArticuloAnitaSyncService $articuloAnitaSyncService,
        StkdepSaldoAnitaService $stkdepSaldoAnitaService)
    {
        $this->articulo_cajaRepository = $articulo_cajaRepository;
        $this->articulo_costoRepository = $articulo_costoRepository;
        $this->articulo_estadoRepository = $articulo_estadoRepository;
        $this->articulo_archivoRepository = $articulo_archivoRepository;
        $this->articulo_cuentacontableRepository = $articulo_cuentacontableRepository;
        $this->articulo_proveedorRepository = $articulo_proveedorRepository;
        $this->cuentacontableRepository = $cuentacontableRepository;
        $this->articuloRepository = $articuloRepository;
        $this->oficinacompraRepository = $oficinacompraRepository;
        $this->condicionentregaRepository = $condicionentregaRepository;
        $this->periodicidadcompraRepository = $periodicidadcompraRepository;
        $this->descuentoventaRepository = $descuentoventarepository;
        $this->empresaRepository = $empresaRepository;
        $this->seteoModeloetiquetaRepository = $seteoModeloetiquetaRepository;
        $this->seteoSalidaRepository = $seteosalidarepository;
        $this->precioService = $precioservice;
        $this->articuloAnitaSyncService = $articuloAnitaSyncService;
        $this->stkdepSaldoAnitaService = $stkdepSaldoAnitaService;
    }

    /**
     * Importación desde Anita (stkmae). Puede superar el timeout del proxy (504);
     * en ese caso usar: php artisan articulo:sincronizar-anita
     */
    public function sincronizarDesdeAnita(Request $request)
    {
        can('actualizar-articulos');

        if (! $request->isMethod('post')) {
            abort(405);
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        set_time_limit(0);
        ignore_user_abort(true);

        try {
            $ret = $this->articuloAnitaSyncService->sincronizarDesdeAnita();
            $msg = 'Sincronización desde Anita: '.$ret['en_anita'].' códigos en Anita, '
                .$ret['importados'].' altas ejecutadas, '.$ret['omitidos_ya_en_erp'].' ya existían en el ERP.';
            if (! empty($ret['advertencias'])) {
                $msg .= ' '.implode(' ', array_slice($ret['advertencias'], 0, 8));
            }

            return redirect()->route('articulo')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('Articulo sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('articulo')->with('errores', [
                'No se completó la sincronización desde Anita. Si el error fue por tiempo de espera (504), ejecute en el servidor: php artisan articulo:sincronizar-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }

    public function index(Request $request)
    {
        can('listar-articulos');

        $filtros = ArticuloListadoFiltros::resolverDesdeRequest($request);

        $articulos = $this->articuloRepository->leeArticulo($filtros, true);

        if ($articulos->isEmpty() && config('app.anita_sync_articulo_index')) {
            $Articulo = new Articulo;
            $Articulo->sincronizarConAnita();

            $articulos = $this->articuloRepository->leeArticulo($filtros, true);
        }

        $saldosStkdep = [];
        try {
            $saldosStkdep = $this->stkdepSaldoAnitaService->saldosStkdepPorArticulosLab($articulos);
        } catch (\Throwable $e) {
            Log::warning('Articulo index: no se pudo consultar saldo stkdep', ['exception' => $e->getMessage()]);
        }

        return view('stock.articulo.index', [
            'articulos' => $articulos,
            'busqueda' => $filtros['busqueda'],
            'filtros' => $filtros,
            'filtrosQuery' => ArticuloListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ArticuloListadoFiltros::CAMPOS,
            'saldosStkdep' => $saldosStkdep,
        ]);
    }

    public function apiSaldosDeposito(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! ArticuloSaldosDepositoSupport::puedeConsultar()) {
            abort(403, 'No tiene permisos para consultar saldos de stock.');
        }

        $articuloId = (int) $request->query('articulo_id', 0);
        if ($articuloId <= 0) {
            return response()->json(['error' => 'Artículo inválido.'], 422);
        }

        $empresaId = (int) $request->query('empresa_id', 0);
        $empresaId = $empresaId > 0 ? $empresaId : null;

        if ($empresaId !== null && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'Empresa no autorizada.');
        }

        try {
            $datos = ArticuloSaldosDepositoSupport::listadoPorArticulo($articuloId, $empresaId);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($datos);
    }

    public function apiPreviewRecalcularTransferenciasFormula(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        if (! TransferenciaMercaderiaRepararCostosSupport::puedeRecalcularDesdeArticulo()) {
            abort(403, 'No tiene permisos para recalcular transferencias a fórmulas.');
        }

        $articuloId = (int) $id;
        if ($articuloId <= 0) {
            return response()->json(['error' => 'Artículo inválido.'], 422);
        }

        try {
            $datos = TransferenciaMercaderiaRepararCostosSupport::previewPorArticulo($articuloId, [
                'modo' => (string) $request->input('modo', 'ultima'),
                'fecha_desde' => $request->input('fecha_desde'),
                'fecha_hasta' => $request->input('fecha_hasta'),
                'coeficiente' => $request->input('coeficiente'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($datos);
    }

    public function apiAplicarRecalcularTransferenciasFormula(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        if (! TransferenciaMercaderiaRepararCostosSupport::puedeRecalcularDesdeArticulo()) {
            abort(403, 'No tiene permisos para recalcular transferencias a fórmulas.');
        }

        $articuloId = (int) $id;
        if ($articuloId <= 0) {
            return response()->json(['error' => 'Artículo inválido.'], 422);
        }

        $lineaIds = $request->input('linea_ids', []);
        if (! is_array($lineaIds)) {
            $lineaIds = [];
        }

        try {
            $datos = TransferenciaMercaderiaRepararCostosSupport::aplicarPorArticulo($articuloId, [
                'modo' => (string) $request->input('modo', 'ultima'),
                'fecha_desde' => $request->input('fecha_desde'),
                'fecha_hasta' => $request->input('fecha_hasta'),
                'coeficiente' => $request->input('coeficiente'),
                'linea_ids' => $lineaIds,
                'solo_con_cambio' => filter_var($request->input('solo_con_cambio', true), FILTER_VALIDATE_BOOLEAN),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($datos);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-articulos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ArticuloListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $articulos = $this->articuloRepository->leeArticulo($filtros, false);

                $view = \View::make('stock.articulo.listado', compact('articulos'))
                    ->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_articulo';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');
                break;

            case 'EXCEL':
                return (new ArticuloExport($this->articuloRepository))
                    ->parametros($filtros)
                    ->download('articulo.xlsx');
                break;

            case 'CSV':
                return (new ArticuloExport($this->articuloRepository))
                    ->parametros($filtros)
                    ->download('articulo.csv', \Maatwebsite\Excel\Excel::CSV);
                break;
        }

        return redirect()->route('articulo', ArticuloListadoFiltros::paraQueryString($filtros));
    }

    public function limpiafiltro(Request $request)
    {
        session()->forget('filtros');

        return json_encode(['ok']);
    }

    // Consulta productos desde QR de etiquetas

    public function consultaProducto($sku)
    {

        // $articulo = Articulo::where("sku",$sku)->first();
        // $combinacion = '';
        // if ($articulo)
        // {
        // $combinacion = Combinacion::where("articulo_id",$articulo->id)->where("estado",'A')->get();

        // return view("stock.product.consultaproducto",compact('articulo', 'combinacion'));
        // }

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '2400');

        $_fecha = Carbon::now();
        $fecha_hoy = \Carbon\Carbon::parse($_fecha)->format('d/m/Y');

        $combinacion = Combinacion::select(
            'combinacion.codigo as codigo',
            'combinacion.nombre as nombre',
            'articulo.id as articulo_id',
            'articulo.sku as sku',
            'articulo.descripcion as descripcion',
            'articulo.mventa_id as marca',
            'numeracion.nombre as numeracion',
            'material.nombre as material',
            'forro.nombre as forro',
            'compfondo.nombre as fondo',
            'combinacion.foto as foto',
            'linea.nombre as linea',
            'articulo.linea_id as linea_id',
            'p1.nombre as nombrelista1',
            'p2.nombre as nombrelista2',
            'p3.nombre as nombrelista3',
            'p4.nombre as nombrelista4',
        )
            ->leftJoin('articulo', 'combinacion.articulo_id', 'articulo.id')
            ->leftJoin('linea', 'linea.id', 'articulo.linea_id')
            ->leftJoin('numeracion', 'numeracion.id', 'linea.numeracion_id')
            ->leftJoin('material', 'material.id', 'articulo.material_id')
            ->leftJoin('forro', 'forro.id', 'articulo.forro_id')
            ->leftJoin('compfondo', 'compfondo.id', 'articulo.compfondo_id')
            ->leftJoin('listaprecio as p1', function ($joinp1) {
                $joinp1->where('p1.id', '1');
            })
            ->leftJoin('listaprecio as p2', function ($joinp2) {
                $joinp2->where('p2.id', '2');
            })
            ->leftJoin('listaprecio as p3', function ($joinp3) {
                $joinp3->where('p3.id', '3');
            })
            ->leftJoin('listaprecio as p4', function ($joinp4) {
                $joinp4->where('p4.id', '6');
            })
            ->orderBy('linea', 'asc')
            ->orderBy('articulo.sku', 'asc')
            ->where('combinacion.estado', 'A')
            ->where('articulo.sku', $sku)
            ->get();

        $combinacion = $combinacion->groupBy(function ($linea) {
            return $linea->linea;
        })->all();

        if (count($combinacion) > 0) {
            foreach ($combinacion as $linea) {
                $items = collect();

                foreach ($linea as $item) {
                    $nombre_pdf = $item->linea;
                    $linea_id = $item->linea_id;
                    $tiponumeracion = Linea::select('tiponumeracion_id')->where('id', $linea_id)->first();

                    // Asigna precio por vigencia
                    $precios = $this->precioService->
                        asignaPrecioPorTipoNumeracion($item->articulo_id,
                            $tiponumeracion->tiponumeracion_id,
                            $_fecha);

                    // Asigna precio por vigencia
                    $item->precio4 = 0;
                    foreach ($precios as $precio) {
                        if ($precio['listaprecio_id'] == 1) {
                            $item->precio1 = $precio['precio'];
                        }
                        if ($precio['listaprecio_id'] == 2) {
                            $item->precio2 = $precio['precio'];
                        }
                        if ($precio['listaprecio_id'] == 3) {
                            $item->precio3 = $precio['precio'];
                        }
                        if ($precio['listaprecio_id'] >= 4) {
                            $item->precio1 = $precio['precio'];
                        }
                    }
                    $items->push($item);
                }
                $modulos = Linea::select('linea.id',
                    'linea.nombre as nombre',
                    'modulo_talle.modulo_id as modulo_id',
                    'modulo.nombre as modulo_nombre',
                    'modulo_talle.talle_id as talle_id',
                    'talle.nombre as talle',
                    'modulo_talle.cantidad as cantidad')
                    ->where('linea.id', $linea_id)
                    ->leftJoin('linea_modulo', 'linea_modulo.linea_id', '=', 'linea.id')
                    ->leftJoin('modulo_talle', 'modulo_talle.modulo_id', '=', 'linea_modulo.modulo_id')
                    ->leftJoin('modulo', 'modulo.id', '=', 'linea_modulo.modulo_id')
                    ->leftJoin('talle', 'talle.id', '=', 'modulo_talle.talle_id')->get();

                $modulos = $modulos->groupBy(function ($modulo) {
                    return $modulo->modulo_nombre;
                })->all();

                return view('exports.stock.catalogo', compact('items', 'modulos'));
            }
        }
    }

    // Lista etiqueta QR

    public function consultarNpuEtiqueta(Request $request, int $id)
    {
        can('imprimir-articulos-qr');

        $articulo = Articulo::query()->findOrFail($id);
        if (! RecepcionProveedorParteUnicaSupport::articuloManejaParteUnica($articulo)) {
            return response()->json(['mensaje' => 'El artículo no lleva número de parte única.'], 422);
        }

        [$npuDesde, $npuHasta] = $this->npuEtiquetaEntradaDesdeRequest($request);
        $sinCriterio = trim($npuDesde) === '' && trim($npuHasta) === '';

        try {
            if ($sinCriterio) {
                $pagina = ArticuloEtiquetaNpuRangoSupport::consultaPaginada(
                    $id,
                    (int) $request->query('page', 1),
                );
                $npus = $pagina['npus'];
                $cantidad = $pagina['total'];
                $criterio = ArticuloEtiquetaNpuRangoSupport::formatearCriterio($npuDesde, $npuHasta);
                $imprimirUrl = null;
                $datos = null;

                return response()->json([
                    'ok' => true,
                    'datos' => $datos,
                    'cantidad' => $cantidad,
                    'criterio' => $criterio,
                    'npus' => $npus,
                    'listado' => true,
                    'page' => $pagina['page'],
                    'last_page' => $pagina['last_page'],
                    'per_page' => $pagina['per_page'],
                    'imprimir_url' => $imprimirUrl,
                ]);
            }

            $npus = ArticuloEtiquetaNpuRangoSupport::resolverCriterioConsulta($id, $npuDesde, $npuHasta);
        } catch (\Throwable $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        $criterio = ArticuloEtiquetaNpuRangoSupport::formatearCriterio($npuDesde, $npuHasta);
        $cantidad = count($npus);

        $datos = null;
        if ($cantidad === 1) {
            try {
                $datos = ArticuloEtiquetaNpuSupport::resolver($id, $npus[0]);
            } catch (\Throwable $e) {
                return response()->json(['mensaje' => $e->getMessage()], 422);
            }
        }

        $imprimirUrl = null;
        if ($cantidad > 0) {
            try {
                ArticuloEtiquetaNpuRangoSupport::resolverParaImpresion($id, $npuDesde, $npuHasta);
                $imprimirUrl = $this->npuEtiquetaImprimirUrl($id, $npuDesde, $npuHasta);
            } catch (\Throwable $e) {
                return response()->json([
                    'ok' => true,
                    'datos' => $datos,
                    'cantidad' => $cantidad,
                    'criterio' => $criterio,
                    'npus' => $npus,
                    'listado' => false,
                    'imprimir_url' => null,
                    'mensaje_impresion' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'datos' => $datos,
            'cantidad' => $cantidad,
            'criterio' => $criterio,
            'npus' => $npus,
            'listado' => false,
            'imprimir_url' => $imprimirUrl,
        ]);
    }

    public function download(Request $request, $id)
    {
        can('imprimir-articulos-qr');

        $articulo = Articulo::query()->findOrFail($id);

        $npusEtiqueta = [];
        $cantidadEtiquetas = 1;

        if (RecepcionProveedorParteUnicaSupport::articuloManejaParteUnica($articulo)) {
            [$npuDesde, $npuHasta] = $this->npuEtiquetaEntradaDesdeRequest($request);

            if (trim($npuDesde) === '' && trim($npuHasta) === '') {
                return $this->responderImpresionEtiquetaArticulo($request, false, '', [
                    'Indique el NPU, lista o rango a imprimir.',
                ]);
            }

            try {
                $npusEtiqueta = ArticuloEtiquetaNpuRangoSupport::resolverParaImpresion((int) $id, $npuDesde, $npuHasta);
            } catch (\Throwable $e) {
                return $this->responderImpresionEtiquetaArticulo($request, false, '', [$e->getMessage()]);
            }
        } else {
            $cantidadEtiquetas = $this->cantidadEtiquetaDesdeRequest($request);
            if ($cantidadEtiquetas === null) {
                return $this->responderImpresionEtiquetaArticulo($request, false, '', [
                    'Indique una cantidad válida de etiquetas (entero entre 1 y '.ArticuloEtiquetaNpuRangoSupport::MAX_ETIQUETAS.').',
                ]);
            }
        }

        // Arma nombre de archivo
        $nombreEtiqueta = 'tmp/eti-'.Str::random(10).'.txt';

        $usuario_id = Auth()->id();
        $modeloetiqueta = $this->seteoModeloetiquetaRepository->buscaSeteoModeloetiqueta(
            $usuario_id,
            SeteoSalidaProgramaSupport::STOCK_ARTICULO
        );

        if (! $modeloetiqueta || ! $modeloetiqueta->modeloetiquetas) {
            return $this->responderImpresionEtiquetaArticulo($request, false, '', [
                'No hay modelo de etiqueta configurado. Use «Configura etiqueta» en el listado.',
            ]);
        }

        $plantillaEtiqueta = $modeloetiqueta->modeloetiquetas->codigoetiqueta;
        $etiqueta = '';

        if ($npusEtiqueta === []) {
            for ($i = 0; $i < $cantidadEtiquetas; $i++) {
                $etiqueta .= $this->armarCodigoEtiquetaArticulo($plantillaEtiqueta, $articulo, null);
            }
        } else {
            foreach ($npusEtiqueta as $numeroparte) {
                try {
                    $datosNpu = ArticuloEtiquetaNpuSupport::resolver((int) $id, $numeroparte);
                } catch (\Throwable $e) {
                    return $this->responderImpresionEtiquetaArticulo($request, false, '', [$e->getMessage()]);
                }

                $etiqueta .= $this->armarCodigoEtiquetaArticulo($plantillaEtiqueta, $articulo, $datosNpu);
            }
        }

        Storage::disk('local')->put($nombreEtiqueta, $etiqueta);
        $path = Storage::path($nombreEtiqueta);

        $seteosalida = $this->seteoSalidaRepository->buscaSeteo($usuario_id, SeteoSalidaProgramaSupport::STOCK_ARTICULO);
        if (! $seteosalida || ! $seteosalida->salidas) {
            Storage::disk('local')->delete($nombreEtiqueta);

            return $this->responderImpresionEtiquetaArticulo($request, false, '', [
                'No hay impresora configurada para artículos. Use «Configura salida» en el listado.',
            ]);
        }

        $comando = trim((string) $seteosalida->salidas->comando);
        if ($comando === '' || ! str_contains($comando, '%s')) {
            Storage::disk('local')->delete($nombreEtiqueta);

            return $this->responderImpresionEtiquetaArticulo($request, false, '', [
                'El comando de la impresora configurada debe incluir %s (ruta del archivo de etiqueta).',
            ]);
        }

        $exitCode = 0;
        passthru(sprintf($comando, $path), $exitCode);

        Storage::disk('local')->delete($nombreEtiqueta);

        if ($exitCode !== 0) {
            return $this->responderImpresionEtiquetaArticulo($request, false, '', [
                'No se pudo enviar la etiqueta a la impresora. Verifique la cola CUPS y que el comando use impresión ZPL cruda (bin/imprimir-etiqueta-zebra.sh).',
            ]);
        }

        $totalImpresas = $npusEtiqueta !== [] ? count($npusEtiqueta) : $cantidadEtiquetas;
        $mensaje = $totalImpresas > 1
            ? 'Se imprimieron '.$totalImpresas.' etiquetas con éxito.'
            : 'El producto seleccionado se imprimió con éxito.';

        return $this->responderImpresionEtiquetaArticulo($request, true, $mensaje);
    }

    /**
     * Cantidad de etiquetas sin NPU (1–MAX_ETIQUETAS). null si el valor no es válido.
     */
    private function cantidadEtiquetaDesdeRequest(Request $request): ?int
    {
        $raw = trim((string) $request->query('cantidad', '1'));
        if ($raw === '' || ! preg_match('/^\d+$/', $raw)) {
            return null;
        }

        $cantidad = (int) $raw;
        if ($cantidad < 1 || $cantidad > ArticuloEtiquetaNpuRangoSupport::MAX_ETIQUETAS) {
            return null;
        }

        return $cantidad;
    }

    private function responderImpresionEtiquetaArticulo(Request $request, bool $ok, string $mensaje = '', array $errores = [])
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => $ok,
                'mensaje' => $mensaje,
                'errores' => $errores,
            ], $ok ? 200 : 422);
        }

        if (! $ok) {
            return redirect()->back()->with('errores', $errores);
        }

        return redirect()->back()->with('mensaje', $mensaje);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function npuEtiquetaEntradaDesdeRequest(Request $request): array
    {
        $desde = trim((string) $request->query('npu_desde', ''));
        $hasta = trim((string) $request->query('npu_hasta', ''));

        if ($desde === '' && $hasta === '') {
            $legacy = trim((string) $request->query('npu', ''));
            if ($legacy !== '') {
                $desde = $legacy;
            }
        }

        return ArticuloEtiquetaNpuRangoSupport::normalizarEntrada($desde, $hasta);
    }

    private function npuEtiquetaImprimirUrl(int $articuloId, string $desde, string $hasta): string
    {
        $query = array_filter([
            'npu_desde' => $desde !== '' ? $desde : null,
            'npu_hasta' => $hasta !== '' ? $hasta : null,
        ], fn ($v) => $v !== null && $v !== '');

        $path = 'stock/listar_etiqueta_articulo/'.$articuloId;
        if ($query !== []) {
            $path .= '?'.http_build_query($query);
        }

        return urlAppAbsoluta($path);
    }

    /**
     * @param  array<string, mixed>|null  $datosNpu
     */
    private function armarCodigoEtiquetaArticulo(string $plantilla, Articulo $articulo, ?array $datosNpu): string
    {
        $plantilla = ArticuloEtiquetaZplSupport::normalizarPlantilla($plantilla);

        $etiqueta = Str::replace('@sku@', $articulo->sku, $plantilla, caseSensitive: false);

        if ($datosNpu !== null) {
            $etiqueta = Str::replace('@npu@', (string) $datosNpu['numeroparte'], $etiqueta, caseSensitive: false);
            $etiqueta = Str::replace('@codigoproveedor@', (string) $datosNpu['codigoproveedor'], $etiqueta, caseSensitive: false);
            $etiqueta = Str::replace('@numerorecepcion@', (string) $datosNpu['numerorecepcion'], $etiqueta, caseSensitive: false);
        } else {
            $etiqueta = Str::replace('@npu@', ' ', $etiqueta, caseSensitive: false);
            $etiqueta = Str::replace('@codigoproveedor@', ' ', $etiqueta, caseSensitive: false);
            $etiqueta = Str::replace('@numerorecepcion@', ' ', $etiqueta, caseSensitive: false);
        }

        return ArticuloEtiquetaZplSupport::normalizarCodigoFinal($etiqueta);
    }

    public function crear(Request $request)
    {
        can('crear-articulos');

        $categoria = Categoria::orderBy('nombre')->get();
        $subcategoria = Subcategoria::orderBy('nombre')->get();
        $linea = Linea::where('nombre', '!=', '')->orderBy('nombre')->get();
        $marca = Mventa::orderBy('nombre')->get();
        $unidadmedida = Unidadmedida::orderBy('nombre')->get();
        $usosArticulos = Usoarticulo::all();
        $tiposArticulos = Tipoarticulo::all();
        $deposito_query = Depmae::query()->paraUsuarioAutorizado()->orderByRaw('CAST(codigo AS UNSIGNED) ASC')->get();
        $oficinacompra_query = $this->oficinacompraRepository->all();
        $periodicidadcompra_query = $this->periodicidadcompraRepository->all();
        $condicionentrega_query = $this->condicionentregaRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $referer = request()->headers->get('referer');
        $tipoimputacion_enum = Articulo_Cuentacontable::$enumTipoImputacion;
        $nofactura_enum = Articulo_Estado::$enumNoFactura;
        $codimp = Impuesto::all();
        $estado_enum = Articulo_Estado::$enumEstado;
        $tipoproducto_query = Tipoproducto::orderBy('nombre')->get();
        $capacidad_query = (config('app.empresa') === 'FRASLE')
                            ? Capacidad::orderBy('nombre')->get()
                            : collect();
        $color_query = (config('app.empresa') === 'FRASLE')
                            ? $this->articuloRepository->leeColores()
                            : collect();
        $tipoliquido_query = (config('app.empresa') === 'FRASLE')
                            ? Tipoliquido::orderBy('nombre')->get()
                            : collect();
        $divide_enum = (config('app.empresa') === 'EL BIERZO')
                            ? Articulo::$enumDivide
                            : [];
        $enviaalarma_enum = (config('app.empresa') === 'EL BIERZO')
                            ? Articulo::$enumEnviaAlarma
                            : [];

        $numeroparte_enum = [
            ['id' => '0', 'nombre' => 'No tiene (a granel)'],
            ['id' => '1', 'nombre' => 'Lleva número de parte'],
        ];

        $producto = ArticuloUltimoCreatePrefill::cargarProductoPrefill();
        $articulo_proveedor_lineas = ArticuloProveedorLineasSupport::lineasParaFormulario($producto);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ArticuloListadoFiltros::class);

        return view('stock.articulo.crear', compact('producto', 'categoria', 'subcategoria', 'linea', 'marca', 'tipoimputacion_enum',
            'unidadmedida', 'usosArticulos', 'oficinacompra_query', 'referer', 'codimp',
            'periodicidadcompra_query', 'condicionentrega_query', 'empresa_query', 'estado_enum',
            'tiposArticulos', 'deposito_query', 'numeroparte_enum', 'nofactura_enum',
            'tipoproducto_query', 'capacidad_query', 'color_query', 'tipoliquido_query',
            'divide_enum', 'enviaalarma_enum', 'articulo_proveedor_lineas', 'filtrosQuery'));
    }

    public function guardar(ValidacionArticulo $request)
    {
        can('crear-articulos');

        $nombre_foto = $request->sku;
        if ($foto = Articulo::setFoto($request, $nombre_foto)) {
            $request->request->add(['foto' => $foto]);
        }

        $estado_enum = Articulo_Estado::$enumEstado;
        $mventa = Mventa::where('id', $request->mventa_id)->first();
        $linea = Linea::where('id', $request->linea_id)->first();
        $data = $request->all();
        $data['fl_precio_promedio_transferencia'] = $request->boolean('fl_precio_promedio_transferencia');

        DB::beginTransaction();
        try {
            $data['foto'] = ($nombre_foto != null ? $nombre_foto.'.jpg' : null);

            // Crea el articulo
            $articulo = Articulo::create($data);

            // Guarda tablas asociadas
            if ($articulo) {
                // Crea estado
                $data['estadofechas'][] = Carbon::now();
                $data['estados'][] = $estado_enum[0]['nombre'];
                $data['estadoobservaciones'][] = 'Alta de Artículo';
                $data['estadousuarios'][] = Auth::user()->id;

                $articulo_estado = $this->articulo_estadoRepository->create($data, $articulo->id);
                $articulo_cuentacontable = $this->articulo_cuentacontableRepository->create($data, $articulo->id);
                $articulo_archivo = $this->articulo_archivoRepository->create($request, $articulo->id);

                if (can('actualizar-compras-articulos', false)) {
                    $this->articulo_proveedorRepository->syncFromRequest($data, (int) $articulo->id);
                }
            }

            $producto = $this->articuloRepository->find($articulo->id);

            // Graba anita
            $Articulo = new Articulo;
            $anita = $Articulo->guardarAnita($producto);

            if (isset($anita['error'])) {
                if ($anita['error'] == 'Error') {
                    throw new Exception('Error en grabacion anita. '.$anita['mensaje']);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['errores' => $e->getMessage()];
        }

        // Envia correo de alta del articulo
        if (config('articulo.ENVIA_MAIL_ALTA_ARTICULO') == 'SI') {
            // Busca el uso del articulo para enviar mail
            $destinatario = config('articulo.DESTINATARIO_ALTA_ARTICULO');

            foreach ($destinatario as $destino) {
                if ($destino['uso'] == $data['usoarticulo_id']) {
                    $receivers = $destino['destinatarios'];

                    Mail::to($receivers)->send(new AltaArticulo($request->all(), $mventa->nombre ?? '', $linea->nombre ?? ''));
                }
            }
        }

        return redirect()->route('articulo', QueryRetornoListado::desdeRequest($request, ArticuloListadoFiltros::class))
            ->with('status', 'Producto creado');
    }

    public function editar(Request $request, $id, $type = null, $filtros = null)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            if (! ArticuloConsultaDesdeModal::puedeConsultar()) {
                abort(403);
            }
        } else {
            can('editar-articulos');
        }

        $producto = $this->articuloRepository->find($id);
        $puedeActualizarArticulo = can('actualizar-articulos', false);

        $categoria = Categoria::orderBy('nombre')->get();
        $subcategoria = Subcategoria::orderBy('nombre')->get();
        $unidadmedida = Unidadmedida::orderBy('nombre')->get();
        $marca = Mventa::orderBy('nombre')->get();
        $linea = Linea::orderBy('nombre')->get();
        $usosArticulos = Usoarticulo::all();
        $tiposArticulos = Tipoarticulo::all();
        $codimp = Impuesto::all();
        $deposito_query = Depmae::query()->paraUsuarioAutorizado()->orderByRaw('CAST(codigo AS UNSIGNED) ASC')->get();
        $oficinacompra_query = $this->oficinacompraRepository->all();
        $periodicidadcompra_query = $this->periodicidadcompraRepository->all();
        $condicionentrega_query = $this->condicionentregaRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $tipoimputacion_enum = Articulo_Cuentacontable::$enumTipoImputacion;

        $referer = $request->headers->get('referer');
        $ocultarVolver = $soloConsulta;
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ArticuloListadoFiltros::class);

        $nofactura_enum = Articulo_Estado::$enumNoFactura;
        $estado_enum = Articulo_Estado::$enumEstado;
        $tipoproducto_query = Tipoproducto::orderBy('nombre')->get();
        $capacidad_query = (config('app.empresa') === 'FRASLE')
                            ? Capacidad::orderBy('nombre')->get()
                            : collect();
        $color_query = (config('app.empresa') === 'FRASLE')
                            ? $this->articuloRepository->leeColores()
                            : collect();
        $tipoliquido_query = (config('app.empresa') === 'FRASLE')
                            ? Tipoliquido::orderBy('nombre')->get()
                            : collect();
        $divide_enum = (config('app.empresa') === 'EL BIERZO')
                            ? Articulo::$enumDivide
                            : [];
        $enviaalarma_enum = (config('app.empresa') === 'EL BIERZO')
                            ? Articulo::$enumEnviaAlarma
                            : [];
        $numeroparte_enum = [
            ['id' => '0', 'nombre' => 'No tiene (a granel)'],
            ['id' => '1', 'nombre' => 'Lleva número de parte'],
        ];

        $articulo_proveedor_lineas = ArticuloProveedorLineasSupport::lineasParaFormulario($producto);
        $partesUnicasTotal = (string) ($producto->numeroparte ?? '0') === '1'
            ? app(ArticuloParteUnicaService::class)->contarPorArticulo((int) $producto->id)
            : 0;

        return view('stock.articulo.editar', compact('producto', 'id', 'categoria', 'marca', 'linea', 'subcategoria',
            'usosArticulos', 'codimp', 'empresa_query', 'referer', 'estado_enum',
            'unidadmedida', 'filtros', 'nofactura_enum', 'tiposArticulos',
            'periodicidadcompra_query', 'condicionentrega_query', 'tipoimputacion_enum',
            'deposito_query', 'numeroparte_enum', 'oficinacompra_query',
            'divide_enum', 'enviaalarma_enum',
            'tipoproducto_query', 'capacidad_query', 'color_query', 'tipoliquido_query',
            'puedeActualizarArticulo', 'ocultarVolver', 'soloConsulta',
            'articulo_proveedor_lineas', 'partesUnicasTotal', 'filtrosQuery'));
    }

    public function actualizar(ValidacionArticulo $request, $id)
    {
        can('actualizar-articulos');

        $nombre_foto = $request->sku;
        if ($foto = Articulo::setFoto($request, $nombre_foto)) {
            $request->request->add(['foto' => $foto]);
        }

        $data = $request->all();
        $data['fl_precio_promedio_transferencia'] = $request->boolean('fl_precio_promedio_transferencia');

        DB::beginTransaction();
        try {
            $data['foto'] = ($nombre_foto != null ? $nombre_foto.'.jpg' : null);

            $articulo = Articulo::findOrFail($data['articulo_id'])->update($data);

            $articulo_estado = $this->articulo_estadoRepository->update($data, $id);
            $articulo_cuentacontable = $this->articulo_cuentacontableRepository->update($data, $id);

            $articulo_archivo = $this->articulo_archivoRepository->update($request, $id);

            if (can('actualizar-compras-articulos', false)) {
                $this->articulo_proveedorRepository->syncFromRequest($data, (int) $id);
            }

            // Lee nuevo precio con relaciones para interface Anita
            $producto = Articulo::with('categorias')->with('subcategorias')->with('lineas')->with('mventas')->with('impuestos')
                ->with('unidadesdemedidas')->with('unidadesdemedidasalternativas')->with('cuentascontablesventas')
                ->with('cuentascontablescompras')->with('cuentascontablesimpinternos')->with('usoarticulos')
                ->with('materiales')->with('tipocortes')->with('punteras')->with('contrafuertes')
                ->with('tipocorteforros')->with('forros')->with('compfondos')->with('articulos_caja')->
                                where('id', $request->id)->get()->first();

            // Actualiza anita
            $Articulo = new Articulo;
            $anita = $Articulo->actualizarAnita($producto, $producto->sku);

            if (isset($anita['error'])) {
                if (str_contains($anita['error'], 'Error')) {
                    throw new Exception('Error en grabacion anita. '.$anita['mensaje']);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()
                ->back()
                ->withInput()
                ->with('errores', [$e->getMessage()]);
        }

        if ($request->input('origen') === 'modal_consulta') {
            return redirect()
                ->route('editar_articulo', [
                    'id' => $id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ])
                ->with('status', 'Artículo actualizado con éxito');
        }

        return redirect()->route('articulo', QueryRetornoListado::desdeRequest($request, ArticuloListadoFiltros::class))
            ->with('status', 'Artículo actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-articulos');

        $producto = Articulo::select('sku')->where('id', $id)->first();

        // Elimina anita
        $Articulo = new Articulo;
        $Articulo->eliminarAnita($producto->sku);

        if ($request->ajax()) {
            if (Articulo::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function consultaArticulo(Request $request)
    {
        $columns = ['articulo.id', 'sku', 'descripcion', 'unidadmedida.abreviatura', 'categoria.nombre', 'articulo.unidadmedida_id', 'articulo.categoria_id', 'articulo.subcategoria_id'];
        $columnsOut = ['articulo_id', 'sku', 'descripcion', 'unidadmedida', 'nombrecategoria', 'idunidadmedida', 'categoria_id', 'subcategoria_id'];
        $muestraColumnas = [true, true, true, true, true, false, false, false];

        $listaPrecioReq = $request->input('listaprecio_id');
        $listaPrecioReq = ($listaPrecioReq !== null && $listaPrecioReq !== '') ? (int) $listaPrecioReq : null;
        $listaPrecio = PrecioListaVigenteSupport::resolverListaDesdeRequest($listaPrecioReq);
        $colspanTabla = $listaPrecio['mostrar'] ? 7 : 6;

        $consultaRaw = $request->input('consulta');
        $consulta = is_string($consultaRaw) ? trim($consultaRaw) : '';

        $skuDigitosSufijo = max(0, (int) $request->input('sku_digitos_sufijo', 0));
        $minLen = \App\Support\Ventas\GastronomiaSkuCatalogoSupport::longitudMinimaBusqueda($consulta, $skuDigitosSufijo);

        if (mb_strlen($consulta) < $minLen) {
            $mensaje = '<tr><td colspan="'.$colspanTabla.'" class="text-muted">Ingrese al menos '.$minLen
                .($minLen === 1 ? ' dígito' : ' caracteres').' para buscar.</td></tr>';

            return response()->json([
                'data' => $mensaje,
                'listaprecio_id' => $listaPrecio['id'],
                'listaprecio_nombre' => $listaPrecio['nombre'],
                'mostrar_precio_lista' => $listaPrecio['mostrar'],
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $query = Articulo::select('articulo.id as articulo_id', 'sku', 'descripcion', 'unidadmedida.abreviatura as unidadmedida',
            'categoria.nombre as nombrecategoria', 'articulo.unidadmedida_id as idunidadmedida',
            'articulo.categoria_id as categoria_id', 'articulo.subcategoria_id as subcategoria_id',
            'articulo.depositoentrega_id as depositoentrega_id')
            ->leftJoin('unidadmedida', 'articulo.unidadmedida_id', '=', 'unidadmedida.id')
            ->leftJoin('categoria', 'articulo.categoria_id', '=', 'categoria.id')
            ->leftJoin('linea', 'articulo.linea_id', '=', 'linea.id');

        if (filter_var($request->input('solo_facturable'), FILTER_VALIDATE_BOOLEAN)) {
            $query->where('articulo.nofactura', '0');
        }

        if (filter_var($request->input('solo_insumo_gastronomia'), FILTER_VALIDATE_BOOLEAN)) {
            $insumoId = \App\Support\Stock\ArticuloUsoInsumoSupport::idUsoInsumo();
            if ($insumoId !== null && $insumoId > 0) {
                $query->where('articulo.usoarticulo_id', $insumoId);
            }
        }

        \App\Support\Stock\ArticuloSeleccionOperativaSupport::aplicarSoloActivos($query);

        $cont = count($columns);

        $skuPrefijoRaw = $request->input('sku_prefijo');
        $skuPrefijo = is_string($skuPrefijoRaw) ? trim($skuPrefijoRaw) : '';
        $skuPrefijoUpper = $skuPrefijo !== '' && preg_match('/^[A-Za-z0-9_-]{1,24}$/', $skuPrefijo)
            ? mb_strtoupper($skuPrefijo, 'UTF-8')
            : '';

        if ($skuPrefijoUpper !== '' && $skuDigitosSufijo > 0) {
            \App\Support\Ventas\GastronomiaSkuCatalogoSupport::aplicarScopeFormatoCatalogo(
                $query,
                $skuPrefijoUpper,
                $skuDigitosSufijo,
                'articulo.sku',
            );
            \App\Support\Ventas\GastronomiaSkuCatalogoSupport::aplicarFiltroTerminoCatalogo(
                $query,
                $consulta,
                $skuPrefijoUpper,
                $skuDigitosSufijo,
                'articulo.sku',
            );
        } elseif ($skuPrefijoUpper !== '') {
            $query->whereRaw('UPPER(articulo.sku) LIKE ?', [$skuPrefijoUpper.'%']);
            \App\Support\Ventas\GastronomiaSkuCatalogoSupport::aplicarFiltroTerminoCatalogo(
                $query,
                $consulta,
                $skuPrefijoUpper,
                0,
                'articulo.sku',
            );
        } else {
            $like = '%'.$consulta.'%';
            $query->where(function ($q) use ($columns, $cont, $like) {
                $q->where($columns[0], 'LIKE', $like);
                for ($i = 1; $i < $cont; $i++) {
                    $q->orWhere($columns[$i], 'LIKE', $like);
                }
            });
        }

        $query = $query->orderBy('articulo.descripcion')->limit(250)->get();

        $preciosLista = [];
        if ($listaPrecio['mostrar'] && count($query) > 0) {
            $articuloIds = $query->pluck('articulo_id')->map(fn ($id) => (int) $id)->all();
            $preciosLista = PrecioListaVigenteSupport::vigentesPorArticulos(
                $articuloIds,
                $listaPrecio['id'],
            );
        }

        $output = [];
        $output['data'] = '';
        $output['listaprecio_id'] = $listaPrecio['id'];
        $output['listaprecio_nombre'] = $listaPrecio['nombre'];
        $output['mostrar_precio_lista'] = $listaPrecio['mostrar'];
        $puedeConsultarArticulo = ArticuloConsultaDesdeModal::puedeConsultar();
        $puedeVerKardex = MovimientosArticuloDepositoSupport::puedeConsultar();
        if (count($query) > 0) {
            foreach ($query as $row) {
                $output['data'] .= '<tr>';
                for ($i = 0; $i < $cont; $i++) {
                    if ($muestraColumnas[$i]) {
                        $output['data'] .= '<td class="'.$columnsOut[$i].'">'.$row[$columnsOut[$i]].'</td>';
                    } else {
                        $output['data'] .= '<input type="hidden" class="'.$columnsOut[$i].'" value="'.$row[$columnsOut[$i]].'">';
                    }
                }
                if ($listaPrecio['mostrar']) {
                    $precioFmt = PrecioListaVigenteSupport::formatearPrecioLista(
                        $preciosLista[(int) $row['articulo_id']] ?? null,
                    );
                    $output['data'] .= '<td class="preciolista text-right">'.$precioFmt.'</td>';
                }
                $output['data'] .= '<td>'
                    .'<a class="btn btn-warning btn-sm eligeconsultaarticulo">Elegir</a>';
                if ($puedeConsultarArticulo) {
                    $urlConsulta = ArticuloConsultaDesdeModal::urlEditar((int) $row['articulo_id']);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($urlConsulta).'" target="_blank" rel="noopener">Consultar</a>';
                }
                if ($puedeVerKardex) {
                    $output['data'] .= ' <button type="button" class="btn btn-outline-info btn-sm btn-movimientos-stock-articulo btn-kardex-consulta-articulo"'
                        .' data-articulo-id="'.(int) $row['articulo_id'].'"'
                        .' data-articulo-sku="'.e((string) $row['sku']).'"'
                        .' data-articulo-descripcion="'.e((string) $row['descripcion']).'"'
                        .' data-deposito-id="'.(int) ($row['depositoentrega_id'] ?? 0).'"'
                        .' title="Kardex de stock">'
                        .'<i class="fa fa-list-alt"></i></button>';
                }
                $output['data'] .= ' <button type="button" class="btn btn-outline-secondary btn-sm btn-movimientos-articulo-deposito d-none"'
                    .' data-articulo-id="'.(int) $row['articulo_id'].'"'
                    .' data-articulo-sku="'.e((string) $row['sku']).'"'
                    .' data-articulo-descripcion="'.e((string) $row['descripcion']).'"'
                    .' title="Kardex en el dep&oacute;sito del recuento">'
                    .'<i class="fa fa-list"></i></button>';
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
            }
        } else {
            $output['data'] .= '<tr>';
            $output['data'] .= '<td colspan="'.$colspanTabla.'">Sin resultados</td>';
            $output['data'] .= '</tr>';
        }

        return response()->json($output, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function leeUnArticulo($articulo_id)
    {
        return $this->articuloRepository->find($articulo_id);
    }

    public function precioProveedorArticulo(int $articulo_id, int $proveedor_id)
    {
        if (! can('editar-articulos', false)
            && ! can('listar-articulos', false)
            && ! can('editar-compras-articulos', false)
            && ! can('actualizar-compras-articulos', false)) {
            abort(403);
        }

        $vigente = ArticuloProveedorPrecioListaSupport::precioVigente($articulo_id, $proveedor_id);

        if (! $vigente) {
            return response()->json(['tiene_precio' => false]);
        }

        return response()->json(array_merge($vigente, ['tiene_precio' => true]));
    }

    public function resolverArticuloProveedor(int $proveedor_id, Request $request)
    {
        if (! can('editar-articulos', false)
            && ! can('listar-articulos', false)
            && ! can('editar-compras-articulos', false)
            && ! can('actualizar-compras-articulos', false)
            && ! can('listar-requisicion', false)
            && ! can('crear-ordencompra', false)) {
            abort(403);
        }

        $match = ArticuloProveedorMatchSupport::resolver(
            $proveedor_id,
            $request->query('codigo_articulo_proveedor'),
            $request->query('codigobarra')
        );

        if (! $match) {
            return response()->json(['encontrado' => false]);
        }

        return response()->json(array_merge($match, ['encontrado' => true]));
    }

    public function leeUnArticuloPorSku($sku, Request $request)
    {
        $articulo = $this->articuloRepository->findPorSku($sku);

        if (! \App\Support\Stock\ArticuloSeleccionOperativaSupport::esSeleccionable($articulo)) {
            return response()->json(null);
        }

        if ($articulo && filter_var($request->query('solo_facturable'), FILTER_VALIDATE_BOOLEAN)
            && (string) $articulo->nofactura === '1') {
            return response()->json(null);
        }

        return $articulo;
    }

    public function redondeaCaja($articulo_id, $unidadmedida, $caja, $pieza, $kilo, $descuentoventa_id, $opcion)
    {
        // Lee el articulo
        $articulo = $this->articuloRepository->find($articulo_id);

        $cajaCalculo = $piezaCalculo = $kiloCalculo = 0;
        if ($articulo) {
            $unidadesxenvase = $articulo->unidadesxenvase;
            $peso = $articulo->peso;

            $kilo = floatval($kilo);
            $peso = floatval($peso);
            $caja = floatval($caja);

            switch ($opcion) {
                case 1: // ingresa cajas
                    $cajaCalculo = $caja;
                    $piezaCalculo = $cajaCalculo * $unidadesxenvase;
                    $kiloCalculo = $piezaCalculo * $peso;
                    break;
                case 2: // ingresa unidades
                    // Convierte unidades a cajas
                    if (strtoupper($unidadmedida) == 'CAJ' || strtoupper($unidadmedida) == 'CJ' || strtoupper($unidadmedida) == 'C') {
                        self::redondeaPieza($pieza, $peso, $unidadesxenvase, $cajaCalculo, $piezaCalculo, $kiloCalculo);
                    } else {
                        $piezaCalculo = $pieza;
                        $kiloCalculo = $pieza * $peso;

                        if ($peso != 0 && $unidadesxenvase != 0) {
                            $cajaCalculo = $kiloCalculo / $peso / $unidadesxenvase;
                        } else {
                            $cajaCalculo = 0;
                        }
                    }
                    break;
                case 3: // ingresa kilos
                    // Convierte los kilos a cajas
                    if (strtoupper($unidadmedida) == 'CAJ' || strtoupper($unidadmedida) == 'CJ' || strtoupper($unidadmedida) == 'C') {
                        if ($peso != 0 && $unidadesxenvase != 0) {
                            $cajas = $kilo / $peso / $unidadesxenvase;
                        } else {
                            $cajas = 0;
                        }

                        // Si el resto no da 0 ajusta a la siguiente caja
                        if (abs($cajas - floor($cajas)) < 0.0000001) {
                            $cajaCalculo = $cajas;
                        } else {
                            $cajaCalculo = floor($cajas) + 1;
                        }

                        $piezaCalculo = $cajaCalculo * $unidadesxenvase;
                        $kiloCalculo = $piezaCalculo * $peso;
                    } else {
                        $kiloCalculo = $kilo;

                        if ($peso != 0) {
                            $piezaCalculo = $kilo / $peso;

                            // Redondea piezas
                            if (abs($piezaCalculo - floor($piezaCalculo)) > 0.0001) {
                                $piezaCalculo = floor($piezaCalculo) + 1;
                            }
                        }

                        if ($unidadesxenvase != 0) {
                            $cajaCalculo = $piezaCalculo / $unidadesxenvase;
                        }
                    }
                    break;
            }
        }

        // Agrega el descuento
        if ($descuentoventa_id > 0) {
            if ($piezaCalculo != 0) {
                $descuentoventa = $this->descuentoventaRepository->find($descuentoventa_id);

                if ($descuentoventa) {
                    if ($descuentoventa->tipodescuento == 'POR CANTIDAD VENDIDA') {
                        $cantidadVenta = $descuentoventa->cantidadventa;
                        $cantidadDescuento = $descuentoventa->cantidaddescuento;

                        // Calcula el descuento
                        $piezaDescuento = ($piezaCalculo / $cantidadVenta) * $cantidadDescuento;

                        // Lo suma a las piezas calculadas
                        $pieza = $piezaCalculo + $piezaDescuento;

                        // Redondea las piezas a caja
                        if (strtoupper($unidadmedida) == 'CAJ' || strtoupper($unidadmedida) == 'CJ' || strtoupper($unidadmedida) == 'C') {
                            self::redondeaPieza($pieza, $peso, $unidadesxenvase, $cajaCalculo, $piezaCalculo, $kiloCalculo);
                        } else {
                            $piezaCalculo = $pieza;
                            $kiloCalculo = $piezaCalculo * $peso;

                            if ($unidadesxenvase != 0) {
                                $cajaCalculo = $piezaCalculo / $unidadesxenvase;
                            }
                        }
                    }
                }
            }
        }

        return ['caja' => $cajaCalculo, 'pieza' => $piezaCalculo, 'kilo' => $kiloCalculo];
    }

    private function redondeaPieza($pieza, $peso, $unidadesxenvase, &$cajaCalculo, &$piezaCalculo, &$kiloCalculo)
    {
        $kilo = $pieza * $peso;
        if ($peso != 0 && $unidadesxenvase != 0) {
            $cajas = $kilo / $peso / $unidadesxenvase;
        } else {
            $cajas = 0;
        }

        // Si el resto no da 0 ajusta a la siguiente caja
        if (abs($cajas - floor($cajas)) < 0.0000001) {
            $cajaCalculo = $cajas;
        } else {
            $cajaCalculo = floor($cajas) + 1;
        }

        $piezaCalculo = $cajaCalculo * $unidadesxenvase;
        $kiloCalculo = $piezaCalculo * $peso;
    }

    public function leerHistoriaArticulo($articulo_id)
    {
        return $this->articulo_estadoRepository->leeHistoriaArticulo($articulo_id);
    }

    public function actualizaEstadoArticulo($estadoarticulo, $articulo_id)
    {
        DB::beginTransaction();
        try {
            $data = [];
            $data['estado'] = $estadoarticulo;

            $articulo = Articulo::findOrFail($articulo_id)->update($data);

            // Crea estado
            if (isset($estadoarticulo)) {
                $data = [];
                $data['estadofechas'][] = Carbon::now();
                $data['usuario_ids'][] = Auth::user()->id;

                if ($estadoarticulo == 'INACTIVO') {
                    $data['estadoobservaciones'][] = 'Inactivación de Artículo';
                    $data['estados'][] = Articulo_Estado::$enumEstado[array_search('I', array_column(Articulo_Estado::$enumEstado, 'valor'))]['nombre'];
                } else {
                    $data['estadoobservaciones'][] = 'Activación de Artículo';
                    $data['estados'][] = Articulo_Estado::$enumEstado[array_search('A', array_column(Articulo_Estado::$enumEstado, 'valor'))]['nombre'];
                }

                $data['estadousuarios'][] = Auth::user()->id;

                $articulo_estado = $this->articulo_estadoRepository->create($data, $articulo_id);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }
    }

    // En base a una cuenta ingresada, verificar el resto de las empresas asignadas y las genera

    public function replicarCuentaContableArticulo($empresa_id, $tipoimputacion, $cuentacontable_id)
    {
        $empresa_query = $this->empresaRepository->allFiltrado();

        $cuentacontable = $this->cuentacontableRepository->find($cuentacontable_id);

        if ($cuentacontable) {
            $codigoCuentaContable = $cuentacontable->codigo;
        }

        $arrayCuenta = [];
        foreach ($empresa_query as $empresa) {
            // Solo crea las empresas distintas a la empresa del parametro
            if ($empresa->id != $empresa_id) {
                // busca la cuenta en la otra empresa
                $cuentacontable = $this->cuentacontableRepository->findPorCodigo($empresa->id, $codigoCuentaContable);

                if ($cuentacontable) {
                    $arrayCuenta[] = [
                        'empresa_id' => $empresa->id,
                        'tipoimputacion' => $tipoimputacion,
                        'cuentacontable_id' => $cuentacontable->id,
                        'codigocuentacontable' => $cuentacontable->codigo,
                        'nombrecuentacontable' => $cuentacontable->nombre,
                    ];
                }
            }
        }

        return $arrayCuenta;
    }
}
