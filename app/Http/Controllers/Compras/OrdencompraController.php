<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\OrdencompraExport;
use App\Http\Controllers\Controller;
use App\Models\Compras\Condicioncompra;
use App\Models\Compras\Condicionentrega;
use App\Models\Compras\Condicionpago;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Archivo;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Estado;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Color;
use App\Models\Stock\Talle;
use App\Models\Ventas\Transporte;
use App\Queries\Compras\RequisicionQueryInterface;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Services\Compras\OrdencompraAnitaSyncService;
use App\Services\Compras\OrdencompraEnvioProveedorService;
use App\Services\Compras\OrdencompraGestionService;
use App\Services\Compras\OrdencompraListadoPdfService;
use App\Services\Compras\OrdencompraOpcionesPrecioService;
use App\Services\Compras\OrdencompraPdfService;
use App\Services\Compras\OrdencompraArticuloPrecioHistoriaService;
use App\Services\Compras\OrdencompraRecepcionesListadoService;
use App\Services\Compras\OrdencompraRecepcionPrecioSyncService;
use App\Services\Compras\OrdencompraRevertirCierreLineaService;
use App\Services\Stock\RecepcionProveedorPrecioPendienteService;
use App\Support\Archivos\ArchivoAdjuntoCacheSupport;
use App\Support\Compras\OrdencompraArticuloPrecioHistoriaOrigen;
use App\Support\Compras\OrdencompraContratoVencimientoSupport;
use App\Support\Compras\OrdencompraTratamientoMovimientosSupport;
use App\Support\Seguridad\IngresoProveedorVinculoSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Services\Configuracion\ImpuestoService;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Compras\OrdencompraLegajoGastronomiaSupport;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use App\Support\Compras\OrdencompraListadoFiltros;
use App\Support\Compras\OrdencompraSectorVisibilidadSupport;
use Illuminate\Http\JsonResponse;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Compras\OrdencompraPdfContextoRequisicion;
use App\Support\Compras\OrdencompraTotalesCabecera;
use App\Support\Compras\OrdencompraTotalesResumen;
use App\Support\Compras\RequisicionListadoFiltros;
use App\Support\Compras\RequisicionTotalesCabecera;
use App\Models\Stock\Recepcion_Proveedor;
use Auth;
use Illuminate\Http\Request;
class OrdencompraController extends Controller
{
    public function __construct(
        private OrdencompraRepositoryInterface $ordencompraRepository,
        private OrdencompraGestionService $ordencompraGestionService,
        private OrdencompraPdfService $ordencompraPdfService,
        private OrdencompraEnvioProveedorService $ordencompraEnvioProveedorService,
        private ArbolaprobacionService $arbolaprobacionService,
        private Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacion_movimientoRepository,
        private EmpresaRepositoryInterface $empresaRepository,
        private CentrocostoRepositoryInterface $centrocostoRepository,
        private MonedaRepositoryInterface $monedaRepository,
        private FormapagoRepositoryInterface $formapagoRepository,
        private CotizacionQueryInterface $cotizacionQuery,
        private ImpuestoService $impuestoService,
        private RequisicionQueryInterface $requisicionQuery,
        private OrdencompraAnitaSyncService $ordencompraAnitaSyncService,
        private OrdencompraListadoPdfService $ordencompraListadoPdfService,
        private OrdencompraRecepcionesListadoService $ordencompraRecepcionesListadoService,
        private OrdencompraRecepcionPrecioSyncService $ordencompraRecepcionPrecioSyncService,
        private OrdencompraArticuloPrecioHistoriaService $ordencompraArticuloPrecioHistoriaService,
        private RecepcionProveedorPrecioPendienteService $recepcionPrecioPendienteService,
        private OrdencompraRevertirCierreLineaService $ordencompraRevertirCierreLineaService,
    ) {}

    public function index(Request $request)
    {
        can('listar-ordencompra');

        if (filter_var(env('ORDENCOMPRA_SYNC_ANITA_INDEX', false), FILTER_VALIDATE_BOOLEAN)
            && ! $this->ordencompraRepository->existeRegistro()) {
            $this->ordencompraAnitaSyncService->sincronizarConAnita((int) Auth::id());
        }

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = OrdencompraListadoFiltros::resolverDesdeRequest(
            $request,
            null,
            $empresaDefault ? (int) $empresaDefault : null
        );
        $sectorId = OrdencompraSectorVisibilidadSupport::idSectorParaFiltro();
        $ordencompra = $this->ordencompraRepository->listadoIndex(
            $filtros,
            $sectorId,
            true,
        );
        $estados = OrdencompraEstados::todos();
        $sectores = OrdencompraLegajoGastronomiaSupport::sectoresParaCambio();

        return view('compras.ordencompra.index', [
            'ordencompra' => $ordencompra,
            'busqueda' => $filtros['busqueda'],
            'filtros' => $filtros,
            'filtrosQuery' => OrdencompraListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => OrdencompraListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'estados' => $estados,
            'sectores' => $sectores,
            'sectorUsuario' => ($sectorId !== null && $sectorId > 0) ? $sectorId : null,
            'alcanceSector' => OrdencompraSectorVisibilidadSupport::etiquetaAlcance(),
        ]);
    }

    public function crear(Request $request)
    {
        can('crear-ordencompra');

        return $this->formularioOrdencompra(null, false, null, $request);
    }

