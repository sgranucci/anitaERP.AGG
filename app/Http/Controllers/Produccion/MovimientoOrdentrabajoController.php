<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMovimientoOrdentrabajo;
use App\Services\Produccion\MovimientoOrdentrabajoService;
use App\Repositories\Produccion\TareaRepositoryInterface;
use App\Repositories\Produccion\OperacionRepositoryInterface;
use App\Repositories\Produccion\EmpleadoRepositoryInterface;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Listado\ListadoVisualSupport;
use App\Support\Produccion\MovimientoOrdentrabajoListadoColumnas;
use App\Support\Produccion\MovimientoOrdentrabajoListadoFiltros;
use App\Support\Produccion\MovimientoOrdentrabajoListadoPreferenciasUsuario;
use App\Support\Reportes\DompdfListadoSupport;
use App\Exports\Produccion\MovimientoOrdentrabajoListadoExport;

class MovimientoOrdentrabajoController extends Controller
{
	private $movimientoOrdentrabajoService;
	private $tareaRepository;
	private $operacionRepository;
	private $empleadoRepository;

    public function __construct(MovimientoOrdentrabajoService $movimientoordentrabajoservice,
    							TareaRepositoryInterface $tarearepository,
    							OperacionRepositoryInterface $operacionrepository,
    							EmpleadoRepositoryInterface $empleadorepository)
    {
        $this->movimientoOrdentrabajoService = $movimientoordentrabajoservice;
        $this->tareaRepository = $tarearepository;
        $this->operacionRepository = $operacionrepository;
        $this->empleadoRepository = $empleadorepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $id = null)
    {
        can('listar-movimientos-orden-trabajo');

        $otId = (int) ($id ?: $request->input('ordentrabajo_id', 0));
        if ($otId > 0) {
            $request->merge(['ordentrabajo_id' => $otId]);
        }
        if (! $request->has('filtro_estado_tarea')) {
            $request->merge([
                'filtro_estado_tarea' => $otId > 0
                    ? MovimientoOrdentrabajoListadoFiltros::ESTADO_TODAS
                    : MovimientoOrdentrabajoListadoFiltros::ESTADO_EN_CURSO,
            ]);
        }

        $armado = $this->armarListado($request, $otId > 0 ? $otId : null);

        return view('produccion.movimientoordentrabajo.index', $armado);
    }

