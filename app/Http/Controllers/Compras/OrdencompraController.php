<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\OrdencompraExport;
use App\Http\Controllers\Controller;
use App\Models\Compras\Condicioncompra;
use App\Models\Compras\Condicionentrega;
use App\Models\Compras\Condicionpago;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Archivo;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Estado;
use App\Models\Compras\Sector_Legajocompra;
use App\Models\Configuracion\Moneda;
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
use App\Services\Compras\OrdencompraGestionService;
use App\Services\Compras\OrdencompraListadoPdfService;
use App\Services\Compras\OrdencompraOpcionesPrecioService;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Services\Configuracion\ImpuestoService;
use App\Support\Compras\NotaOcPdfRecorteMargenIzquierdo;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Compras\OrdencompraListadoFiltros;
use App\Support\Compras\OrdencompraPdfContextoRequisicion;
use App\Support\Compras\OrdencompraTotalesCabecera;
use App\Support\Compras\OrdencompraTotalesResumen;
use App\Support\Compras\RequisicionTotalesCabecera;
use Auth;
use Illuminate\Http\Request;
use Jurosh\PDFMerge\PDFMerger;

class OrdencompraController extends Controller
{
    public function __construct(
        private OrdencompraRepositoryInterface $ordencompraRepository,
        private OrdencompraGestionService $ordencompraGestionService,
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
    ) {}

    public function index(Request $request)
    {
        can('listar-ordencompra');

        if (filter_var(env('ORDENCOMPRA_SYNC_ANITA_INDEX', false), FILTER_VALIDATE_BOOLEAN)
            && ! $this->ordencompraRepository->existeRegistro()) {
            $this->ordencompraAnitaSyncService->sincronizarConAnita((int) Auth::id());
        }

        $filtros = OrdencompraListadoFiltros::resolverDesdeRequest($request);
        $sectorUsuario = Auth::user()->sector_legajocompra_id ?? null;
        $sectorId = $sectorUsuario ? (int) $sectorUsuario : null;
        $ordencompra = $this->ordencompraRepository->listadoIndex(
            $filtros,
            $sectorId,
            true,
        );
        $estados = OrdencompraEstados::todos();
        $sectores = Sector_Legajocompra::orderBy('nombre')->get();

        return view('compras.ordencompra.index', [
            'ordencompra' => $ordencompra,
            'busqueda' => $filtros['busqueda'],
            'filtros' => $filtros,
            'filtrosQuery' => OrdencompraListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => OrdencompraListadoFiltros::CAMPOS,
            'estados' => $estados,
            'sectores' => $sectores,
            'sectorUsuario' => $sectorId,
        ]);
    }

    public function crear()
    {
        can('crear-ordencompra');

        return $this->formularioOrdencompra(null, false, null);
    }

    public function guardar(Request $request)
    {
        can('crear-ordencompra');

        $ret = $this->ordencompraGestionService->guardar($request, false);

        if (($ret['mensaje'] ?? '') === 'ok') {
            return redirect()->route('editar_ordencompra', ['id' => $ret['id']])->with('mensaje', 'Orden de compra creada con éxito');
        }

        return redirect()->route('crear_ordencompra')->withInput()->with('mensaje', $ret['errores'] ?? 'Error al guardar');
    }

    public function editar($id)
    {
        $soloConsulta = request()->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            if (! can('listar-ordencompra', false) && ! can('editar-ordencompra', false)) {
                can('listar-ordencompra');
            }
        } else {
            can('editar-ordencompra');
        }

        $puedeActualizar = can('actualizar-ordencompra', false);
        $soloLectura = $soloConsulta && ! $puedeActualizar;