    public function guardar(Request $request)
    {
        can('crear-ordencompra');

        $ret = $this->ordencompraGestionService->guardar($request, false);

        if (($ret['mensaje'] ?? '') === 'ok') {
            $redirect = redirect()
                ->route('consultar_ordencompra', QueryRetornoListado::desdeRequest($request, OrdencompraListadoFiltros::class))
                ->with('mensaje', 'Orden de compra creada con éxito');
            if ($this->ordencompraEnvioProveedorService->datosEnvio((int) $ret['id'])['puede_enviar'] ?? false) {
                $redirect->with('sugerir_envio_oc', (int) $ret['id']);
            }

            return $redirect;
        }

        return redirect()->route('crear_ordencompra', QueryRetornoListado::desdeRequest($request, OrdencompraListadoFiltros::class))
            ->withInput()->with('mensaje-error', $ret['errores'] ?? 'Error al guardar');
    }

    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            if (! can('listar-ordencompra', false) && ! can('editar-ordencompra', false)) {
                can('listar-ordencompra');
            }
        } else {
            can('editar-ordencompra');
        }

        $puedeActualizar = can('actualizar-ordencompra', false);
        $soloLectura = $soloConsulta && ! $puedeActualizar;

        return $this->formularioOrdencompra((int) $id, $soloLectura, null, $request);
    }

    public function actualizar(Request $request, $id)
    {
        can('actualizar-ordencompra');

        $ret = $this->ordencompraGestionService->actualizar($request, (int) $id);

        if (($ret['mensaje'] ?? '') === 'ok') {
            if (QueryRetornoListado::esModalConsulta($request)) {
                return redirect()
                    ->route('editar_ordencompra', [
                        'id' => $id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ])
                    ->with('mensaje', 'Orden de compra actualizada con éxito');
            }

            return redirect()->route('consultar_ordencompra', QueryRetornoListado::desdeRequest($request, OrdencompraListadoFiltros::class))
                ->with('mensaje', 'Orden de compra actualizada con éxito');
        }

        $params = QueryRetornoListado::paramsRutaEditar($request, OrdencompraListadoFiltros::class, (int) $id);

        return redirect()->route('editar_ordencompra', $params)->withInput()->with('mensaje-error', $ret['errores'] ?? 'Error');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-ordencompra');

        if ($request->ajax()) {
            if ($this->ordencompraGestionService->eliminar((int) $id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }
        abort(404);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-ordencompra');

        $sectorId = OrdencompraSectorVisibilidadSupport::idSectorParaFiltro();
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = OrdencompraListadoFiltros::resolverDesdeRequest(
            $request,
            $busqueda,
            $empresaDefault ? (int) $empresaDefault : null
        );

        if (in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');
        }

        switch ($formato) {
            case 'PDF':
                $rutaPdf = $this->ordencompraListadoPdfService->generar($filtros, $sectorId);

                return response()->download($rutaPdf, 'listado_ordencompra.pdf')->deleteFileAfterSend(true);

            case 'EXCEL':
                return (new OrdencompraExport($this->ordencompraRepository))
                    ->parametros($filtros, $sectorId)
                    ->download('ordencompra.xlsx');

            case 'CSV':
                return (new OrdencompraExport($this->ordencompraRepository))
                    ->parametros($filtros, $sectorId)
                    ->download('ordencompra.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_ordencompra', OrdencompraListadoFiltros::paraQueryString($filtros));
    }

    public function soloConsulta($id)
    {
        if (! can('listar-ordencompra', false)
            && ! can('editar-ordencompra', false)
            && ! can('listar-legajo-compra', false)
        ) {
            can('listar-ordencompra');
        }

        // Consulta logueada: layout ERP normal (no portal hash). El layout hash
        // solo aplica a visualizar por enlace de mail; ahí window.close() no sirve
        // porque se navega en la misma pestaña desde el listado.
        $request = request();
        $request->query->set('origen', 'modal_consulta');
        $request->query->set('vista', 'consulta');

        return $this->formularioOrdencompra((int) $id, true, null, $request, false);
    }

    public function visualizar($id, $hash = null)
    {
        $movs = $this->arbolaprobacion_movimientoRepository->findPorOrdencompra((int) $id);
        $flEncontro = ! $hash;
        if ($hash) {
            foreach ($movs as $movimiento) {
                if ($movimiento->hashvisualizar == $hash) {
                    $flEncontro = true;
                    break;
                }
            }
        }
        if (! $flEncontro) {
            return redirect()->route('inicio')->with('mensaje', 'No tiene permisos para visualizar la orden de compra');
        }

        $accesoHash = is_string($hash) && $hash !== '';

        return $this->formularioOrdencompra((int) $id, true, null, null, $accesoHash);
    }

    public function buscarRequisicionesAprobadas(Request $request)
    {
        if (! can('listar-ordencompra', false) && ! can('crear-ordencompra', false) && ! can('editar-ordencompra', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }
        $q = $request->query('q');

        return response()->json($this->ordencompraGestionService->buscarRequisicionesAprobadas(is_string($q) ? $q : null));
    }

    public function plantillaRequisicion(Request $request)
    {
        if (! can('crear-ordencompra', false) && ! can('editar-ordencompra', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }
        $id = (int) $request->query('requisicion_id', 0);
        if ($id <= 0) {
            return response()->json(['message' => 'Requisición inválida.'], 422);
        }
        if (! $this->requisicionQuery->requisicionAccesiblePorUsuario($id)) {
            return response()->json(['message' => 'Requisición no encontrada o sin acceso.'], 404);
        }
        try {
            $data = $this->ordencompraGestionService->plantillaDesdeRequisicion($id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($data);
    }

    /**
     * Opciones de precio para una línea de OC generada desde requisición (lista proveedor, presupuesto activo/elegido, precio requisición).
     */
    public function opcionesPrecioLineaOc(Request $request, OrdencompraOpcionesPrecioService $opcionesPrecioService)
    {
        if (! can('crear-ordencompra', false) && ! can('editar-ordencompra', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }
        $request->validate([
            'requisicion_id' => 'required|integer|exists:requisicion,id',
            'requisicion_articulo_id' => 'required|integer|exists:requisicion_articulo,id',
            'articulo_id' => 'required|integer|exists:articulo,id',
            'fecha_referencia' => 'nullable|date',
            'proveedor_id' => 'nullable|integer|exists:proveedor,id',
            'condicioncompra_id' => 'nullable|integer|exists:condicioncompra,id',
            'condicionentrega_id' => 'nullable|integer|exists:condicionentrega,id',
            'condicionpago_id' => 'nullable|integer|exists:condicionpago,id',
        ]);

        $fechaRef = $request->query('fecha_referencia');
        if (! is_string($fechaRef) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaRef)) {
            $fechaRef = date('Y-m-d');
        }

        $proveedorId = $request->query('proveedor_id');
        $proveedorId = ($proveedorId !== null && $proveedorId !== '' && (int) $proveedorId > 0)
            ? (int) $proveedorId
            : null;

        $ccId = $request->query('condicioncompra_id');
        $ccId = ($ccId !== null && $ccId !== '' && (int) $ccId > 0) ? (int) $ccId : null;
        $ceId = $request->query('condicionentrega_id');
        $ceId = ($ceId !== null && $ceId !== '' && (int) $ceId > 0) ? (int) $ceId : null;
        $cpId = $request->query('condicionpago_id');
        $cpId = ($cpId !== null && $cpId !== '' && (int) $cpId > 0) ? (int) $cpId : null;

        $data = $opcionesPrecioService->opcionesLinea(
            (int) $request->query('requisicion_id'),
            (int) $request->query('requisicion_articulo_id'),
            (int) $request->query('articulo_id'),
            $proveedorId,
            $ccId,
            $ceId,
            $cpId,
            $fechaRef
        );

        if (isset($data['message'])) {
            return response()->json($data, 422);
        }

        return response()->json($data);
    }

    public function calcularTotales(Request $request)
    {
        if (! can('crear-ordencompra', false) && ! can('editar-ordencompra', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }
        $payload = $request->all();
        $totales = OrdencompraTotalesResumen::desdeRequest($payload, $this->cotizacionQuery, $this->impuestoService);
        $mon = Moneda::query()->find($totales['moneda_id']);
        $totales['moneda_abrev'] = $mon ? (string) ($mon->abreviatura ?? '') : '';

        return response()->json($totales);
    }

    /**
     * Cotización de venta del día (tabla cotización) para una moneda y fecha de documento.
     */
    public function cotizacionMonedaFecha(Request $request)
    {
        if (! can('crear-ordencompra', false) && ! can('editar-ordencompra', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }
        $request->validate([
            'fecha' => 'required|date',
            'moneda_id' => 'required|integer|exists:moneda,id',
        ]);
        $fecha = substr((string) $request->query('fecha'), 0, 10);
        $monedaId = (int) $request->query('moneda_id');
        $cot = RequisicionTotalesCabecera::cotizacionVentaPorMonedaEnFecha($this->cotizacionQuery, $fecha, $monedaId);

        return response()->json([
            'cotizacion' => $cot,
            'moneda_id' => $monedaId,
            'fecha' => $fecha,
        ]);
    }

    public function sugerirCuotasCondicionpago(Request $request)
    {
        if (! can('crear-ordencompra', false) && ! can('editar-ordencompra', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }
        $request->validate([
            'condicionpago_id' => 'required|integer|exists:condicionpago,id',
            'fecha_base' => 'required|date',
            'monto' => 'required|numeric',
            'moneda_id' => 'required|integer|exists:moneda,id',
        ]);

        $cuotas = $this->ordencompraGestionService->sugerirCuotasDesdeCondicionpago(
            (int) $request->condicionpago_id,
            (string) $request->fecha_base,
            (float) $request->monto,
            (int) $request->moneda_id
        );

        return response()->json(['cuotas' => $cuotas]);
    }

    public function leerHistoriaLegajo($ordencompra_id)
    {
        can('editar-ordencompra');

        return response()->json($this->ordencompraGestionService->leerHistoriaLegajo((int) $ordencompra_id));
    }

    public function leerHistoriaEstados($ordencompra_id)
    {
        can('editar-ordencompra');

        return response()->json($this->ordencompraGestionService->leerHistoriaEstados((int) $ordencompra_id));
    }

    public function leerMovimientoAprobacion($ordencompra_id)
    {
        can('editar-ordencompra');

        return response()->json(
            $this->arbolaprobacionService->movimientosOrdencompraConAvisoGrabacion((int) $ordencompra_id)
        );
    }

    public function leerRecepciones($ordencompra_id)
    {
        can('editar-ordencompra');

        return response()->json(
            $this->ordencompraRecepcionesListadoService->listar((int) $ordencompra_id)
        );
    }

    /**
     * Entregas semanales de una línea OC (consulta desde OC / recepción).
     */
    public function leerEntregasSemanalLinea($ordencompra_articulo_id)
    {
        if (! can('listar-ordencompra', false)
            && ! can('editar-ordencompra', false)
            && ! can('listar-recepcion-proveedor-surmar', false)
            && ! can('crear-recepcion-proveedor-surmar', false)
            && ! can('editar-recepcion-proveedor-surmar', false)
            && ! can('listar-recepcion-proveedor', false)
        ) {
            can('listar-ordencompra');
        }

        $linea = Ordencompra_Articulo::query()
            ->with(['entregas', 'articulos:id,sku,descripcion'])
            ->find((int) $ordencompra_articulo_id);

        if (! $linea) {
            return response()->json(['ok' => false, 'mensaje' => 'Línea de OC no encontrada.'], 404);
        }

        $entregas = $linea->entregas->map(static function ($e) {
            return [
                'fecha' => $e->fecha ? $e->fecha->format('Y-m-d') : null,
                'cantidad' => (float) $e->cantidad,
                'orden' => (int) ($e->orden ?? 0),
            ];
        })->values()->all();

        $suma = 0.0;
        foreach ($entregas as $e) {
            $suma += (float) $e['cantidad'];
        }

        return response()->json([
            'ok' => true,
            'ordencompra_articulo_id' => (int) $linea->id,
            'ordencompra_id' => (int) $linea->ordencompra_id,
            'sku' => (string) (optional($linea->articulos)->sku ?? ''),
            'descripcion' => (string) (optional($linea->articulos)->descripcion ?? $linea->detalle ?? ''),
            'cantidad_linea' => (float) $linea->cantidad,
            'entregas' => $entregas,
            'cantidad_entregas' => $suma,
        ]);
    }

    /**
     * Matriz de entregas semanales de toda la OC (consulta desde OC / recepción).
     */
    public function leerEntregasSemanalOrden($ordencompra_id)
    {
        if (! can('listar-ordencompra', false)
            && ! can('editar-ordencompra', false)
            && ! can('listar-recepcion-proveedor-surmar', false)
            && ! can('crear-recepcion-proveedor-surmar', false)
            && ! can('editar-recepcion-proveedor-surmar', false)
            && ! can('listar-recepcion-proveedor', false)
        ) {
            can('listar-ordencompra');
        }

        $oc = Ordencompra::query()
            ->with([
                'ordencompra_articulos' => static fn ($q) => $q->orderBy('penvp_orden')->orderBy('id'),
                'ordencompra_articulos.articulos:id,sku,descripcion',
                'ordencompra_articulos.entregas',
            ])
            ->find((int) $ordencompra_id);

        if (! $oc) {
            return response()->json(['ok' => false, 'mensaje' => 'Orden de compra no encontrada.'], 404);
        }

        $lineas = [];
        foreach ($oc->ordencompra_articulos as $linea) {
            $entregas = $linea->entregas->map(static function ($e) {
                return [
                    'fecha' => $e->fecha ? $e->fecha->format('Y-m-d') : null,
                    'cantidad' => (float) $e->cantidad,
                ];
            })->filter(static function ($e) {
                return ! empty($e['fecha']) && (float) $e['cantidad'] > 0;
            })->values()->all();

            $lineas[] = [
                'ordencompra_articulo_id' => (int) $linea->id,
                'sku' => (string) (optional($linea->articulos)->sku ?? ''),
                'descripcion' => (string) (optional($linea->articulos)->descripcion ?? $linea->detalle ?? ''),
                'cantidad_linea' => (float) $linea->cantidad,
                'entregas' => $entregas,
            ];
        }

        return response()->json([
            'ok' => true,
            'ordencompra_id' => (int) $oc->id,
            'numeroordencompra' => (int) ($oc->numeroordencompra ?? 0),
            'lineas' => $lineas,
        ]);
    }

    public function leerHistoriaPrecios($ordencompra_id)
    {
        can('editar-ordencompra');

        return response()->json(
            $this->ordencompraArticuloPrecioHistoriaService->listarPorOrdencompra((int) $ordencompra_id)
        );
    }

    public function aplicarPreciosRecepcion($ordencompra_id, $recepcion_id)
    {
        can('editar-ordencompra');

        $recepcion = Recepcion_Proveedor::query()
            ->where('id', (int) $recepcion_id)
            ->where('ordencompra_id', (int) $ordencompra_id)
            ->firstOrFail();

        try {
            $actualizadas = $this->ordencompraRecepcionPrecioSyncService->actualizarPreciosDesdeRecepcion(
                $recepcion,
                soloPendientes: true,
                origen: OrdencompraArticuloPrecioHistoriaOrigen::APLICACION_MANUAL,
            );
            $this->recepcionPrecioPendienteService->liberarRecepcionesPendientesPorOc((int) $ordencompra_id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'actualizadas' => $actualizadas,
            'mensaje' => $actualizadas > 0
                ? "Se actualizaron {$actualizadas} precio(s) en la OC y Anita."
                : 'No había precios pendientes de aplicar.',
        ]);
    }

    public function aplicarPreciosSolicitadosRecepcionBorrador($ordencompra_id, $recepcion_id)
    {
        can('modificar-precio-ordencompra');

        $recepcion = Recepcion_Proveedor::query()
            ->where('id', (int) $recepcion_id)
            ->where('ordencompra_id', (int) $ordencompra_id)
            ->firstOrFail();

        try {
            $resultado = $this->recepcionPrecioPendienteService->aplicarPreciosSolicitadosDesdeBorrador((int) $recepcion->id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'actualizadas' => $resultado['actualizadas'],
            'liberada' => $resultado['liberada'],
            'mensaje' => $resultado['actualizadas'] > 0
                ? "Se actualizaron {$resultado['actualizadas']} precio(s) en la OC. La recepción ya puede confirmarse; se avisó al usuario por correo."
                : 'No había precios solicitados para aplicar.',
        ]);
    }

    public function cambiarEstado(Request $request, $id)
    {
        can('actualizar-ordencompra');
        $request->validate([
            'estado' => 'required|string|max:50',
            'observacion' => 'nullable|string|max:2000',
        ]);
        $ret = $this->ordencompraGestionService->cambiarEstado((int) $id, (string) $request->estado, (string) ($request->observacion ?? ''));

        if ($request->ajax() || $request->wantsJson()) {
            if (($ret['mensaje'] ?? '') === 'ok'
                && (string) $request->estado === OrdencompraEstados::APROBADA
                && ($this->ordencompraEnvioProveedorService->datosEnvio((int) $id)['puede_enviar'] ?? false)
            ) {
                $ret['sugerir_envio_proveedor'] = true;
            }

            return response()->json($ret);
        }

        $redirect = redirect()->back()->with(
            'mensaje',
            ($ret['mensaje'] ?? '') === 'ok' ? 'Estado actualizado' : ($ret['errores'] ?? 'Error')
        );
        if (($ret['mensaje'] ?? '') === 'ok'
            && (string) $request->estado === OrdencompraEstados::APROBADA
            && ($this->ordencompraEnvioProveedorService->datosEnvio((int) $id)['puede_enviar'] ?? false)
        ) {
            $redirect->with('sugerir_envio_oc', (int) $id);
        }

        return $redirect;
    }

    public function datosEnvioProveedor($id)
    {
        if (! can('listar-ordencompra', false) && ! can('editar-ordencompra', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        return response()->json($this->ordencompraEnvioProveedorService->datosEnvio((int) $id));
    }

    public function enviarProveedor(Request $request, $id)
    {
        if (! can('editar-ordencompra', false)) {
            return response()->json(['mensaje' => 'error', 'errores' => 'Sin permisos para enviar la orden al proveedor.'], 403);
        }

        $request->validate([
            'email' => 'nullable|string|max:500',
            'mensaje' => 'nullable|string|max:4000',
        ]);

        $ret = $this->ordencompraEnvioProveedorService->enviar(
            (int) $id,
            $request->input('email'),
            $request->input('mensaje')
        );

        $status = ($ret['mensaje'] ?? '') === 'ok' ? 200 : 422;

        return response()->json($ret, $status);
    }

    public function reactivarSuspendida($id)
    {
        can('actualizar-ordencompra');
        $ret = $this->ordencompraGestionService->reactivarDesdeSuspendida((int) $id);

        return redirect()->back()->with('mensaje', ($ret['mensaje'] ?? '') === 'ok' ? 'Orden reactivada a PENDIENTE' : ($ret['errores'] ?? 'Error'));
    }

    public function revertirCierreLineas(Request $request, $id)
    {
        can('actualizar-ordencompra');
        $request->validate([
            'observacion' => 'nullable|string|max:2000',
        ]);

        $ret = $this->ordencompraRevertirCierreLineaService->revertir(
            (int) $id,
            (int) (Auth::id() ?? 0),
            (string) ($request->input('observacion') ?? '')
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($ret, ($ret['mensaje'] ?? '') === 'ok' ? 200 : 422);
        }

        if (($ret['mensaje'] ?? '') === 'ok') {
            $pend = number_format((float) ($ret['cantidad_pendiente'] ?? 0), 2, ',', '.');
            $msg = "Se reabrieron {$ret['lineas_reabiertas']} línea(s). Saldo pendiente de recepción: {$pend}. Estado: ".($ret['estado_nuevo'] ?? '');

            return redirect()->back()->with('mensaje', $msg);
        }

        return redirect()->back()->with('mensaje', $ret['errores'] ?? 'No se pudo revertir el cierre de líneas.');
    }

    public function cambiarSector(Request $request, $id)
    {
        can('actualizar-ordencompra');
        $request->validate([
            'sector_legajocompra_id' => 'required|integer|exists:sector_legajocompra,id',
            'observacion' => 'nullable|string|max:255',
            'leyenda' => 'nullable|string|max:2000',
            'factura_pdf' => 'nullable|file|mimes:pdf|max:20480',
        ]);
        $ret = $this->ordencompraGestionService->cambiarSector(
            (int) $id,
            (int) $request->sector_legajocompra_id,
            $request->observacion,
            $request->leyenda,
            $request->file('factura_pdf'),
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($ret);
        }

        if (($ret['mensaje'] ?? '') === 'ok') {
            return redirect()->back()->with('mensaje', 'Sector actualizado');
        }

        return redirect()->back()->with('errores', [$ret['errores'] ?? 'Error']);
    }

    public function enviarGastronomia(Request $request, $id)
    {
        can('actualizar-ordencompra');
        $request->validate([
            'observacion' => 'nullable|string|max:255',
            'leyenda' => 'nullable|string|max:2000',
            'factura_pdf' => 'nullable|file|mimes:pdf|max:20480',
        ]);
        $ret = $this->ordencompraGestionService->enviarAGastronomia(
            (int) $id,
            $request->observacion,
            $request->leyenda,
            $request->file('factura_pdf'),
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($ret, ($ret['mensaje'] ?? '') === 'ok' ? 200 : 422);
        }

        if (($ret['mensaje'] ?? '') === 'ok') {
            return redirect()->back()->with('mensaje', 'Legajo enviado a Gastronomía');
        }

        return redirect()->back()->with('errores', [$ret['errores'] ?? 'Error']);
    }

    public function enviarCuentasAPagar(Request $request, $id)
    {
        can('actualizar-ordencompra');
        $request->validate([
            'observacion' => 'nullable|string|max:255',
            'leyenda' => 'nullable|string|max:2000',
            'factura_pdf' => 'nullable|file|mimes:pdf|max:20480',
        ]);
        $ret = $this->ordencompraGestionService->enviarACuentasAPagar(
            (int) $id,
            $request->observacion,
            $request->leyenda,
            $request->file('factura_pdf'),
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($ret, ($ret['mensaje'] ?? '') === 'ok' ? 200 : 422);
        }

        if (($ret['mensaje'] ?? '') === 'ok') {
            return redirect()->back()->with('mensaje', 'Legajo enviado a Cuentas a pagar');
        }

        return redirect()->back()->with('errores', [$ret['errores'] ?? 'Error']);
    }

    public function enviarPagos(Request $request, $id)
    {
        if (! can('actualizar-ordencompra', false)
            && ! can('crear-comprobante-proveedor', false)
            && ! can('listar-legajo-compra', false)
        ) {
            can('actualizar-ordencompra');
        }
        $request->validate([
            'observacion' => 'nullable|string|max:255',
            'leyenda' => 'nullable|string|max:2000',
        ]);
        $ret = $this->ordencompraGestionService->enviarAPagos(
            (int) $id,
            $request->observacion,
            $request->leyenda,
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($ret, ($ret['mensaje'] ?? '') === 'ok' ? 200 : 422);
        }

        if (($ret['mensaje'] ?? '') === 'ok') {
            return redirect()->back()->with('mensaje', 'Legajo enviado a Pagos');
        }

        return redirect()->back()->with('errores', [$ret['errores'] ?? 'Error']);
    }

    public function devolverCuentasAPagar(Request $request, $id)
    {
        if (! can('actualizar-ordencompra', false)
            && ! can('editar-pagoproveedor', false)
            && ! can('crear-pagoproveedor', false)
            && ! can('listar-legajo-compra', false)
        ) {
            can('actualizar-ordencompra');
        }
        $request->validate([
            'observacion' => 'required|string|min:3|max:255',
            'leyenda' => 'nullable|string|max:2000',
        ]);
        $ret = $this->ordencompraGestionService->devolverACuentasAPagar(
            (int) $id,
            (string) $request->observacion,
            $request->leyenda,
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($ret, ($ret['mensaje'] ?? '') === 'ok' ? 200 : 422);
        }

        if (($ret['mensaje'] ?? '') === 'ok') {
            return redirect()->back()->with('mensaje', 'Legajo devuelto a Cuentas a pagar');
        }

        return redirect()->back()->with('errores', [$ret['errores'] ?? 'Error']);
    }

    public function devolverCompras(Request $request, $id)
    {
        if (! can('actualizar-ordencompra', false)
            && ! can('crear-comprobante-proveedor', false)
            && ! can('listar-legajo-compra', false)
        ) {
            can('actualizar-ordencompra');
        }
        $request->validate([
            'observacion' => 'required|string|min:3|max:255',
            'leyenda' => 'nullable|string|max:2000',
        ]);
        $ret = $this->ordencompraGestionService->devolverACompras(
            (int) $id,
            (string) $request->observacion,
            $request->leyenda,
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($ret, ($ret['mensaje'] ?? '') === 'ok' ? 200 : 422);
        }

        if (($ret['mensaje'] ?? '') === 'ok') {
            return redirect()->back()->with('mensaje', 'Legajo devuelto a Compras');
        }

        return redirect()->back()->with('errores', [$ret['errores'] ?? 'Error']);
    }

    public function finalizarLegajo(Request $request, $id)
    {
        if (! can('actualizar-ordencompra', false)
            && ! can('editar-pagoproveedor', false)
            && ! can('crear-pagoproveedor', false)
            && ! can('listar-legajo-compra', false)
        ) {
            can('actualizar-ordencompra');
        }
        $request->validate([
            'observacion' => 'nullable|string|max:255',
        ]);
        $ret = $this->ordencompraGestionService->finalizarLegajo((int) $id, $request->observacion);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($ret, ($ret['mensaje'] ?? '') === 'ok' ? 200 : 422);
        }

        if (($ret['mensaje'] ?? '') === 'ok') {
            return redirect()->back()->with('mensaje', 'Legajo archivado');
        }

        return redirect()->back()->with('errores', [$ret['errores'] ?? 'Error']);
    }

    public function gateCuentasAPagar(int $id): JsonResponse
    {
        can('actualizar-ordencompra');

        $oc = Ordencompra::query()->find($id);
        if (! $oc) {
            return response()->json(['ok' => false, 'errores' => ['Orden de compra inexistente.']], 404);
        }

        $paquete = OrdencompraEnvioCuentasAPagarGateSupport::evaluar($oc);
        $gate = OrdencompraEnvioCuentasAPagarGateSupport::evaluarCuentasAPagar($oc);
        $gate['sector_cuentas_a_pagar_id'] = OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_CUENTAS_A_PAGAR
        );
        $gate['sector_gastronomia_id'] = OrdencompraLegajoGastronomiaSupport::sectorGastronomiaId();
        $gate['requiere_gastronomia'] = OrdencompraLegajoGastronomiaSupport::requiereCircuito($oc);
        $gate['puede_enviar_gastronomia'] = OrdencompraLegajoGastronomiaSupport::puedeMostrarEnviar($oc);
        $gate['paquete_ok'] = $paquete['ok'];
        $gate['paquete_errores'] = $paquete['errores'];
        $gate['requiere_pdf'] = $paquete['requiere_pdf'];
        $gate['tiene_factura'] = $paquete['tiene_factura'];
        $gate['tiene_com'] = $paquete['tiene_com'];
        $gate['exige_com'] = $paquete['exige_com'];

        return response()->json($gate);
    }

    public function visualizarFacturaLegajo(Request $request, int $id, string $hash)
    {
        if (! OrdencompraLegajoGastronomiaSupport::hashVisualizarValido($id, $hash)) {
            abort(403, 'El enlace de la factura no es válido.');
        }

        $oc = Ordencompra::query()->find($id);
        if (! $oc) {
            abort(404);
        }
        $factura = OrdencompraEnvioCuentasAPagarGateSupport::resolverPrecargaConPdf($oc);
        if (! $factura) {
            abort(404, 'El legajo no tiene factura PDF.');
        }

        $path = app(PrecargaFacturaScanPathResolver::class)->resolve($factura->rutaalmacenamiento);
        if ($path === null) {
            abort(404, 'No se encontró el PDF de la factura.');
        }

        $nombre = basename($path);
        if ($request->boolean('inline')) {
            return response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            ]);
        }

        return response()->download($path, $nombre);
    }

    /**
     * Descarga o sirve en línea (preview) un archivo adjunto de la orden de compra.
     */
    public function descargarArchivo(Request $request, int $id, int $archivo)
    {
        $hash = $request->query('hash');
        if (filled($hash)) {
            $flEncontro = false;
            foreach ($this->arbolaprobacion_movimientoRepository->findPorOrdencompra($id) as $movimiento) {
                if ($movimiento->hashvisualizar == $hash) {
                    $flEncontro = true;
                    break;
                }
            }
            if (! $flEncontro) {
                abort(403);
            }
        } elseif (! can('listar-ordencompra', false) && ! can('editar-ordencompra', false) && ! can('crear-ordencompra', false) && ! can('actualizar-ordencompra', false)) {
            abort(403);
        }

        $registro = Ordencompra_Archivo::query()
            ->where('id', $archivo)
            ->where('ordencompra_id', $id)
            ->first();
        if (! $registro) {
            abort(404);
        }

        $basename = basename((string) $registro->nombrearchivo);
        if ($basename === '' || str_contains($registro->nombrearchivo, '..')) {
            abort(404);
        }

        $path = public_path('storage/archivos/ordencompras/'.$id.'/'.$basename);
        if (! is_file($path)) {
            abort(404);
        }

        if ($request->boolean('inline')) {
            return ArchivoAdjuntoCacheSupport::aplicarAntiCacheNavegador(response()->file($path));
        }

        return response()->download($path, $basename);
    }

    public function imprimirPdf(Request $request, $id)
    {
        if (! can('listar-ordencompra', false) && ! can('editar-ordencompra', false)) {
            return redirect()->route('inicio')->with('mensaje', 'No tienes permisos para imprimir la orden de compra');
        }

        $layoutApaisado = strtolower((string) $request->query('formato', '')) === 'apaisado';
        $pdf = $this->ordencompraPdfService->generarArchivo((int) $id, $layoutApaisado);

        return response()->download($pdf['ruta'], $pdf['nombre'])->deleteFileAfterSend(true);
    }

    public function wizardMultiplesDesdeRequisicion(Request $request, int $requisicion_id)
    {
        can('crear-ordencompra');
        $filtrosQueryRequisicion = QueryRetornoListado::desdeRequest($request, RequisicionListadoFiltros::class);
        $paramsRetornoRequisicion = array_merge(['id' => $requisicion_id], $filtrosQueryRequisicion);

        if (! $this->requisicionQuery->requisicionAccesiblePorUsuario($requisicion_id)) {
            abort(404);
        }
        $req = Requisicion::query()->select('id', 'estado', 'numerorequisicion')->find($requisicion_id);
        if (! $req) {
            abort(404);
        }
        if (! $this->requisicionQuery->puedeUsuarioGenerarMultiplesOcDesdeRequisicion($req)) {
            $mensaje = \App\Support\Compras\RequisicionLineasOcSupport::cuentaPendientesOc($requisicion_id) <= 0
                ? 'No quedan ítems pendientes de orden de compra en esta requisición.'
                : 'No se pueden generar órdenes de compra desde esta requisición en su estado actual.';

            return redirect()->route('solo_consulta_requisicion', $paramsRetornoRequisicion)
                ->with('mensaje', $mensaje);
        }

        $this->ordencompraGestionService->sincronizarEstadoRequisicionSegunLineasOc(
            $requisicion_id,
            (int) auth()->id()
        );
        $req->refresh();

        try {
            $wizardPlantilla = $this->ordencompraGestionService->plantillaDesdeRequisicion($requisicion_id);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('solo_consulta_requisicion', $paramsRetornoRequisicion)
                ->with('mensaje', $e->getMessage());
        }

        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $formapago_query = $this->formapagoRepository->all();
        $proveedor_query = Proveedor::orderBy('nombre')->get();
        $condicionpago_query = Condicionpago::orderBy('nombre')->get();
        $condicionentrega_query = Condicionentrega::orderBy('nombre')->get();
        $condicioncompra_query = Condicioncompra::orderBy('nombre')->get();
        $transporte_query = Transporte::orderBy('nombre')->get();
        $tratamiento_enum = Ordencompra::$enumTratamientoCompra;
        $tipos_comprobante = [
            ['valor' => 'FACTURA', 'nombre' => 'Factura'],
            ['valor' => 'NOTA_CREDITO', 'nombre' => 'Nota de crédito'],
            ['valor' => 'NOTA_DEBITO', 'nombre' => 'Nota de débito'],
        ];
        $wizardRequisicionId = $requisicion_id;

        return view('compras.ordencompra.wizard_desde_requisicion', compact(
            'empresa_query',
            'centrocosto_query',
            'moneda_query',
            'formapago_query',
            'proveedor_query',
            'condicionpago_query',
            'condicionentrega_query',
            'condicioncompra_query',
            'transporte_query',
            'tratamiento_enum',
            'tipos_comprobante',
            'wizardRequisicionId',
            'wizardPlantilla',
            'filtrosQueryRequisicion',
        ));
    }

    public function generarMultiplesOcDesdeRequisicion(Request $request, int $requisicion_id)
    {
        can('crear-ordencompra');
        if (! $this->requisicionQuery->requisicionAccesiblePorUsuario($requisicion_id)) {
            return response()->json(['message' => 'Requisición no encontrada o sin acceso.'], 404);
        }
        if ((int) $request->input('requisicion_id', 0) !== $requisicion_id) {
            return response()->json(['message' => 'El identificador de requisición no coincide.'], 422);
        }
        // Soporta dos formatos:
        //   (a) `ordenes` array directo (compatibilidad con JS legacy);
        //   (b) `ordenes_json` string JSON (wizard nuevo que envía multipart con archivos).
        $ordenes = $request->input('ordenes', null);
        if ($ordenes === null) {
            $rawJson = (string) $request->input('ordenes_json', '');
            if ($rawJson !== '') {
                $decoded = json_decode($rawJson, true);
                $ordenes = is_array($decoded) ? $decoded : [];
            }
        }
        $lineasSin = $request->input('lineas_sin_orden', null);
        if ($lineasSin === null) {
            $rawSin = (string) $request->input('lineas_sin_orden_json', '');
            if ($rawSin !== '') {
                $decodedSin = json_decode($rawSin, true);
                $lineasSin = is_array($decodedSin) ? $decodedSin : [];
            }
        }
        if (! is_array($ordenes)) {
            $ordenes = [];
        }
        if (! is_array($lineasSin)) {
            $lineasSin = [];
        }
        if ($ordenes === [] && $lineasSin === []) {
            return response()->json(['message' => 'No hay órdenes para generar ni líneas a cerrar.'], 422);
        }
        if ($ordenes === [] && $lineasSin !== []) {
            return response()->json([
                'message' => 'Debe elegir el origen de precio en al menos un ítem para generar una orden de compra. '
                    .'No se puede cerrar la requisición sin crear ninguna OC.',
            ], 422);
        }

        // Archivos por grupo (campo `archivos_grupo_{idx}[]` cuando se postea multipart).
        $archivosPorOrden = [];
        foreach (array_keys($ordenes) as $idx) {
            $archivos = $request->file('archivos_grupo_'.$idx);
            if ($archivos === null) {
                continue;
            }
            if (! is_array($archivos)) {
                $archivos = [$archivos];
            }
            $archivosPorOrden[$idx] = array_values(array_filter($archivos, static fn ($f) => $f !== null));
        }

        $ret = $this->ordencompraGestionService->generarMultiplesOrdenesCompraDesdeRequisicion(
            $requisicion_id,
            $ordenes,
            $lineasSin,
            $archivosPorOrden
        );
        if (($ret['mensaje'] ?? '') !== 'ok') {
            return response()->json([
                'message' => $ret['errores'] ?? 'Error',
                'partial' => $ret['partial'] ?? false,
                'ids' => $ret['ids'] ?? [],
            ], 422);
        }

        $numeros = [];
        $enviosPendientes = [];
        foreach ($ret['ids'] ?? [] as $ocId) {
            $oc = Ordencompra::query()->select('id', 'numeroordencompra')->find($ocId);
            if ($oc) {
                $datosEnvio = $this->ordencompraEnvioProveedorService->datosEnvio((int) $oc->id);
                $fila = [
                    'id' => $oc->id,
                    'numeroordencompra' => $oc->numeroordencompra,
                    'url_imprimir' => route('imprimir_pdf_ordencompra', ['id' => $oc->id]),
                    'url_imprimir_apaisado' => route('imprimir_pdf_ordencompra', ['id' => $oc->id, 'formato' => 'apaisado']),
                    'url_editar' => route('editar_ordencompra', ['id' => $oc->id]),
                    'puede_enviar_proveedor' => (bool) ($datosEnvio['puede_enviar'] ?? false),
                    'proveedor_nombre' => (string) ($datosEnvio['proveedor_nombre'] ?? ''),
                    'email_proveedor' => (string) ($datosEnvio['email'] ?? ''),
                ];
                $numeros[] = $fila;
                if ($fila['puede_enviar_proveedor']) {
                    $enviosPendientes[] = [
                        'id' => (int) $oc->id,
                        'numeroordencompra' => $oc->numeroordencompra,
                        'proveedor_nombre' => $fila['proveedor_nombre'],
                        'email' => $fila['email_proveedor'],
                    ];
                }
            }
        }

        return response()->json([
            'mensaje' => 'ok',
            'ordencompra_ids' => $ret['ids'] ?? [],
            'ordenes' => $numeros,
            'envios_pendientes' => $enviosPendientes,
            'advertencias' => $ret['advertencias'] ?? [],
        ]);
    }

    private function formularioOrdencompra(
        ?int $id,
        bool $soloLectura,
        ?int $wizardRequisicionId = null,
        ?Request $request = null,
        bool $accesoVisualizacionPorHash = false,
    ) {
        $request = $request ?? request();
        $data = null;
        if ($id !== null) {
            $data = $this->ordencompraRepository->find($id);
            OrdencompraTotalesCabecera::aplicarAtributosVirtuales($data, $this->cotizacionQuery);
        }

        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $formapago_query = $this->formapagoRepository->all();
        $proveedor_query = Proveedor::orderBy('nombre')->get();
        $condicionpago_query = Condicionpago::orderBy('nombre')->get();
        $condicionentrega_query = Condicionentrega::orderBy('nombre')->get();
        $condicioncompra_query = Condicioncompra::orderBy('nombre')->get();
        $sectores_legajo = OrdencompraLegajoGastronomiaSupport::sectoresParaCambio();
        $transporte_query = Transporte::orderBy('nombre')->get();
        $tratamiento_enum = Ordencompra::$enumTratamientoCompra;
        $estados_oc = OrdencompraEstados::todos();
        $tipos_comprobante = [
            ['valor' => 'FACTURA', 'nombre' => 'Factura'],
            ['valor' => 'NOTA_CREDITO', 'nombre' => 'Nota de crédito'],
            ['valor' => 'NOTA_DEBITO', 'nombre' => 'Nota de débito'],
        ];
        $visualizar = $soloLectura;
        // Portal mail/hash: layout reducido. Solo consulta logueada usa layout ERP.
        $acceso_visualizacion_por_hash = $accesoVisualizacionPorHash;
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        $ocultarVolver = $soloConsulta;
        $puedeActualizarOrdencompra = can('actualizar-ordencompra', false);
        $filtrosQuery = ($soloConsulta || $wizardRequisicionId)
            ? []
            : QueryRetornoListado::desdeRequest($request, OrdencompraListadoFiltros::class);
        $proximoNumeroordencompra = $id === null
            ? $this->ordencompraRepository->proximoNumeroOrdencompra()
            : null;

        $oc_totales_resumen = OrdencompraTotalesResumen::vacioParaVista();
        $oc_datos_envio_proveedor = null;
        $tratamiento_bloqueado_por_movimientos = false;
        if ($id !== null && $data) {
            $oc_totales_resumen = OrdencompraTotalesResumen::desdeModelo($data, $this->cotizacionQuery, $this->impuestoService);
            $monRef = $moneda_query->firstWhere('id', $oc_totales_resumen['moneda_id']);
            if ($monRef) {
                $oc_totales_resumen['moneda_abrev'] = (string) ($monRef->abreviatura ?? '');
            }
            $oc_datos_envio_proveedor = $this->ordencompraEnvioProveedorService->datosEnvio($id);
            $tratamiento_bloqueado_por_movimientos = OrdencompraTratamientoMovimientosSupport::hayMovimientosQueBloqueanCambio($id);
        }

        $sugerir_envio_oc = session('sugerir_envio_oc');
        $oc_revertir_cierre_lineas = null;
        if ($id !== null && $data && $puedeActualizarOrdencompra) {
            $oc_revertir_cierre_lineas = $this->ordencompraRevertirCierreLineaService->resumen($id);
        }

        $color_query = Color::query()->orderBy('nombre')->get(['id', 'nombre']);
        $talle_query = Talle::query()->orderBy('nombre')->get(['id', 'nombre']);

        $usuario_contrato_query = UsuarioOperativoSupport::listadoParaSelector(
            columnas: ['id', 'nombre', 'email']
        );
        $oc_contrato_resumen = ($id !== null && $data && $data->es_contrato)
            ? OrdencompraContratoVencimientoSupport::resumenParaFormulario($id)
            : null;

        $tickets_ingreso = ($id !== null && $data)
            ? IngresoProveedorVinculoSupport::ticketsDeOc((int) $data->id)
            : collect();
        $ocPermiteIngresos = $id !== null && $data
            && IngresoProveedorVinculoSupport::ocPermiteCargarPersonas($data);
        $mostrar_solapa_ingresos = $id !== null && $data
            && IngresoProveedorVinculoSupport::usuarioPuedeVerSolapa()
            && ($ocPermiteIngresos || $tickets_ingreso->isNotEmpty());
        $url_nuevo_ticket_ingreso = $mostrar_solapa_ingresos && $ocPermiteIngresos
            ? IngresoProveedorVinculoSupport::urlNuevoTicket([
                'empresa_id' => $data->empresa_id,
                'proveedor_id' => $data->proveedor_id,
                'ordencompra_id' => $data->id,
            ])
            : null;

        $oc_puede_enviar_gastronomia = $id !== null && $data
            && empty($visualizar)
            && $puedeActualizarOrdencompra
            && OrdencompraLegajoGastronomiaSupport::puedeMostrarEnviar($data);
        $oc_puede_enviar_cuentas_a_pagar = $id !== null && $data
            && empty($visualizar)
            && $puedeActualizarOrdencompra
            && OrdencompraLegajoGastronomiaSupport::puedeMostrarEnviarCuentasAPagar($data);
        $oc_puede_enviar_pagos = $id !== null && $data
            && empty($visualizar)
            && (can('actualizar-ordencompra', false) || can('crear-comprobante-proveedor', false))
            && OrdencompraLegajoGastronomiaSupport::puedeMostrarEnviarPagos($data);
        $oc_puede_devolver_cxp = $id !== null && $data
            && empty($visualizar)
            && (
                can('editar-pagoproveedor', false)
                || can('crear-pagoproveedor', false)
                || can('actualizar-ordencompra', false)
            )
            && OrdencompraLegajoGastronomiaSupport::puedeDevolverACuentasAPagar($data);
        $oc_puede_devolver_compras = $id !== null && $data
            && empty($visualizar)
            && (can('actualizar-ordencompra', false) || can('crear-comprobante-proveedor', false))
            && OrdencompraLegajoGastronomiaSupport::puedeDevolverACompras($data);
        $oc_puede_finalizar_legajo = $id !== null && $data
            && empty($visualizar)
            && (
                $puedeActualizarOrdencompra
                || can('editar-pagoproveedor', false)
                || can('crear-pagoproveedor', false)
            )
            && OrdencompraLegajoGastronomiaSupport::puedeFinalizar($data);
        $oc_sector_gastronomia_id = OrdencompraLegajoGastronomiaSupport::sectorGastronomiaId();
        $oc_sector_cuentas_a_pagar_id = OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_CUENTAS_A_PAGAR
        );

        return view('compras.ordencompra.editar', compact(
            'data',
            'empresa_query',
            'centrocosto_query',
            'moneda_query',
            'formapago_query',
            'proveedor_query',
            'condicionpago_query',
            'condicionentrega_query',
            'condicioncompra_query',
            'sectores_legajo',
            'transporte_query',
            'tratamiento_enum',
            'tratamiento_bloqueado_por_movimientos',
            'estados_oc',
            'tipos_comprobante',
            'visualizar',
            'acceso_visualizacion_por_hash',
            'proximoNumeroordencompra',
            'oc_totales_resumen',
            'wizardRequisicionId',
            'soloConsulta',
            'ocultarVolver',
            'puedeActualizarOrdencompra',
            'oc_datos_envio_proveedor',
            'sugerir_envio_oc',
            'filtrosQuery',
            'oc_revertir_cierre_lineas',
            'color_query',
            'talle_query',
            'usuario_contrato_query',
            'oc_contrato_resumen',
            'mostrar_solapa_ingresos',
            'tickets_ingreso',
            'url_nuevo_ticket_ingreso',
            'oc_puede_enviar_gastronomia',
            'oc_puede_enviar_cuentas_a_pagar',
            'oc_puede_enviar_pagos',
            'oc_puede_devolver_cxp',
            'oc_puede_devolver_compras',
            'oc_puede_finalizar_legajo',
            'oc_sector_gastronomia_id',
            'oc_sector_cuentas_a_pagar_id',
        ));
    }
}