    public function listar(Request $request, $formato = null)
    {
        can('listar-movimientos-orden-trabajo');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $otId = (int) $request->input('ordentrabajo_id', 0);
        if (! $request->has('filtro_estado_tarea')) {
            $request->merge([
                'filtro_estado_tarea' => MovimientoOrdentrabajoListadoFiltros::ESTADO_EN_CURSO,
            ]);
        }
        $filtros = $this->resolverFiltrosListado($request, $otId > 0 ? $otId : null);
        $columnasRequest = $request->input('columnas');
        if (is_string($columnasRequest)) {
            $columnasRequest = array_filter(array_map('trim', explode(',', $columnasRequest)));
        }
        $columnasVisibles = MovimientoOrdentrabajoListadoPreferenciasUsuario::resolverColumnas(
            is_array($columnasRequest) ? $columnasRequest : null
        );
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            MovimientoOrdentrabajoListadoColumnas::RECURSO,
            MovimientoOrdentrabajoListadoColumnas::catalogoActivo()
        );
        $estadoTxt = MovimientoOrdentrabajoListadoColumnas::etiquetaEstadoTarea((string) ($filtros['estado_tarea'] ?? ''));
        $subtitulo = 'Estado de tareas: '.$estadoTxt;
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $subtitulo .= ' · Texto: '.trim((string) $filtros['valor']);
        }

        switch ($formato) {
            case 'PDF':
                $datas = $this->movimientoOrdentrabajoService->leeListado($filtros, false);
                $html = view('produccion.movimientoordentrabajo.listado', [
                    'datas' => $datas,
                    'columnasVisibles' => $columnasVisibles,
                    'etiquetasColumnas' => $etiquetas,
                    'subtitulo' => $subtitulo,
                    'totalFilas' => $datas->count(),
                ])->render();
                $ruta = storage_path('pdf/listados/listado_movimientoordentrabajo.pdf');
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => 'Movimientos de OT',
                ]);

                return response()->download($ruta, 'movimientos_ot.pdf');

            case 'EXCEL':
                return (new MovimientoOrdentrabajoListadoExport($this->movimientoOrdentrabajoService))
                    ->parametros($filtros, $columnasVisibles, $etiquetas, $subtitulo)
                    ->download('movimientos_ot.xlsx');

            case 'CSV':
                return (new MovimientoOrdentrabajoListadoExport($this->movimientoOrdentrabajoService))
                    ->parametros($filtros, $columnasVisibles, $etiquetas, $subtitulo)
                    ->download('movimientos_ot.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('movimientoordentrabajo', MovimientoOrdentrabajoListadoFiltros::paraQueryString($filtros));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-movimientos-orden-trabajo');

        return view('produccion.movimientoordentrabajo.crear', $this->datosFormulario());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionMovimientoOrdentrabajo $request)
    {
		session(['tarea_id'=>$request->tarea_id]);
		session(['operacion_id'=>$request->operacion_id]);
		session(['empleado_id'=>$request->empleado_id]);

        $mensaje = '';
		try
		{
			$data = $this->movimientoOrdentrabajoService->guardaMovimientoOrdenTrabajo($request->all(), 'create');

            if (is_array($data) && isset($data['errores'])) {
                $errores = $data['errores'];
                if (is_array($errores)) {
                    $errores = implode(' ', array_map('strval', $errores));
                }

                return back()->withInput()->with('mensaje-error', (string) $errores);
            }

			if (is_array($data)) {
				$mensaje = 'Movimiento de OT creado con exito';
			} elseif ($data) {
				$mensaje = (string) $data;
			}
		} catch (\Exception $e) {
		    return back()->withInput()->with('mensaje-error', (string) $e->getMessage());
		}

        return redirect('produccion/movimientoordentrabajo/crear')->with('mensaje', $mensaje);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-movimientos-orden-trabajo');
    	$data = $this->movimientoOrdentrabajoService->leeMovimientoOrdenTrabajo($id);
		if ($data)
			$data['ordenestrabajo'] = $data->ordenestrabajo->codigo;

        return view(
            'produccion.movimientoordentrabajo.editar',
            array_merge(['data' => $data], $this->datosFormulario($data))
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionMovimientoOrdentrabajo $request, $id)
    {
        can('actualizar-movimientos-orden-trabajo');

        $mensaje = '';
		try
		{
            $data = $this->movimientoOrdentrabajoService->guardaMovimientoOrdenTrabajo($request->all(), 'update', $id);

            if (is_array($data) && isset($data['errores'])) {
                $errores = $data['errores'];
                if (is_array($errores)) {
                    $errores = implode(' ', array_map('strval', $errores));
                }

                return back()->withInput()->with('mensaje-error', (string) $errores);
            }

			if (is_array($data)) {
				$mensaje = 'Movimiento de OT actualizado con exito';
			} elseif ($data) {
				$mensaje = (string) $data;
			}
		} catch (\Exception $e) {
		    return back()->withInput()->with('mensaje-error', (string) $e->getMessage());
		}

        return redirect('produccion/movimientoordentrabajo')->with('mensaje', $mensaje ?: 'Movimiento de OT actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-movimientos-orden-trabajo');

        if ($request->ajax()) {
			if ($this->movimientoOrdentrabajoService->borraMovimientoOrdenTrabajo($id))
        	{
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    // Arma tarea 
    public function empacarTarea(Request $request)
    {
        can('empacar-ordenes-de-trabajo');

		$resultado = $this->movimientoOrdentrabajoService->empacaTarea($request->all());

		if (is_array($resultado) && isset($resultado['mensaje'])) {
			return (string) $resultado['mensaje'];
		}

		return is_string($resultado) ? $resultado : 'OT empacada con éxito';
    }

    // Lee tareas
    public function leerTareas($ot_id)
    {
        return $this->movimientoOrdentrabajoService->leeTareas($ot_id);
    }

    // Control de secuencia de fabricacion 
    public function controlSecuencia($ordenestrabajo, $operacion_id, $tarea_id)
    {
        return $this->movimientoOrdentrabajoService->controlSecuencia($ordenestrabajo, $operacion_id, $tarea_id);
    }

    // Control de secuencia de fabricacion 
    public function ctrlSecuencia($ordenestrabajo, $operacion_id, $tarea_id, $pedido_combinacion_id)
    {
        return $this->movimientoOrdentrabajoService->controlSecuencia($ordenestrabajo, $operacion_id, $tarea_id, $pedido_combinacion_id);
    }

    public function previewWorkbench(Request $request)
    {
        can('listar-movimientos-orden-trabajo');

        $filtros = $this->resolverFiltrosListado($request);
        $filtros['_per_page'] = ListadoDisenadorPreviewSupport::LIMITE_MUESTRA;
        $layout = MovimientoOrdentrabajoListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
        $page = $this->movimientoOrdentrabajoService->leeListado($filtros, true);
        $total = method_exists($page, 'total') ? (int) $page->total() : $page->count();
        $filas = method_exists($page, 'getCollection') ? $page->getCollection() : $page;
        $orden = ListadoOrdenamientoSupport::normalizar(
            $request->input('sort', $filtros['sort'] ?? []),
            MovimientoOrdentrabajoListadoFiltros::camposOrdenables()
        );
        $agrupar = ListadoAgrupacionSupport::normalizar(
            $request->input('group', $filtros['agrupar'] ?? []),
            MovimientoOrdentrabajoListadoFiltros::camposOrdenables()
        );
        $filtrosCortes = $filtros;
        $filtrosCortes['agrupar'] = $agrupar;
        $cortes = $agrupar !== []
            ? $this->movimientoOrdentrabajoService->cortesListado($filtrosCortes)
            : ['activo' => false];

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            $agrupar,
            $filas,
            static fn (object $row, string $key): string => MovimientoOrdentrabajoListadoColumnas::valorCelda($row, $key),
            $total,
            $etiquetas,
            $cortes
        ));
    }

    public function guardarVistaListado(Request $request)
    {
        can('listar-movimientos-orden-trabajo');

        $filtros = $this->resolverFiltrosListado($request);
        $layout = MovimientoOrdentrabajoListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $orden = $filtros['sort'] ?? [];
        $vista = ListadoVistaSupport::guardar(
            MovimientoOrdentrabajoListadoColumnas::RECURSO,
            (int) auth()->id(),
            (string) $request->input('nombre', ''),
            [
                'modo' => $filtros['modo'],
                'qbe' => $filtros['qbe'] ?? [],
                'sort' => $orden,
                'orden' => $orden,
                'agrupar' => $filtros['agrupar'] ?? [],
            ],
            $layout,
            $request->boolean('es_default'),
            $request->boolean('compartida'),
            $request->filled('vista_id') ? (int) $request->input('vista_id') : null
        );
        if (! $vista) {
            return redirect()->route('movimientoordentrabajo', MovimientoOrdentrabajoListadoFiltros::paraQueryString($filtros))
                ->with('error', 'No se pudo guardar la vista.');
        }
        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));
        $qs = MovimientoOrdentrabajoListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs['vista_id'] = $vista->id;

        return redirect()->route('movimientoordentrabajo', $qs)
            ->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public function eliminarVistaListado(int $id)
    {
        can('listar-movimientos-orden-trabajo');
        $ok = ListadoVistaSupport::eliminar($id, MovimientoOrdentrabajoListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('movimientoordentrabajo', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        can('listar-movimientos-orden-trabajo');
        $layout = MovimientoOrdentrabajoListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;
        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            $vista = ListadoVistaSupport::findParaUsuario($vistaId, MovimientoOrdentrabajoListadoColumnas::RECURSO, (int) auth()->id());
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            MovimientoOrdentrabajoListadoPreferenciasUsuario::persistirGrillaEstandar($layout);
        }
        $filtros = $this->resolverFiltrosListado($request);
        $qs = MovimientoOrdentrabajoListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs[$vistaId > 0 ? 'vista_id' : 'vista_estandar'] = $vistaId > 0 ? $vistaId : 1;

        return redirect()->route('movimientoordentrabajo', $qs)->with('mensaje', 'Grilla actualizada.');
    }

    public function guardarEtiquetasListado(Request $request)
    {
        can('listar-movimientos-orden-trabajo');
        $etiquetas = $request->input('etiquetas', []);
        if (! is_array($etiquetas)) {
            $etiquetas = [];
        }
        ListadoColumnaEtiquetaSupport::guardar(
            MovimientoOrdentrabajoListadoColumnas::RECURSO,
            $etiquetas,
            array_keys(MovimientoOrdentrabajoListadoColumnas::catalogoActivo())
        );

        return redirect()->route(
            'movimientoordentrabajo',
            MovimientoOrdentrabajoListadoFiltros::paraQueryString($this->resolverFiltrosListado($request))
        )->with('mensaje', 'Etiquetas actualizadas.');
    }

    /**
     * @return array<string, mixed>
     */
    private function armarListado(Request $request, ?int $otId): array
    {
        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $vistas = ListadoVistaSupport::listarParaUsuario(MovimientoOrdentrabajoListadoColumnas::RECURSO, $usuarioId);
        $vistaActiva = null;
        $forzarEstandar = $request->boolean('vista_estandar')
            || $request->input('vista_modo') === 'estandar';

        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                MovimientoOrdentrabajoListadoColumnas::RECURSO,
                $usuarioId
            );
        } elseif (
            ! $forzarEstandar
            && ! $request->has('filtro_valor')
            && ! $request->has('qbe')
            && ! $request->boolean('limpiar_filtros')
            && ! $request->boolean('filtro_limpiar')
        ) {
            $vistaActiva = ListadoVistaSupport::defaultDelUsuario(MovimientoOrdentrabajoListadoColumnas::RECURSO, $usuarioId);
        }

        $filtrosRequest = ListadoVistaSupport::prepararQbeContraVista(
            $this->resolverFiltrosListado($request, $otId),
            $request
        );
        $filtros = $filtrosRequest;
        if ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros = MovimientoOrdentrabajoListadoFiltros::fusionarDesdeVista($filtros, $vistaActiva->filtros_json);
        }
        unset($filtros['_qbe_explicito']);
        $filtros = ListadoVisualSupport::aplicarPedido(
            $filtros,
            $request,
            ($vistaActiva && is_array($vistaActiva->filtros_json)) ? $vistaActiva->filtros_json : null,
            MovimientoOrdentrabajoListadoFiltros::camposOrdenables()
        );
        ListadoVistaSupport::recordarQbeSiEnvio($vistaActiva, $request, $filtros);
        if ($vistaActiva && ($request->exists('group') || $request->exists('sort'))) {
            if ($request->exists('group')) {
                $filtros['agrupar'] = $filtrosRequest['agrupar'] ?? [];
            }
            if ($request->exists('sort')) {
                $filtros['sort'] = $filtrosRequest['sort'] ?? [];
            }
            ListadoVistaSupport::recordarOrdenYAgrupar(
                $vistaActiva,
                $filtros['sort'] ?? [],
                $filtros['agrupar'] ?? []
            );
        }

        $catalogo = MovimientoOrdentrabajoListadoColumnas::catalogoActivo();
        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            MovimientoOrdentrabajoListadoColumnas::RECURSO,
            $catalogo
        );
        if ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []) {
            $grillaLayout = MovimientoOrdentrabajoListadoPreferenciasUsuario::normalizarLayout($vistaActiva->columnas_json);
        } else {
            $grillaLayout = MovimientoOrdentrabajoListadoPreferenciasUsuario::grillaEstandar();
        }
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);

        $datas = $this->movimientoOrdentrabajoService->leeListado($filtros, true);
        $cortes = ['activo' => false];
        if (ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], MovimientoOrdentrabajoListadoFiltros::camposOrdenables()) !== []) {
            $cortes = $this->movimientoOrdentrabajoService->cortesListado($filtros);
        }

        $camposFiltro = MovimientoOrdentrabajoListadoFiltros::camposQbeDisponibles();
        foreach ($camposFiltro as $key => $meta) {
            $camposFiltro[$key]['label'] = $etiquetas[$key] ?? $etiquetasInstalacion[$key] ?? $meta['label'];
        }

        $filtrosQuery = MovimientoOrdentrabajoListadoFiltros::paraQueryString($filtros);
        $filtrosQuery = array_merge($filtrosQuery, ListadoVisualSupport::paraQueryString($filtros));
        $filtrosQuery['columnas'] = implode(',', $columnasVisibles);
        if ($request->boolean('filtro_limpiar')) {
            $filtrosQuery['filtro_limpiar'] = 1;
        }
        if ($vistaActiva) {
            $filtrosQuery['vista_id'] = $vistaActiva->id;
        } elseif ($forzarEstandar) {
            $filtrosQuery['vista_estandar'] = 1;
        }

        return [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => $camposFiltro,
            'estado_enum' => $this->movimientoOrdentrabajoService->estadoEnum(),
            'otId' => $otId,
            'columnasVisibles' => $columnasVisibles,
            'grillaLayout' => $grillaLayout,
            'catalogoColumnas' => $catalogo,
            'etiquetasColumnas' => $etiquetas,
            'etiquetasInstalacion' => $etiquetasInstalacion,
            'vistasListado' => $vistas,
            'vistaActiva' => $vistaActiva,
            'workbenchListo' => ListadoVistaSupport::tablasDisponibles(),
            'cortes' => $cortes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?int $otId = null): array
    {
        return MovimientoOrdentrabajoListadoFiltros::resolverDesdeRequest($request, null, $otId);
    }

	/**
	 * Valores del formulario. Tarea y empleado se resuelven por id;
	 * operación es un catálogo chico y se muestra como etiquetas.
	 *
	 * @param  mixed  $data
	 * @return array
	 */
	private function datosFormulario($data = null): array
	{
		$tareaId = old('tarea_id', $data?->tarea_id ?? session('tarea_id'));
		$operacionId = old('operacion_id', $data?->operacion_id ?? session('operacion_id'));
		$empleadoId = old('empleado_id', $data?->empleado_id ?? session('empleado_id'));

		$tarea = ((int) $tareaId > 0) ? $this->tareaRepository->findParaConsulta($tareaId) : null;
		$empleado = ((int) $empleadoId > 0) ? $this->empleadoRepository->findParaConsulta($empleadoId) : null;

		return [
			'operacion_query' => $this->operacionRepository->all()->sortBy('id')->values(),
			'operacionId' => $operacionId,
			'tareaId' => $tarea['id'] ?? '',
			'tareaCodigo' => $tarea ? (string) $tarea['id'] : (string) ($tareaId ?? ''),
			'tareaNombre' => $tarea['nombre'] ?? '',
			'empleadoId' => $empleado['id'] ?? '',
			'empleadoCodigo' => $empleado ? (string) $empleado['id'] : (string) ($empleadoId ?? ''),
			'empleadoNombre' => $empleado['nombre'] ?? '',
		];
	}

    
}