        return $this->formularioOrdencompra((int) $id, $soloLectura, null);
    }

    public function actualizar(Request $request, $id)
    {
        can('actualizar-ordencompra');

        $ret = $this->ordencompraGestionService->actualizar($request, (int) $id);

        if (($ret['mensaje'] ?? '') === 'ok') {
            if ($request->input('origen') === 'modal_consulta') {
                return redirect()
                    ->route('editar_ordencompra', [
                        'id' => $id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ])
                    ->with('mensaje', 'Orden de compra actualizada con éxito');
            }

            return redirect()->route('consultar_ordencompra')->with('mensaje', 'Orden de compra actualizada con éxito');
        }

        $params = ['id' => $id];
        if ($request->input('origen') === 'modal_consulta') {
            $params['origen'] = 'modal_consulta';
            $params['vista'] = 'consulta';
        }

        return redirect()->route('editar_ordencompra', $params)->withInput()->with('mensaje', $ret['errores'] ?? 'Error');
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

        $sectorUsuario = Auth::user()->sector_legajocompra_id ?? null;
        $sectorId = $sectorUsuario ? (int) $sectorUsuario : null;
        $filtros = OrdencompraListadoFiltros::resolverDesdeRequest($request, $busqueda);

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
        return $this->visualizar($id, null);
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

        return $this->formularioOrdencompra((int) $id, true, null);
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

    public function cambiarEstado(Request $request, $id)
    {
        can('actualizar-ordencompra');
        $request->validate([
            'estado' => 'required|string|max:50',
            'observacion' => 'nullable|string|max:2000',
        ]);
        $ret = $this->ordencompraGestionService->cambiarEstado((int) $id, (string) $request->estado, (string) ($request->observacion ?? ''));

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($ret);
        }

        return redirect()->back()->with('mensaje', ($ret['mensaje'] ?? '') === 'ok' ? 'Estado actualizado' : ($ret['errores'] ?? 'Error'));
    }

    public function reactivarSuspendida($id)
    {
        can('actualizar-ordencompra');
        $ret = $this->ordencompraGestionService->reactivarDesdeSuspendida((int) $id);

        return redirect()->back()->with('mensaje', ($ret['mensaje'] ?? '') === 'ok' ? 'Orden reactivada a PENDIENTE' : ($ret['errores'] ?? 'Error'));
    }

    public function cambiarSector(Request $request, $id)
    {
        can('actualizar-ordencompra');
        $request->validate([
            'sector_legajocompra_id' => 'required|integer|exists:sector_legajocompra,id',
            'observacion' => 'nullable|string|max:255',
            'leyenda' => 'nullable|string|max:2000',
        ]);
        $ret = $this->ordencompraGestionService->cambiarSector(
            (int) $id,
            (int) $request->sector_legajocompra_id,
            $request->observacion,
            $request->leyenda
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($ret);
        }

        return redirect()->back()->with('mensaje', ($ret['mensaje'] ?? '') === 'ok' ? 'Sector actualizado' : ($ret['errores'] ?? 'Error'));
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
            return response()->file($path);
        }

        return response()->download($path, $basename);
    }

    public function imprimirPdf(Request $request, $id)
    {
        if (! can('listar-ordencompra', false) && ! can('editar-ordencompra', false)) {
            return redirect()->route('inicio')->with('mensaje', 'No tienes permisos para imprimir la orden de compra');
        }

        $data = $this->ordencompraRepository->find((int) $id);
        $data->loadMissing([
            'requisiciones.usuarios',
            'requisiciones.requisicion_estados.usuarios',
            'ordencompra_estados.usuarios',
        ]);
        OrdencompraTotalesCabecera::aplicarAtributosVirtuales($data, $this->cotizacionQuery);

        $totalesOc = OrdencompraTotalesResumen::desdeModelo($data, $this->cotizacionQuery, $this->impuestoService);
        $monedaPdf = $totalesOc['moneda_abrev'] !== ''
            ? $totalesOc['moneda_abrev']
            : (string) ($data->monedacabecera_abreviatura ?? '');

        $req = ($data->requisicion_id && $data->requisiciones) ? $data->requisiciones : null;
        [$reqUsuarioEmitio, $reqUsuarioAprobador] = OrdencompraPdfContextoRequisicion::emitioYUltimoAprobador($req);

        $layoutApaisado = strtolower((string) $request->query('formato', '')) === 'apaisado';
        $vistaPdf = $layoutApaisado
            ? 'compras.ordencompra.pdf-legal-landscape'
            : 'compras.ordencompra.pdf';

        $html = view($vistaPdf, compact('data', 'reqUsuarioEmitio', 'reqUsuarioAprobador', 'totalesOc', 'monedaPdf'))->render();

        $dir = storage_path('pdf/ordencompra');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $nombreArchivo = 'Ordencompra_'.preg_replace('/[^\w\-]+/', '_', (string) $data->numeroordencompra).'.pdf';
        $rutaPdfOc = $dir.'/'.$nombreArchivo;

        $pdf = \App::make('dompdf.wrapper');
        if ($layoutApaisado) {
            $pdf->setPaper([0.0, 0.0, 1008.0, 612.0]);
        } else {
            $pdf->setPaper('legal', 'portrait');
        }
        $pdf->loadHTML($html, 'UTF-8');
        $pdf->save($rutaPdfOc);

        $rutaNota = storage_path('app/public/imagenes/nota_oc.pdf');
        if (is_file($rutaNota)) {
            $recorteIzqPt = env('NOTA_OC_RECORTE_IZQUIERDO_PT');
            $recorteIzqPt = $recorteIzqPt !== null && $recorteIzqPt !== ''
                ? (float) $recorteIzqPt
                : NotaOcPdfRecorteMargenIzquierdo::RECORTE_POR_DEFECTO_PT;
            $escalaNota = env('NOTA_OC_ESCALA');
            $escalaNota = $escalaNota !== null && $escalaNota !== ''
                ? (float) $escalaNota
                : NotaOcPdfRecorteMargenIzquierdo::ESCALA_POR_DEFECTO;
            $margenIzqNota = env('NOTA_OC_MARGEN_IZQUIERDO_PT');
            if ($margenIzqNota !== null && $margenIzqNota !== '') {
                $margenIzqNota = (float) $margenIzqNota;
            } else {
                $margenIzqNota = ($recorteIzqPt > 0.01 || $escalaNota < 0.999)
                    ? NotaOcPdfRecorteMargenIzquierdo::MARGEN_IZQUIERDO_POR_DEFECTO_PT
                    : 0.0;
            }
            $rutaNotaParaFusion = NotaOcPdfRecorteMargenIzquierdo::generarTemporal($rutaNota, $recorteIzqPt, $escalaNota, $margenIzqNota) ?? $rutaNota;
            try {
                // jurosh/pdf-merge: 'vertical' = retrato (P), 'horizontal' = apaisado (L).
                $orientacionOc = $layoutApaisado ? 'horizontal' : 'vertical';
                $merger = new PDFMerger;
                $merger->addPDF($rutaPdfOc, 'all', $orientacionOc)
                    ->addPDF($rutaNotaParaFusion, 'all', 'vertical');
                $fusion = $merger->merge('string', $nombreArchivo);
                file_put_contents($rutaPdfOc, $fusion);
            } catch (\Throwable $e) {
                report($e);
            } finally {
                if ($rutaNotaParaFusion !== $rutaNota && is_file($rutaNotaParaFusion)) {
                    @unlink($rutaNotaParaFusion);
                }
            }
        }

        return response()->download($rutaPdfOc, $nombreArchivo)->deleteFileAfterSend(true);
    }

    public function wizardMultiplesDesdeRequisicion(int $requisicion_id)
    {
        can('crear-ordencompra');
        if (! $this->requisicionQuery->requisicionAccesiblePorUsuario($requisicion_id)) {
            abort(404);
        }
        $req = Requisicion::query()->select('id', 'estado', 'numerorequisicion')->find($requisicion_id);
        if (! $req) {
            abort(404);
        }
        $aprobada = Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'), true)]['nombre'];
        if ($req->estado !== $aprobada) {
            return redirect()->route('solo_consulta_requisicion', ['id' => $requisicion_id])
                ->with('mensaje', 'Solo se pueden generar órdenes de compra desde una requisición en estado APROBADA.');
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
            'wizardRequisicionId'
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
        foreach ($ret['ids'] ?? [] as $ocId) {
            $oc = Ordencompra::query()->select('id', 'numeroordencompra')->find($ocId);
            if ($oc) {
                $numeros[] = [
                    'id' => $oc->id,
                    'numeroordencompra' => $oc->numeroordencompra,
                    'url_imprimir' => route('imprimir_pdf_ordencompra', ['id' => $oc->id]),
                    'url_imprimir_apaisado' => route('imprimir_pdf_ordencompra', ['id' => $oc->id, 'formato' => 'apaisado']),
                ];
            }
        }

        return response()->json([
            'mensaje' => 'ok',
            'ordencompra_ids' => $ret['ids'] ?? [],
            'ordenes' => $numeros,
            'advertencias' => $ret['advertencias'] ?? [],
        ]);
    }

    private function formularioOrdencompra(?int $id, bool $soloLectura, ?int $wizardRequisicionId = null)
    {
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
        $sectores_legajo = Sector_Legajocompra::orderBy('nombre')->get();
        $transporte_query = Transporte::orderBy('nombre')->get();
        $tratamiento_enum = Ordencompra::$enumTratamientoCompra;
        $estados_oc = OrdencompraEstados::todos();
        $tipos_comprobante = [
            ['valor' => 'FACTURA', 'nombre' => 'Factura'],
            ['valor' => 'NOTA_CREDITO', 'nombre' => 'Nota de crédito'],
            ['valor' => 'NOTA_DEBITO', 'nombre' => 'Nota de débito'],
        ];
        $visualizar = $soloLectura;
        $acceso_visualizacion_por_hash = $soloLectura;
        $soloConsulta = request()->query('origen') === 'modal_consulta';
        $ocultarVolver = $soloConsulta;
        $puedeActualizarOrdencompra = can('actualizar-ordencompra', false);
        $proximoNumeroordencompra = $id === null
            ? $this->ordencompraRepository->proximoNumeroOrdencompra()
            : null;

        $oc_totales_resumen = OrdencompraTotalesResumen::vacioParaVista();
        if ($id !== null && $data) {
            $oc_totales_resumen = OrdencompraTotalesResumen::desdeModelo($data, $this->cotizacionQuery, $this->impuestoService);
            $monRef = $moneda_query->firstWhere('id', $oc_totales_resumen['moneda_id']);
            if ($monRef) {
                $oc_totales_resumen['moneda_abrev'] = (string) ($monRef->abreviatura ?? '');
            }
        }

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
        ));
    }
}
