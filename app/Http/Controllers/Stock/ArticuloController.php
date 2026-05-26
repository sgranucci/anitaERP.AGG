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
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Stock\Articulo_ArchivoRepositoryInterface;
use App\Repositories\Stock\Articulo_CajaRepositoryInterface;
use App\Repositories\Stock\Articulo_CostoRepositoryInterface;
use App\Repositories\Stock\Articulo_CuentacontableRepositoryInterface;
use App\Repositories\Stock\Articulo_EstadoRepositoryInterface;
use App\Repositories\Stock\ArticuloRepositoryInterface;
use App\Repositories\Ventas\DescuentoventaRepositoryInterface;
use App\Services\Stock\ArticuloAnitaSyncService;
use App\Services\Stock\PrecioService;
use App\Services\Stock\StkdepSaldoAnitaService;
use App\Support\Stock\ArticuloUltimoCreatePrefill;
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

        $busqueda = $request->busqueda;

        $articulos = $this->articuloRepository->leeArticulo($busqueda, true);

        if ($articulos->isEmpty() && config('app.anita_sync_articulo_index')) {
            $Articulo = new Articulo;
            $Articulo->sincronizarConAnita();

            $articulos = $this->articuloRepository->leeArticulo($busqueda, true);
        }

        $saldosStkdep = [];
        try {
            $saldosStkdep = $this->stkdepSaldoAnitaService->saldosStkdepPorArticulosLab($articulos);
        } catch (\Throwable $e) {
            Log::warning('Articulo index: no se pudo consultar saldo stkdep', ['exception' => $e->getMessage()]);
        }

        $datas = [
            'articulos' => $articulos,
            'busqueda' => $busqueda,
            'saldosStkdep' => $saldosStkdep,
        ];

        return view('stock.articulo.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-articulos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch ($formato) {
            case 'PDF':
                $articulos = $this->articuloRepository->leeArticulo($busqueda, false);

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
                    ->parametros($busqueda)
                    ->download('articulo.xlsx');
                break;

            case 'CSV':
                return (new ArticuloExport($this->articuloRepository))
                    ->parametros($busqueda)
                    ->download('articulo.csv', \Maatwebsite\Excel\Excel::CSV);
                break;
        }

        $datas = ['articulo' => $articulo, 'busqueda' => $busqueda];

        return view('stock.articulo.indexp', $datas);
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

    public function download(Request $request, $id)
    {

        $articulo = Articulo::where('id', $id)->first();

        // Arma nombre de archivo
        $nombreEtiqueta = 'tmp/eti-'.Str::random(10).'.txt';

        // Agrega programa enviado a la url completa
        $programa = request()->header('referer');
        $usuario_id = Auth()->id();
        $modeloetiqueta = $this->seteoModeloetiquetaRepository->buscaSeteoModeloetiqueta($usuario_id, $programa);

        $etiqueta = '';
        if ($modeloetiqueta) {
            $etiqueta = $modeloetiqueta->modeloetiquetas->codigoetiqueta;

            // Busca tags para reemplazar
            $etiqueta = Str::replace('@sku@', $articulo->sku, $etiqueta, caseSensitive: false);
            $etiqueta = Str::replace('@npu@', $articulo->numeroparte, $etiqueta, caseSensitive: false);
            $etiqueta = Str::replace('@codigoproveedor@', ' ', $etiqueta, caseSensitive: false);
            $etiqueta = Str::replace('@numerorecepcion@', ' ', $etiqueta, caseSensitive: false);

            Storage::disk('local')->put($nombreEtiqueta, $etiqueta);
            $path = Storage::path($nombreEtiqueta);

            // Trae la impresora
            $seteosalida = $this->seteoSalidaRepository->buscaSeteo($usuario_id, '');
            $comando = sprintf($seteosalida->salidas->comando, $path);
            system($comando);

            Storage::disk('local')->delete($nombreEtiqueta);
        }

        return redirect()->back()->with('status', 'El producto seleccionado se imprimió con exito.');
    }

    public function crear()
    {
        can('crear-articulos');

        $categoria = Categoria::orderBy('nombre')->get();
        $subcategoria = Subcategoria::orderBy('nombre')->get();
        $linea = Linea::where('nombre', '!=', '')->orderBy('nombre')->get();
        $marca = Mventa::orderBy('nombre')->get();
        $unidadmedida = Unidadmedida::orderBy('nombre')->get();
        $usosArticulos = Usoarticulo::all();
        $tiposArticulos = Tipoarticulo::all();
        $deposito_query = Depmae::orderByRaw('CAST(codigo AS UNSIGNED) ASC')->get();
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

        $numeroparte_enum = [
            ['id' => '0', 'nombre' => 'No tiene (a granel)'],
            ['id' => '1', 'nombre' => 'Lleva número de parte'],
        ];

        $producto = ArticuloUltimoCreatePrefill::cargarProductoPrefill();

        return view('stock.articulo.crear', compact('producto', 'categoria', 'subcategoria', 'linea', 'marca', 'tipoimputacion_enum',
            'unidadmedida', 'usosArticulos', 'oficinacompra_query', 'referer', 'codimp',
            'periodicidadcompra_query', 'condicionentrega_query', 'empresa_query', 'estado_enum',
            'tiposArticulos', 'deposito_query', 'numeroparte_enum', 'nofactura_enum',
            'tipoproducto_query', 'capacidad_query', 'color_query', 'tipoliquido_query',
            'divide_enum'));
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

        return redirect('stock/articulo')->with('status', 'Producto creado');
    }

    public function editar($id, $type = null, $filtros = null)
    {
        can('editar-articulos');

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
        $deposito_query = Depmae::orderByRaw('CAST(codigo AS UNSIGNED) ASC')->get();
        $oficinacompra_query = $this->oficinacompraRepository->all();
        $periodicidadcompra_query = $this->periodicidadcompraRepository->all();
        $condicionentrega_query = $this->condicionentregaRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $tipoimputacion_enum = Articulo_Cuentacontable::$enumTipoImputacion;

        $referer = request()->headers->get('referer');
        $ocultarVolver = request()->query('origen') === 'modal_consulta';

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
        $numeroparte_enum = [
            ['id' => '0', 'nombre' => 'No tiene (a granel)'],
            ['id' => '1', 'nombre' => 'Lleva número de parte'],
        ];

        return view('stock.articulo.editar', compact('producto', 'id', 'categoria', 'marca', 'linea', 'subcategoria',
            'usosArticulos', 'codimp', 'empresa_query', 'referer', 'estado_enum',
            'unidadmedida', 'filtros', 'nofactura_enum', 'tiposArticulos',
            'periodicidadcompra_query', 'condicionentrega_query', 'tipoimputacion_enum',
            'deposito_query', 'numeroparte_enum', 'oficinacompra_query',
            'divide_enum',
            'tipoproducto_query', 'capacidad_query', 'color_query', 'tipoliquido_query',
            'puedeActualizarArticulo', 'ocultarVolver'));
    }

    public function actualizar(ValidacionArticulo $request, $id)
    {
        can('actualizar-articulos');

        $nombre_foto = $request->sku;
        if ($foto = Articulo::setFoto($request, $nombre_foto)) {
            $request->request->add(['foto' => $foto]);
        }

        $data = $request->all();

        DB::beginTransaction();
        try {
            $data['foto'] = ($nombre_foto != null ? $nombre_foto.'.jpg' : null);

            $articulo = Articulo::findOrFail($data['articulo_id'])->update($data);

            $articulo_estado = $this->articulo_estadoRepository->update($data, $id);
            $articulo_cuentacontable = $this->articulo_cuentacontableRepository->update($data, $id);

            $articulo_archivo = $this->articulo_archivoRepository->update($request, $id);

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

            dd($e->getMessage());

            return ['errores' => $e->getMessage()];
        }

        if ($request->input('origen') === 'modal_consulta') {
            return redirect()
                ->route('editar_articulo', ['id' => $id, 'origen' => 'modal_consulta'])
                ->with('status', 'Artículo actualizado con éxito');
        }

        return redirect('stock/articulo')->with('status', 'Artículo actualizado con éxito');
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

        $consultaRaw = $request->input('consulta');
        $consulta = is_string($consultaRaw) ? trim($consultaRaw) : '';

        $skuDigitosSufijo = max(0, (int) $request->input('sku_digitos_sufijo', 0));
        $minLen = \App\Support\Ventas\GastronomiaSkuCatalogoSupport::longitudMinimaBusqueda($consulta, $skuDigitosSufijo);

        if (mb_strlen($consulta) < $minLen) {
            $mensaje = '<tr><td colspan="6" class="text-muted">Ingrese al menos '.$minLen
                .($minLen === 1 ? ' dígito' : ' caracteres').' para buscar.</td></tr>';

            return response()->json(['data' => $mensaje], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $query = Articulo::select('articulo.id as articulo_id', 'sku', 'descripcion', 'unidadmedida.abreviatura as unidadmedida',
            'categoria.nombre as nombrecategoria', 'articulo.unidadmedida_id as idunidadmedida',
            'articulo.categoria_id as categoria_id', 'articulo.subcategoria_id as subcategoria_id')
            ->leftJoin('unidadmedida', 'articulo.unidadmedida_id', '=', 'unidadmedida.id')
            ->leftJoin('categoria', 'articulo.categoria_id', '=', 'categoria.id')
            ->leftJoin('linea', 'articulo.linea_id', '=', 'linea.id');

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

        $output = [];
        $output['data'] = '';
        $puedeConsultarArticulo = can('editar-articulos', false);
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
                $output['data'] .= '<td>'
                    .'<a class="btn btn-warning btn-sm eligeconsultaarticulo">Elegir</a>';
                if ($puedeConsultarArticulo) {
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e(url('stock/articulo/'.$row['articulo_id'].'/editar')).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
            }
        } else {
            $output['data'] .= '<tr>';
            $output['data'] .= '<td colspan="6">Sin resultados</td>';
            $output['data'] .= '</tr>';
        }

        return response()->json($output, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function leeUnArticulo($articulo_id)
    {
        return $this->articuloRepository->find($articulo_id);
    }

    public function leeUnArticuloPorSku($sku)
    {
        return $this->articuloRepository->findPorSku($sku);
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
