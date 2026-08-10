<?php

namespace App\Http\Controllers\Stock;

use App\Support\Database\SqlDialectSupport;
use App\Exports\Stock\PrecioExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPrecio;
use App\Http\Requests\ValidacionPrecioActualizacionCategoria;
use App\Imports\Stock\PrecioImport;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Models\Stock\Talle;
use App\Queries\Stock\PrecioQueryInterface;
use App\Repositories\Stock\ArticuloRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Services\Stock\PrecioActualizacionCategoriaService;
use App\Services\Stock\PrecioImportPreviewService;
use App\Services\Stock\PrecioService;
use App\Support\Stock\PrecioImportColumnasSupport;
use App\Support\Stock\PrecioListadoFiltros;
use App\Models\Stock\Categoria;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;

class PrecioController extends Controller
{
    protected $precioService;

    private $clienteRepository;

    private $articuloRepository;

    public function __construct(
        PrecioService $precioservice,
        ArticuloRepositoryInterface $articulorepository,
        ClienteRepositoryInterface $clienterepository,
        private PrecioQueryInterface $precioQuery,
        private PrecioActualizacionCategoriaService $precioActualizacionCategoriaService,
        private PrecioImportPreviewService $precioImportPreviewService,
    ) {
        $this->precioService = $precioservice;
        $this->articuloRepository = $articulorepository;
        $this->clienteRepository = $clienterepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-precios');

        if (! Precio::query()->exists()) {
            (new Precio)->sincronizarConAnita();
        }

        $filtros = PrecioListadoFiltros::resolverDesdeRequest($request);
        $listasPrecio = $this->listasPrecioParaFiltro();
        $datas = $this->precioQuery->leePrecios($filtros, true);

        return view('stock.precio.index', [
            'datas' => $datas,
            'listasPrecio' => $listasPrecio,
            'filtros' => $filtros,
            'filtrosQuery' => PrecioListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => PrecioListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, ?string $formato = null)
    {
        can('listar-precios');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = PrecioListadoFiltros::resolverDesdeRequest($request);

        switch ($formato) {
            case 'PDF':
                $precios = $this->precioQuery->leePrecios($filtros, false);
                $fechaReferencia = $filtros['fecha_vigencia'];
                $listasPrecio = $this->listasPrecioParaFiltro();
                $subtituloFiltros = PrecioListadoFiltros::subtituloExport($filtros, $listasPrecio);
                $view = \View::make('stock.precio.listado', compact(
                    'precios',
                    'fechaReferencia',
                    'filtros',
                    'listasPrecio',
                    'subtituloFiltros'
                ))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_precios';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new PrecioExport($this->precioQuery))
                    ->parametros($filtros, $this->listasPrecioParaFiltro())
                    ->download('precios.xlsx');

            case 'CSV':
                return (new PrecioExport($this->precioQuery))
                    ->parametros($filtros, $this->listasPrecioParaFiltro())
                    ->download('precios.csv', ExcelFormat::CSV);
        }

        if (! Precio::query()->exists()) {
            (new Precio)->sincronizarConAnita();
        }

        $listasPrecio = $this->listasPrecioParaFiltro();
        $datas = $this->precioQuery->leePrecios($filtros, true);

        return view('stock.precio.index', [
            'datas' => $datas,
            'listasPrecio' => $listasPrecio,
            'filtros' => $filtros,
            'filtrosQuery' => PrecioListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => PrecioListadoFiltros::CAMPOS,
        ]);
    }

    private function listasPrecioParaFiltro()
    {
        return Listaprecio::query()
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);
    }

    public function asignaPrecioPorTalle($articulo_id, $talle_id)
    {
        $fechaHoy = Carbon::now();

        $talle_id = preg_replace('([^A-Za-z0-9,])', '', $talle_id);
        $array_talle = explode(',', $talle_id);
        $array_precio = [];
        if ($talle_id) {
            $talle = Talle::select('nombre', 'id')->whereIn('id', $array_talle)->get();

            foreach ($talle as $value) {
                $precio = $this->precioService->asignaPrecio($articulo_id, $value->id, $fechaHoy);

                if (count($precio) > 0) {
                    $precio_talle = $precio[0]['precio'];
                    $listaprecio_id = $precio[0]['listaprecio_id'];
                    $moneda_id = $precio[0]['moneda_id'];
                    $incluyeimpuesto = $precio[0]['incluyeimpuesto'];
                } else {
                    $precio_talle = 0;
                    $listaprecio_id = 0;
                    $moneda_id = 1;
                    $incluyeimpuesto = 1;
                }

                $array_precio[] = [
                    'precio' => $precio_talle,
                    'listaprecio_id' => $listaprecio_id,
                    'moneda_id' => $moneda_id,
                    'incluyeimpuesto' => $incluyeimpuesto,
                ];
            }
        }

        return $array_precio;
    }

    public function asignaPrecioPorCliente($articulo_id, $codigocliente)
    {
        $fechaHoy = Carbon::now();

        $cliente = $this->clienteRepository->findPorCodigo($codigocliente);

        $listaprecio_id = config('precio.listaprecio_default_id');

        // Asigna la lista del cliente, o deja lista precio default
        if ($cliente) {
            if ($cliente->listaprecio_id != null) {
                $listaprecio = Listaprecio::find($cliente->listaprecio_id);

                if ($listaprecio) {
                    $listaprecio_id = $cliente->listaprecio_id;
                }
            }
        }

        $precio = $this->precioService->asignaPrecioPorLista($articulo_id, $listaprecio_id, $fechaHoy);

        if (count($precio) > 0) {
            $precio_talle = $precio[0]['precio'];
            $listaprecio_id = $precio[0]['listaprecio_id'];
            $moneda_id = $precio[0]['moneda_id'];
            $incluyeimpuesto = $precio[0]['incluyeimpuesto'];
        } else {
            $precio_talle = 0;
            $listaprecio_id = 0;
            $moneda_id = 1;
            $incluyeimpuesto = 1;
        }

        $array_precio[] = [
            'precio' => $precio_talle,
            'listaprecio_id' => $listaprecio_id,
            'moneda_id' => $moneda_id,
            'incluyeimpuesto' => $incluyeimpuesto,
        ];

        return $array_precio;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear(Request $request)
    {
        can('crear-precios');
        $listaprecio_query = Listaprecio::all();
        $moneda_query = Moneda::all();

        $esEdicion = false;
        $retornoArticuloPrecios = $this->resolverRetornoArticuloPrecios($request);

        $precio = new Precio();
        $articuloId = (int) $request->query('articulo_id', 0);
        if ($articuloId <= 0 && $retornoArticuloPrecios !== null) {
            $articuloId = $retornoArticuloPrecios['articulo_id'];
        }
        if ($articuloId > 0) {
            $articulo = Articulo::query()
                ->select('id', 'sku', 'descripcion', 'detalle')
                ->find($articuloId);
            if ($articulo) {
                $precio->articulo_id = $articulo->id;
                $precio->setRelation('articulos', $articulo);
            }
        }

        $listaprecioId = (int) $request->query('listaprecio_id', 0);
        if ($listaprecioId > 0) {
            $listaprecio = Listaprecio::find($listaprecioId);
            if ($listaprecio) {
                $precio->listaprecio_id = $listaprecio->id;
                $precio->setRelation('listaprecios', $listaprecio);
            }
        }

        return view('stock.precio.crear', compact(
            'listaprecio_query',
            'moneda_query',
            'esEdicion',
            'retornoArticuloPrecios',
            'precio'
        ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPrecio $request)
    {
        try {
            $precio = $this->precioService->crearDesdeFormulario($request);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('mensaje', $e->getMessage());
        }

        // Lee nuevo precio con relaciones para interface Anita
        $precio = Precio::where('id', $precio->id)->with('articulos:id,descripcion,sku')->with('listaprecios')->with('monedas')->with('usuarios')->first();

        // Graba anita
        // $Precio = new Precio();
        // $Precio->guardarAnita($precio);

        return $this->redirectDespuesDeGuardarPrecio($request, 'Precio creado con exito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar(Request $request, $id)
    {
        can('editar-precios');

        $precio = Precio::where('id', $id)->with('articulos:id,descripcion,detalle,sku')->with('listaprecios')->with('monedas')->with('usuarios')->first();
        $listaprecio_query = Listaprecio::all();
        $moneda_query = Moneda::all();

        $esEdicion = true;
        $retornoArticuloPrecios = $this->resolverRetornoArticuloPrecios($request);

        return view('stock.precio.editar', compact('precio', 'listaprecio_query', 'moneda_query', 'esEdicion', 'retornoArticuloPrecios'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPrecio $request, $id)
    {
        can('actualizar-precios');

        try {
            $resultado = $this->precioService->actualizarDesdeFormulario($request, (int) $id);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('mensaje', $e->getMessage());
        }
        $precio = $resultado['precio'];

        // Lee precio con relaciones para interface Anita
        $precio = Precio::where('id', $precio->id)->with('articulos:id,descripcion,detalle,sku')->with('listaprecios')->with('monedas')->with('usuarios')->first();

        // Actualiza anita
        // $Precio = new Precio();
        // $Precio->actualizarAnita($precio);

        $mensaje = $resultado['creado_nueva_vigencia']
            ? 'Se registró una nueva vigencia de precio; el registro anterior se conservó en el historial.'
            : 'Precio actualizado con exito';

        return $this->redirectDespuesDeGuardarPrecio($request, $mensaje);
    }

    /**
     * Historial de precios de venta del artículo en todas las listas donde figura (JSON).
     */
    public function consultaPreciosArticulo(Request $request)
    {
        if (! can('listar-precios', false) && ! can('listar-articulos', false)) {
            return response()->json(['message' => 'No tiene permisos para esta consulta.'], 403);
        }

        $articuloId = (int) $request->query('articulo_id', 0);
        if ($articuloId <= 0) {
            return response()->json(['message' => 'Indique un artículo válido.'], 422);
        }

        $articulo = Articulo::query()->select('id', 'sku', 'descripcion')->find($articuloId);
        if (! $articulo) {
            return response()->json(['message' => 'Artículo no encontrado.'], 404);
        }

        $fechaRef = $request->query('fecha_referencia');
        if (! is_string($fechaRef) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaRef)) {
            $fechaRef = Carbon::today()->format('Y-m-d');
        }

        $listaprecioId = (int) $request->query('listaprecio_id', 0);
        $listaprecioId = $listaprecioId > 0 ? $listaprecioId : null;

        $data = $this->precioQuery->leeHistorialPreciosArticulo($articuloId, $fechaRef, $listaprecioId);

        $puedeEditar = can('editar-precios', false);
        foreach ($data['filas'] as &$fila) {
            $fila['puede_editar'] = $puedeEditar;
            if ($puedeEditar) {
                $fila['editar_url'] = route('editar_precio', ['id' => $fila['id']]);
            }
        }
        unset($fila);

        $data['puede_crear'] = can('crear-precios', false);
        if ($data['puede_crear']) {
            $data['crear_url'] = route('crear_precio');
        }

        return response()->json($data);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-precios');

        $precio = Precio::where('id', $id)->with('articulos:id,descripcion,detalle,sku')->with('listaprecios')->with('monedas')->with('usuarios')->first();

        // Elimina anita
        // $Precio = new Precio();
        // $Precio->eliminarAnita($precio->articulos->sku, $precio->listaprecios->codigo);

        if ($request->ajax()) {
            if (Precio::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function crearImportacion()
    {
        can('crear-precios');

        $listaprecio_query = Listaprecio::all();
        $moneda_query = Moneda::all();

        return view('stock.precio.crearimportacion', compact('listaprecio_query', 'moneda_query'));
    }

    public function previewImportacion(Request $request)
    {
        can('crear-precios');

        $formato = (string) $request->input('formato', PrecioImportColumnasSupport::FORMATO_SIMPLE);

        $request->validate([
            'file' => 'required|file|mimetypes:application/vnd.ms-office,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel',
            'formato' => 'required|in:'.PrecioImportColumnasSupport::FORMATO_SIMPLE.','.PrecioImportColumnasSupport::FORMATO_LISTAS,
            'listaprecio_id' => 'nullable|integer|exists:listaprecio,id',
            'col_sku' => 'nullable|string|max:100',
            'col_descripcion' => 'nullable|string|max:100',
            'col_precio' => 'nullable|string|max:100',
            'fila_encabezado' => 'nullable|integer|min:1|max:50',
            'hoja_indice' => 'nullable|integer|min:1|max:50',
        ]);

        $preview = $this->precioImportPreviewService->previsualizar(
            $request->file('file'),
            $formato,
            $formato === PrecioImportColumnasSupport::FORMATO_SIMPLE ? (int) $request->input('listaprecio_id', 0) : null,
            $request->input('col_sku'),
            $request->input('col_descripcion'),
            $request->input('col_precio'),
            $request->filled('fila_encabezado') ? (int) $request->input('fila_encabezado') : null,
            $request->filled('hoja_indice') ? (int) $request->input('hoja_indice') : null
        );

        return response()->json($preview);
    }

    public function importar(Request $request)
    {
        $formato = (string) $request->input('formato', PrecioImportColumnasSupport::FORMATO_SIMPLE);

        $this->validate($request, [
            'file' => 'required|mimetypes:'.
                'application/vnd.ms-office,'.
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'.
                'application/vnd.ms-excel',
            'fechavigencia' => 'required|string',
            'moneda_id' => 'required|integer|exists:moneda,id',
            'formato' => 'required|in:'.PrecioImportColumnasSupport::FORMATO_SIMPLE.','.PrecioImportColumnasSupport::FORMATO_LISTAS,
            'listaprecio_id' => 'required_if:formato,'.PrecioImportColumnasSupport::FORMATO_SIMPLE.'|nullable|integer|exists:listaprecio,id',
            'col_sku' => 'required_if:formato,'.PrecioImportColumnasSupport::FORMATO_SIMPLE.'|nullable|string|max:100',
            'col_descripcion' => 'nullable|string|max:100',
            'col_precio' => 'required_if:formato,'.PrecioImportColumnasSupport::FORMATO_SIMPLE.'|nullable|string|max:100',
            'fila_encabezado' => 'nullable|integer|min:1|max:50',
            'hoja_indice' => 'nullable|integer|min:1|max:50',
        ]);

        $nombresHojas = PrecioImportColumnasSupport::listarNombresHojas($request->file('file'));
        $hojaIndice0 = PrecioImportColumnasSupport::indiceHojaDesdeRequest(
            $request->filled('hoja_indice') ? (int) $request->input('hoja_indice') : null,
            count($nombresHojas)
        );

        $filaEncabezado = PrecioImportColumnasSupport::detectarFilaEncabezado(
            $request->file('file'),
            $request->filled('fila_encabezado') ? (int) $request->input('fila_encabezado') : null,
            $hojaIndice0
        );

        $headings = null;
        if ($formato === PrecioImportColumnasSupport::FORMATO_LISTAS) {
            $headingsPorHoja = (new HeadingRowImport($filaEncabezado))->toArray($request->file('file'));
            $headings = $headingsPorHoja[$hojaIndice0] ?? null;
        }

        try {
            set_time_limit(0);

            DB::beginTransaction();

            $import = new PrecioImport(
                (string) $request->input('fechavigencia'),
                (int) $request->input('moneda_id'),
                $headings,
                $formato,
                $formato === PrecioImportColumnasSupport::FORMATO_SIMPLE ? (int) $request->input('listaprecio_id') : null,
                $request->input('col_sku'),
                $request->input('col_descripcion'),
                $request->input('col_precio'),
                $filaEncabezado,
                $hojaIndice0,
            );

            Excel::import($import, $request->file('file'));
            DB::commit();

            $listaprecioNombre = null;
            $listaprecioId = $formato === PrecioImportColumnasSupport::FORMATO_SIMPLE
                ? (int) $request->input('listaprecio_id')
                : null;
            if ($listaprecioId > 0) {
                $listaprecioNombre = Listaprecio::query()->whereKey($listaprecioId)->value('nombre');
            }

            $fechavigencia = (string) $request->input('fechavigencia');
            try {
                $fechavigenciaFmt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechavigencia)
                    ? Carbon::createFromFormat('Y-m-d', $fechavigencia)->format('d/m/Y')
                    : Carbon::createFromFormat('d-m-Y', $fechavigencia)->format('d/m/Y');
            } catch (\Throwable $e) {
                $fechavigenciaFmt = $fechavigencia;
            }

            $resultado = array_merge($import->resumen(), [
                'fila_encabezado' => $import->filaEncabezadoUsada(),
                'hoja_indice' => $hojaIndice0 + 1,
                'hoja_nombre' => $nombresHojas[$hojaIndice0] ?? null,
                'fechavigencia' => $fechavigenciaFmt,
                'listaprecio_id' => $listaprecioId,
                'listaprecio_nombre' => $listaprecioNombre,
            ]);

            return back()
                ->withInput()
                ->with('precio_import_resultado', $resultado);
        } catch (\Exception $exception) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('mensaje-error', $exception->getMessage());
        }
    }

    public function limpiafiltro(Request $request)
    {
        session()->forget('filtrosPrecios');

        return json_encode(['ok']);
    }

    public function actualizarPorCategoria(Request $request)
    {
        can('actualizar-precios');

        $categoria_query = Categoria::query()->orderBy('nombre')->get(['id', 'nombre']);
        $listasPrecio = $this->listasPrecioParaFiltro();
        $fechaReferencia = $request->filled('fecha_referencia')
            ? Carbon::parse($request->fecha_referencia)->format('Y-m-d')
            : Carbon::today()->format('Y-m-d');
        $nuevaFechavigencia = $request->filled('nueva_fechavigencia')
            ? Carbon::parse($request->nueva_fechavigencia)->format('Y-m-d')
            : Carbon::today()->format('Y-m-d');

        return view('stock.precio.actualizar_categoria', [
            'categoria_query' => $categoria_query,
            'listasPrecio' => $listasPrecio,
            'fechaReferencia' => $fechaReferencia,
            'nuevaFechavigencia' => $nuevaFechavigencia,
            'categoriaId' => (int) $request->query('categoria_id', 0),
            'listaprecioId' => $request->filled('listaprecio_id') ? (int) $request->listaprecio_id : null,
            'porcentaje' => $request->query('porcentaje', ''),
        ]);
    }

    public function previewActualizacionCategoria(ValidacionPrecioActualizacionCategoria $request)
    {
        can('actualizar-precios');

        $listaprecioId = $request->filled('listaprecio_id') ? (int) $request->listaprecio_id : null;

        $preview = $this->precioActualizacionCategoriaService->previsualizar(
            (int) $request->categoria_id,
            $listaprecioId,
            $request->fecha_referencia,
            $request->nueva_fechavigencia,
            (float) $request->porcentaje
        );

        return response()->json($preview);
    }

    public function aplicarActualizacionCategoria(ValidacionPrecioActualizacionCategoria $request)
    {
        can('actualizar-precios');

        $listaprecioId = $request->filled('listaprecio_id') ? (int) $request->listaprecio_id : null;

        $resultado = $this->precioActualizacionCategoriaService->aplicar(
            (int) $request->categoria_id,
            $listaprecioId,
            $request->fecha_referencia,
            $request->nueva_fechavigencia,
            (float) $request->porcentaje
        );

        $mensaje = 'Se registraron '.$resultado['creados'].' nuevos precios con la vigencia indicada.';
        if ($resultado['omitidos'] > 0) {
            $mensaje .= ' Se omitieron '.$resultado['omitidos'].' registros (sin cambio, precio inválido o vigencia ya existente).';
        }

        return redirect()
            ->route('precio', [
                'fecha_vigencia' => $request->nueva_fechavigencia,
                'listaprecio_id' => $listaprecioId,
            ])
            ->with('mensaje', $mensaje);
    }

    /**
     * @return array{articulo_id: int, origen: string, fecha_referencia: string}|null
     */
    private function resolverRetornoArticuloPrecios(Request $request): ?array
    {
        $articuloId = (int) $request->input('retorno_articulo_id', $request->query('retorno_articulo_id', 0));
        $origen = (string) $request->input('retorno_origen', $request->query('retorno_origen', ''));

        if ($articuloId <= 0 || ! in_array($origen, ['index', 'editar'], true)) {
            return null;
        }

        $fecha = (string) $request->input('retorno_fecha_referencia', $request->query('retorno_fecha_referencia', ''));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = Carbon::today()->format('Y-m-d');
        }

        return [
            'articulo_id' => $articuloId,
            'origen' => $origen,
            'fecha_referencia' => $fecha,
        ];
    }

    private function redirectDespuesDeGuardarPrecio(Request $request, string $mensaje)
    {
        $retorno = $this->resolverRetornoArticuloPrecios($request);
        if ($retorno === null) {
            return redirect('stock/precio')->with('mensaje', $mensaje);
        }

        $params = [
            'abrir_consulta_precios' => 1,
            'articulo_id' => $retorno['articulo_id'],
            'fecha_referencia' => $retorno['fecha_referencia'],
        ];

        $url = $retorno['origen'] === 'editar'
            ? route('editar_articulo', ['id' => $retorno['articulo_id']])
            : route('articulo');

        return redirect($url.'?'.http_build_query($params))->with('mensaje', $mensaje);
    }
}
