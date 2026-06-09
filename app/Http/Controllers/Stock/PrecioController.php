<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\PrecioExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPrecio;
use App\Imports\Stock\PrecioImport;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Models\Stock\Talle;
use App\Queries\Stock\PrecioQueryInterface;
use App\Repositories\Stock\ArticuloRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Services\Stock\PrecioService;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        $f = $this->precioQuery->resolverFiltrosDesdeRequest($request);
        $listasPrecio = $this->listasPrecioParaFiltro();
        $datas = $this->precioQuery->leePrecios(
            $f['fecha_vigencia'],
            $f['listaprecio_id'],
            $f['filtros'],
            $f['busqueda'] !== '' ? $f['busqueda'] : null,
            true
        );

        return view('stock.precio.index', [
            'datas' => $datas,
            'fechaVigenciaFiltro' => $f['fecha_vigencia'],
            'listaprecioIdFiltro' => $f['listaprecio_id'],
            'listasPrecio' => $listasPrecio,
            'filtrosParaVista' => $f['filtros'],
            'busqueda' => $f['busqueda'],
        ]);
    }

    public function listar(Request $request, ?string $formato = null)
    {
        can('listar-precios');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $f = $this->precioQuery->resolverFiltrosDesdeRequest($request);

        switch ($formato) {
            case 'PDF':
                $precios = $this->precioQuery->leePrecios(
                    $f['fecha_vigencia'],
                    $f['listaprecio_id'],
                    $f['filtros'],
                    $f['busqueda'] !== '' ? $f['busqueda'] : null,
                    false
                );
                $fechaReferencia = $f['fecha_vigencia'];
                $view = \View::make('stock.precio.listado', compact('precios', 'fechaReferencia'))->render();
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
                    ->parametros($f['fecha_vigencia'], $f['listaprecio_id'], $f['filtros'], $f['busqueda'])
                    ->download('precios.xlsx');

            case 'CSV':
                return (new PrecioExport($this->precioQuery))
                    ->parametros($f['fecha_vigencia'], $f['listaprecio_id'], $f['filtros'], $f['busqueda'])
                    ->download('precios.csv', ExcelFormat::CSV);
        }

        if (! Precio::query()->exists()) {
            (new Precio)->sincronizarConAnita();
        }

        $listasPrecio = $this->listasPrecioParaFiltro();
        $datas = $this->precioQuery->leePrecios(
            $f['fecha_vigencia'],
            $f['listaprecio_id'],
            $f['filtros'],
            $f['busqueda'] !== '' ? $f['busqueda'] : null,
            true
        );

        return view('stock.precio.index', [
            'datas' => $datas,
            'fechaVigenciaFiltro' => $f['fecha_vigencia'],
            'listaprecioIdFiltro' => $f['listaprecio_id'],
            'listasPrecio' => $listasPrecio,
            'filtrosParaVista' => $f['filtros'],
            'busqueda' => $f['busqueda'],
        ]);
    }

    private function listasPrecioParaFiltro()
    {
        return Listaprecio::query()
            ->orderByRaw('CAST(codigo AS UNSIGNED) ASC')
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
        $precio = $this->precioService->crearDesdeFormulario($request);

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

        $resultado = $this->precioService->actualizarDesdeFormulario($request, (int) $id);
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

    public function importar(Request $request)
    {
        $this->validate(request(), [
            'file' => 'required|mimetypes::'.
                'application/vnd.ms-office,'.
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'.
                'application/vnd.ms-excel',
        ]);

        $rowEncabezado = 1;
        $headings = (new HeadingRowImport($rowEncabezado))->toArray(request('file'));

        try {
            set_time_limit(0);

            DB::beginTransaction();
            Excel::import(new PrecioImport(request('fechavigencia'), request('moneda_id'), $headings), request('file'));
            DB::commit();

            return back()
                ->with('mensaje', 'Precios importados correctamente');
        } catch (\Exception $exception) {
            DB::rollBack();

            return back()
                ->with('mensaje', $exception->getMessage());
        }
    }

    public function limpiafiltro(Request $request)
    {
        session()->forget('filtrosPrecios');

        return json_encode(['ok']);
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
