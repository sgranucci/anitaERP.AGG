<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ComprobanteProveedorListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionComprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Archivo;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Proveedor;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Compras\Comprobante_ProveedorRepositoryInterface;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Repositories\Compras\Tipotransaccion_CompraRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Compras\ComprobanteProveedorPersistenciaService;
use App\Services\Compras\ComprobanteProveedorPrefillService;
use App\Services\Compras\ComprobanteProveedorRecepcionesSupport;
use App\Services\Compras\ComprobanteProveedorComLegajoResolucionService;
use App\Services\Compras\ComprobanteProveedorContabilizarService;
use App\Services\Compras\ComprobanteProveedorEliminarService;
use App\Services\Compras\ComprobanteProveedorAsientoService;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Support\Compras\ComprobanteProveedorArchivoPathSupport;
use App\Support\Compras\ComprobanteProveedorArchivoTipos;
use App\Support\Compras\ComprobanteProveedorControlesConfigSupport;
use App\Support\Compras\ComprobanteProveedorCotizacionSupport;
use App\Support\Compras\ComprobanteProveedorDuplicadoException;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorFlujoOcComFacSupport;
use App\Support\Compras\ComprobanteProveedorListadoFiltros;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorPagoSupport;
use App\Support\Compras\ComprobanteProveedorAsientoPreviewSupport;
use App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorNumeroOcSupport;
use App\Services\Arca\ConstanciaInscripcionService;
use App\Support\Ventas\ArcaPadronImpuestosClienteValidacion;
use App\Support\Compras\ProveedorFacturasApocrifasSupport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Comprobante_ProveedorController extends Controller
{
    public function __construct(
        private Comprobante_ProveedorRepositoryInterface $comprobanteRepository,
        private EmpresaRepositoryInterface $empresaRepository,
        private Tipotransaccion_CompraRepositoryInterface $tipotransaccionCompraRepository,
        private Concepto_IvacompraRepositoryInterface $conceptoIvacompraRepository,
        private MonedaRepositoryInterface $monedaRepository,
        private ComprobanteProveedorPrefillService $prefillService,
        private ComprobanteProveedorPersistenciaService $persistenciaService,
        private PrecargaFacturaScanPathResolver $facturaScanPathResolver,
        private ComprobanteProveedorArchivoPathSupport $archivoPathSupport,
        private ComprobanteProveedorContabilizarService $contabilizarService,
        private ComprobanteProveedorEliminarService $eliminarService,
        private ComprobanteProveedorRecepcionesSupport $recepcionesSupport,
        private ComprobanteProveedorAsientoService $asientoService,
        private ComprobanteProveedorAsientoPreviewSupport $asientoPreviewSupport,
        private PrecargaProveedorNumeroOcSupport $numeroOcSupport,
        private ComprobanteProveedorComLegajoResolucionService $comLegajoResolucion,
        private CotizacionQueryInterface $cotizacionQuery,
    ) {}

    public function index(Request $request)
    {
        can('listar-comprobante-proveedor');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->comprobanteRepository->leeComprobanteProveedor($filtros, true);

        return view('compras.comprobante_proveedor.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ComprobanteProveedorListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ComprobanteProveedorListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-comprobante-proveedor');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $filtrosQuery = ComprobanteProveedorListadoFiltros::paraQueryString($filtros);

        if (! $formato) {
            return redirect()->route('comprobante_proveedor', $filtrosQuery);
        }

        switch ($formato) {
            case 'PDF':
                $datas = $this->comprobanteRepository->leeComprobanteProveedor($filtros, false);

                $view = \View::make('compras.comprobante_proveedor.listado', [
                    'datas' => $datas,
                    'filtros' => $filtros,
                    'filtrosQuery' => $filtrosQuery,
                ])->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_comprobante_proveedor';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new ComprobanteProveedorListadoExport($this->comprobanteRepository))
                    ->parametros($filtros)
                    ->download('comprobante_proveedor.xlsx');

            case 'CSV':
                return (new ComprobanteProveedorListadoExport($this->comprobanteRepository))
                    ->parametros($filtros, true)
                    ->download('comprobante_proveedor.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('comprobante_proveedor', $filtrosQuery);
    }

    public function crear(Request $request)
    {
        can('crear-comprobante-proveedor');

        $prefill = $this->prefillService->construirDesdeRequest($request);

        return view('compras.comprobante_proveedor.crear', $this->datosFormulario($prefill));
    }

    public function opcionesCarga(Request $request)
    {
        if (! can('crear-comprobante-proveedor', false)
            && ! can('listar-precarga-proveedores', false)
            && ! can('crear-precarga-proveedores', false)) {
            abort(403);
        }

        return view('compras.comprobante_proveedor.opciones_carga', [
            'pdfIaHabilitado' => (bool) config('comprobante_proveedor_pdf_ia.habilitado'),
        ]);
    }

    public function resolverOrdencompraParaAlta(Request $request): JsonResponse
    {
        can('crear-comprobante-proveedor');

        try {
            $numeroOc = $this->numeroOcSupport->normalizar($request->input('numero_oc', ''));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $ordencompra = Ordencompra::query()
            ->where('numeroordencompra', (int) $numeroOc)
            ->orderByDesc('id')
            ->first();

        if (! $ordencompra) {
            return response()->json([
                'ok' => false,
                'message' => 'No existe orden de compra «'.$numeroOc.'» en el ERP.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'ordencompra_id' => (int) $ordencompra->id,
            'numero_oc' => $numeroOc,
            'redirect' => route('crear_comprobante_proveedor', ['ordencompra_id' => $ordencompra->id]),
        ]);
    }

    public function guardar(ValidacionComprobante_Proveedor $request)
    {
        can('crear-comprobante-proveedor');

        try {
            $comprobante = $this->persistenciaService->crearDesdeRequest($request);
        } catch (ComprobanteProveedorDuplicadoException $e) {
            return $this->respuestaComprobanteDuplicado($e);
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errores', [$this->mensajeErrorPersistencia('No se pudo guardar el comprobante', $e)]);
        }

        $mensaje = 'Comprobante de proveedor guardado en borrador.';
        $avisos = $this->persistenciaService->ultimosAvisosControles();
        if ($avisos !== []) {
            $mensaje .= ' '.implode(' ', $avisos);
        }

        $contabilizarAlGuardar = $request->input('accion') === 'contabilizar';
        if ($contabilizarAlGuardar) {
            can('contabilizar-comprobante-proveedor');
            try {
                $this->contabilizarService->contabilizar((int) $comprobante->id);
            } catch (\Throwable $e) {
                return redirect()
                    ->route('editar_comprobante_proveedor', ['id' => $comprobante->id])
                    ->with('errores', [
                        'El comprobante se guardó en borrador, pero no se pudo contabilizar: '.$e->getMessage(),
                    ]);
            }

            return redirect()
                ->route('editar_comprobante_proveedor', ['id' => $comprobante->id])
                ->with('mensaje', 'Comprobante contabilizado: asiento, cuenta corriente y sync Anita.');
        }

        // Borrador: queda en editar para que aparezca Contabilizar (mismo patrón que recepción).
        return redirect()
            ->route('editar_comprobante_proveedor', ['id' => $comprobante->id])
            ->with('mensaje', $mensaje);
    }

    public function editar(int $id)
    {
        can('editar-comprobante-proveedor');

        $comprobante = $this->comprobanteRepository->find($id);
        if (! $comprobante) {
            abort(404);
        }

        $prefill = $this->prefillService->paraEdicion($comprobante);

        return view('compras.comprobante_proveedor.editar', $this->datosFormulario($prefill));
    }

    public function actualizar(ValidacionComprobante_Proveedor $request, int $id)
    {
        can('actualizar-comprobante-proveedor');

        try {
            $this->persistenciaService->actualizarDesdeRequest($request, $id);
        } catch (ComprobanteProveedorDuplicadoException $e) {
            return $this->respuestaComprobanteDuplicado($e, quedarseEnFormulario: true);
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errores', [$this->mensajeErrorPersistencia('No se pudo actualizar el comprobante', $e)]);
        }

        $mensaje = 'Comprobante de proveedor actualizado.';
        $avisos = $this->persistenciaService->ultimosAvisosControles();
        if ($avisos !== []) {
            $mensaje .= ' '.implode(' ', $avisos);
        }

        return redirect()
            ->route('comprobante_proveedor')
            ->with('mensaje', $mensaje);
    }

    /**
     * Cotización venta del día (tabla cotización / cron BNA) para moneda y fecha de comprobante.
     */
    public function apiCotizacionMonedaFecha(Request $request): JsonResponse
    {
        if (! can('crear-comprobante-proveedor', false)
            && ! can('editar-comprobante-proveedor', false)
            && ! can('actualizar-comprobante-proveedor', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        $request->validate([
            'fecha' => 'required|date',
            'moneda_id' => 'required|integer|exists:moneda,id',
        ]);

        $fecha = substr((string) $request->query('fecha'), 0, 10);
        $monedaId = (int) $request->query('moneda_id');
        $cot = ComprobanteProveedorCotizacionSupport::resolverParaMonedaYFecha(
            $this->cotizacionQuery,
            $fecha,
            $monedaId
        );

        return response()->json([
            'cotizacion' => $cot,
            'moneda_id' => $monedaId,
            'fecha' => $fecha,
        ]);
    }

    public function previewAsientoContable(Request $request, ?int $id = null): JsonResponse
    {
        if (! can('editar-comprobante-proveedor', false)
            && ! can('crear-comprobante-proveedor', false)
            && ! can('actualizar-comprobante-proveedor', false)) {
            can('editar-comprobante-proveedor');
        }

        $existente = null;
        if ($id) {
            $existente = $this->comprobanteRepository->find($id);
            if (! $existente) {
                abort(404);
            }
        }

        $asientoPreview = $this->asientoService->previewDesdeFormulario($request, $existente);

        try {
            $comprobanteVista = $this->asientoPreviewSupport->construirDesdeRequest($request, $existente);
        } catch (\RuntimeException $e) {
            $comprobanteVista = $existente ?? new \App\Models\Compras\Comprobante_Proveedor;
            if (empty($asientoPreview['error'])) {
                $asientoPreview['error'] = $e->getMessage();
            }
        }

        $html = view('compras.comprobante_proveedor.partials.solapa_asiento_contable_body', [
            'asientoPreview' => $asientoPreview,
            'data' => $comprobanteVista,
        ])->render();

        return response()->json([
            'html' => $html,
            'error' => $asientoPreview['error'] ?? null,
            'es_preview' => $asientoPreview['es_preview'] ?? true,
            'avisos' => $asientoPreview['avisos'] ?? [],
            'cuadra' => empty($asientoPreview['error']) && empty($asientoPreview['avisos'] ?? []),
        ]);
    }

    public function eliminar(Request $request, int $id)
    {
        can('borrar-comprobante-proveedor');

        try {
            $resultado = $this->eliminarService->eliminar($id, tambienPrecarga: false);
        } catch (\Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'error', 'error' => $e->getMessage()], 422);
            }

            return redirect()
                ->route('editar_comprobante_proveedor', ['id' => $id])
                ->with('errores', ['No se pudo borrar el comprobante: '.$e->getMessage()]);
        }

        if ($request->ajax()) {
            return response()->json(['mensaje' => 'ok', 'detalle' => $resultado['mensaje']]);
        }

        return redirect()
            ->route('comprobante_proveedor')
            ->with('mensaje', $resultado['mensaje']);
    }

    public function eliminarConPrecarga(Request $request, int $id)
    {
        can('borrar-comprobante-proveedor');
        if (! can('borrar-precarga-proveedores', false)) {
            can('borrar-precarga-proveedores');
        }

        try {
            $resultado = $this->eliminarService->eliminar($id, tambienPrecarga: true);
        } catch (\Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'error', 'error' => $e->getMessage()], 422);
            }

            return redirect()
                ->route('editar_comprobante_proveedor', ['id' => $id])
                ->with('errores', ['No se pudo borrar: '.$e->getMessage()]);
        }

        if ($request->ajax()) {
            return response()->json(['mensaje' => 'ok', 'detalle' => $resultado['mensaje']]);
        }

        return redirect()
            ->route('comprobante_proveedor')
            ->with('mensaje', $resultado['mensaje']);
    }

    public function generarDesdePrecarga(int $precargaId)
    {
        can('crear-comprobante-proveedor');

        $existente = Comprobante_Proveedor::query()
            ->where('precarga_comprobante_proveedor_id', $precargaId)
            ->orderBy('id')
            ->first();
        if ($existente) {
            return redirect()
                ->route('editar_comprobante_proveedor', ['id' => $existente->id])
                ->with('mensaje', 'Esta precarga ya tiene el comprobante #'.$existente->id.' generado. Se abrió para revisión.');
        }

        // No grabar hasta que el operador pulse Guardar en el alta.
        return redirect()->route('crear_comprobante_proveedor', ['precarga_id' => $precargaId]);
    }

    public function contabilizar(int $id)
    {
        can('contabilizar-comprobante-proveedor');

        try {
            $this->contabilizarService->contabilizar($id);
        } catch (\Throwable $e) {
            return redirect()
                ->route('editar_comprobante_proveedor', ['id' => $id])
                ->with('errores', ['No se pudo contabilizar: '.$e->getMessage()]);
        }

        return redirect()
            ->route('comprobante_proveedor')
            ->with('mensaje', 'Comprobante contabilizado: asiento, cuenta corriente y sync Anita.');
    }

    public function verFacturaPdf(Request $request, int $id): BinaryFileResponse
    {
        if (! can('editar-comprobante-proveedor', false)
            && ! can('listar-comprobante-proveedor', false)
            && ! can('listar-cuentacorriente-proveedor', false)) {
            abort(403);
        }

        $comprobante = $this->comprobanteRepository->find($id);
        if (! $comprobante) {
            abort(404);
        }

        $archivo = $comprobante->comprobante_proveedor_archivos
            ->firstWhere('tipo', ComprobanteProveedorArchivoTipos::ORIGEN_IA);

        $ruta = $archivo?->ruta_externa;
        if (! $ruta && $comprobante->precarga_comprobante_proveedores) {
            $ruta = $comprobante->precarga_comprobante_proveedores->rutaalmacenamiento;
        }

        $path = $this->facturaScanPathResolver->resolve($ruta);
        if ($path === null) {
            $path = $this->archivoPathSupport->absolutePathDesdeComprobante($comprobante);
        }

        if ($path === null || ! is_readable($path)) {
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

    public function descargarArchivo(Request $request, int $id, int $archivo): BinaryFileResponse
    {
        can('editar-comprobante-proveedor');

        $registro = Comprobante_Proveedor_Archivo::query()
            ->where('id', $archivo)
            ->where('comprobante_proveedor_id', $id)
            ->first();

        if (! $registro) {
            abort(404);
        }

        if ($registro->tipo === ComprobanteProveedorArchivoTipos::ORIGEN_IA) {
            return $this->verFacturaPdf($request, $id);
        }

        $basename = basename((string) $registro->nombrearchivo);
        if ($basename === '' || str_contains($registro->nombrearchivo, '..')) {
            abort(404);
        }

        $path = public_path('storage/archivos/comprobantes_proveedor/'.$id.'/'.$basename);
        if (! is_file($path)) {
            abort(404);
        }

        if ($request->boolean('inline')) {
            return response()->file($path);
        }

        return response()->download($path, $basename);
    }

    public function validarProveedorArcaPadron(Request $request): JsonResponse
    {
        can('editar-comprobante-proveedor');

        if (! filter_var(config('arca.padron_validacion_proveedor.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'ok' => true,
                'skipped' => true,
                'validacion' => null,
            ]);
        }

        $proveedorId = (int) $request->input('proveedor_id', 0);
        if ($proveedorId <= 0) {
            return response()->json(['ok' => false, 'message' => 'Proveedor no indicado.'], 422);
        }

        $proveedor = Proveedor::query()->find($proveedorId);
        if (! $proveedor) {
            return response()->json(['ok' => false, 'message' => 'Proveedor inexistente.'], 404);
        }

        $cuit = preg_replace('/\D+/', '', (string) $proveedor->nroinscripcion) ?? '';
        if (strlen($cuit) !== 11) {
            return response()->json([
                'ok' => false,
                'message' => 'El proveedor no tiene una CUIT válida (11 dígitos) para consultar ARCA.',
            ], 422);
        }

        $condicionivaId = (int) ($proveedor->condicioniva_id ?? 0);

        try {
            $data = app(ConstanciaInscripcionService::class)->getPersonaV2($cuit);
            $validacion = ArcaPadronImpuestosClienteValidacion::validar(
                $condicionivaId > 0 ? $condicionivaId : null,
                $data
            );

            $httpOk = ! ($validacion['aplica'] ?? false) || ($validacion['ok'] ?? false);

            return response()->json([
                'ok' => $httpOk,
                'message' => $validacion['mensaje'] ?? null,
                'validacion' => $validacion,
                'proveedor' => [
                    'id' => $proveedor->id,
                    'codigo' => $proveedor->codigo,
                    'nombre' => $proveedor->nombre,
                    'condicioniva_id' => $condicionivaId,
                ],
            ], $httpOk ? 200 : 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Error al consultar padrón ARCA: '.$e->getMessage(),
            ], 500);
        }
    }

    public function validarProveedorArcaApoc(Request $request): JsonResponse
    {
        can('editar-comprobante-proveedor');

        $support = app(ProveedorFacturasApocrifasSupport::class);
        if (! $support->habilitadoParaComprobante()) {
            return response()->json([
                'ok' => true,
                'skipped' => true,
                'validacion' => null,
            ]);
        }

        $proveedorId = (int) $request->input('proveedor_id', 0);
        if ($proveedorId <= 0) {
            return response()->json(['ok' => false, 'message' => 'Proveedor no indicado.'], 422);
        }

        $proveedor = Proveedor::query()->find($proveedorId);
        if (! $proveedor) {
            return response()->json(['ok' => false, 'message' => 'Proveedor inexistente.'], 404);
        }

        try {
            $validacion = $support->evaluarProveedor($proveedor, suspenderSiApocrifo: true);
            $httpOk = ! ($validacion['aplica'] ?? false) || ($validacion['ok'] ?? false);

            return response()->json([
                'ok' => $httpOk,
                'message' => $validacion['mensaje'] ?? null,
                'validacion' => $validacion,
                'suspendido' => $validacion['suspendido'] ?? false,
                'facturas_apocrifas' => (bool) ($validacion['es_apocrifo'] ?? false),
                'proveedor' => [
                    'id' => $proveedor->id,
                    'codigo' => $proveedor->codigo,
                    'nombre' => $proveedor->nombre,
                ],
            ], $httpOk ? 200 : 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => true,
                'message' => \App\Services\Arca\WsapocConsultaService::mensajeAvisoNoDisponible(),
                'validacion' => [
                    'aplica' => true,
                    'ok' => true,
                    'error_servicio' => true,
                    'mensaje' => \App\Services\Arca\WsapocConsultaService::mensajeAvisoNoDisponible(),
                    'es_apocrifo' => false,
                    'detalles' => [],
                ],
                'facturas_apocrifas' => false,
            ]);
        }
    }

    /** @param array<string, mixed> $prefill */
    private function respuestaComprobanteDuplicado(
        ComprobanteProveedorDuplicadoException $e,
        bool $quedarseEnFormulario = false,
    ) {
        if (! $quedarseEnFormulario && $e->puedeAbrirEdicion()) {
            return redirect()
                ->route('editar_comprobante_proveedor', ['id' => $e->comprobanteId()])
                ->with('errores', [$e->getMessage()]);
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('errores', [$e->getMessage()]);
    }

    private function mensajeErrorPersistencia(string $prefijo, \Throwable $e): string
    {
        if (str_contains($e->getMessage(), 'SQLSTATE') || str_contains($e->getMessage(), 'Duplicate entry')) {
            return $prefijo.'. Ya existe un comprobante con la misma identificación fiscal '
                .'(empresa, tipo, letra, sucursal, número y CUIT). Buscalo en el listado de Cuentas a pagar.';
        }

        return $prefijo.': '.$e->getMessage();
    }

    private function datosFormulario(array $prefill): array
    {
        $data = $prefill['data'] ?? null;
        $ordencompraId = (int) ($data->ordencompra_id ?? 0);
        $comprobanteId = $data->id ?? null;

        $recepcionesDisponibles = $this->resolverRecepcionesDisponiblesFormulario($data, $ordencompraId, $comprobanteId);

        if (($prefill['recepciones_disponibles'] ?? null) instanceof \Illuminate\Support\Collection
            && $prefill['recepciones_disponibles']->isNotEmpty()) {
            $recepcionesDisponibles = $prefill['recepciones_disponibles']
                ->merge($recepcionesDisponibles)
                ->unique('id')
                ->values();
        }

        $recepcionesDisponibles = $this->recepcionesSupport->enriquecerConImporteProvision($recepcionesDisponibles);

        $recepcionesSeleccionadas = $prefill['recepciones_seleccionadas'] ?? [];
        $contabilizado = ($data->estado ?? '') === ComprobanteProveedorEstados::CONTABILIZADO;
        $tienePagos = $comprobanteId
            ? ComprobanteProveedorPagoSupport::tienePagosAplicados((int) $comprobanteId)
            : false;
        $bloqueadoEdicion = $tienePagos
            || ($data->estado ?? '') === ComprobanteProveedorEstados::ANULADO;
        $puedeActualizar = (bool) $comprobanteId && ! $bloqueadoEdicion;
        $asientoPreview = ['activo' => ! $bloqueadoEdicion, 'es_preview' => true, 'lineas' => []];

        $monedaFacturaId = (int) ($data->moneda_id ?? 1);
        $cotizacionFactura = (float) ($data->cotizacion ?? 0);
        $fechaFactura = null;
        if ($data) {
            if ($data->fechacomprobante instanceof \DateTimeInterface) {
                $fechaFactura = $data->fechacomprobante->format('Y-m-d');
            } else {
                $fechaFactura = substr((string) ($data->fechacomprobante ?? ''), 0, 10) ?: null;
            }
        }
        $recepcionesDisponibles = $this->recepcionesSupport->enriquecerConImporteEnMonedaFactura(
            $recepcionesDisponibles,
            $monedaFacturaId,
            $cotizacionFactura,
            $fechaFactura,
        );

        if ($comprobanteId && $data) {
            $data->loadMissing([
                'comprobante_proveedor_recepciones.recepcion_proveedores.ordencompras',
                'comprobante_proveedor_recepciones.recepcion_proveedores.recepcion_proveedor_articulos.articulos',
                'comprobante_proveedor_recepciones.recepcion_proveedores.recepcion_proveedor_articulos.unidadesmedida',
            ]);
            $recepcionesSeleccionadas = $data->comprobante_proveedor_recepciones
                ->pluck('recepcion_proveedor_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($recepcionesSeleccionadas !== []) {
                $vinculadas = Recepcion_Proveedor::query()
                    ->with([
                        'ordencompras',
                        'recepcion_proveedor_articulos.articulos',
                        'recepcion_proveedor_articulos.unidadesmedida',
                    ])
                    ->whereIn('id', $recepcionesSeleccionadas)
                    ->get();
                $recepcionesDisponibles = $vinculadas
                    ->merge($recepcionesDisponibles)
                    ->unique('id')
                    ->values();
                $recepcionesDisponibles = $this->recepcionesSupport->enriquecerConImporteEnMonedaFactura(
                    $recepcionesDisponibles,
                    $monedaFacturaId,
                    $cotizacionFactura,
                    $fechaFactura,
                );
                $recepcionesDisponibles = $this->recepcionesSupport->enriquecerConArticulos($recepcionesDisponibles);
            }

            $asientoPreview = $this->asientoService->previewParaVista($data);
            if (! $bloqueadoEdicion) {
                $data->loadMissing([
                    'comprobante_proveedor_conceptos.concepto_ivacompras',
                    'proveedores',
                    'comprobante_proveedor_recepciones',
                ]);
                $asientoPreview['avisos'] = $this->asientoPreviewSupport->avisosFaltantes($data);
            }
        }

        $comObligatoria = false;
        $comPolitica = [
            'exige_flujo' => false,
            'es_anticipada' => false,
            'tiene_com' => false,
            'debe_asignar_com' => false,
            'permite_factura_anticipada' => false,
            'bloquea_sin_com' => false,
        ];
        if ($data) {
            $data->loadMissing(['ordencompras.sector_legajocompras', 'ordencompras.contrato_cuentacontables']);
            $oc = $data->ordencompras;
            $tieneCom = $recepcionesDisponibles->isNotEmpty();
            $fechaPolitica = null;
            if ($data->fechacomprobante instanceof \DateTimeInterface) {
                $fechaPolitica = $data->fechacomprobante->format('Y-m-d');
            } elseif (filled($data->fechacomprobante ?? null)) {
                $fechaPolitica = substr((string) $data->fechacomprobante, 0, 10);
            }
            $comPolitica = ComprobanteProveedorFlujoOcComFacSupport::resolverPolitica($oc, $tieneCom, $fechaPolitica);
            $comObligatoria = (bool) ($comPolitica['debe_asignar_com'] ?? false);

            $modoSugerido = ComprobanteProveedorFlujoOcComFacSupport::modoCargaSugerido(
                $comPolitica,
                (string) ($data->modo_carga ?? '')
            );
            if ($comObligatoria
                || ($comPolitica['permite_factura_anticipada'] ?? false)
                || ($comPolitica['contrato_vigente'] ?? false)) {
                $data->modo_carga = $modoSugerido;
            }
        }

        $toleranciaPct = 0.0;
        if ($data) {
            $data->loadMissing('ordencompras');
            $oc = $data->ordencompras;
            if ($oc) {
                $toleranciaPct = ComprobanteProveedorToleranciaImporteSupport::porcentajeParaOc(
                    (int) $oc->empresa_id,
                    (int) ($oc->centrocosto_id ?? 0) ?: null,
                );
            }
        }

        $recepcionesDisponibles = $this->recepcionesSupport->enriquecerConArticulos($recepcionesDisponibles);

        $cotizacionMeta = [
            'cotizacion_dia' => 1.0,
            'cotizacion_origen' => 'mn',
            'cotizacion_factura' => null,
        ];
        if ($data) {
            $fechaCot = '';
            if ($data->fechacomprobante instanceof \DateTimeInterface) {
                $fechaCot = $data->fechacomprobante->format('Y-m-d');
            } else {
                $fechaCot = substr((string) ($data->fechacomprobante ?? now()->format('Y-m-d')), 0, 10);
            }
            $monedaCot = (int) ($data->moneda_id ?? 1);
            $cotPrecarga = null;
            if (! empty($data->precarga_comprobante_proveedor_id)) {
                $data->loadMissing('precarga_comprobante_proveedores');
                $cotPrecarga = $data->precarga_comprobante_proveedores->cotizacion ?? null;
            }
            $cotizacionMeta = ComprobanteProveedorCotizacionSupport::resolverConReferenciaDia(
                $this->cotizacionQuery,
                $fechaCot,
                $monedaCot,
                $data->cotizacion ?? null,
                $cotPrecarga
            );
            // Si no había cotización usable en ME, completar con la del día.
            if (ComprobanteProveedorCotizacionSupport::esMonedaExtranjera($monedaCot)
                && (float) ($data->cotizacion ?? 0) <= 1.0
                && (float) $cotizacionMeta['cotizacion'] > 1.0) {
                $data->cotizacion = $cotizacionMeta['cotizacion'];
            }
        }

        return array_merge($prefill, [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'tipotransaccion_compra_query' => $this->tipotransaccionCompraRepository->all('*'),
            'concepto_ivacompra_query' => $this->conceptoIvacompraRepository->all(),
            'moneda_query' => $this->monedaRepository->all(),
            'modos_carga' => ComprobanteProveedorModoCarga::todos(),
            'origenes_entrada' => ComprobanteProveedorOrigenEntrada::todos(),
            'estados' => ComprobanteProveedorEstados::todos(),
            'recepciones_disponibles' => $recepcionesDisponibles,
            'recepciones_seleccionadas' => $recepcionesSeleccionadas,
            'com_obligatoria' => $comObligatoria,
            'com_politica' => $comPolitica,
            'com_tolerancia_pct' => $toleranciaPct,
            'com_resolucion' => $prefill['com_resolucion'] ?? $this->resolverComResolucionFormulario($data, $recepcionesSeleccionadas),
            'asientoPreview' => $asientoPreview,
            'mostrarSolapaAsiento' => ! $bloqueadoEdicion,
            'tiene_pagos' => $tienePagos,
            'bloqueado_edicion' => $bloqueadoEdicion,
            'puede_actualizar' => $puedeActualizar,
            'mostrarSolapaCom' => ! (($comPolitica['contrato_vigente'] ?? false) && ! ($comPolitica['contrato_requiere_recepcion'] ?? true))
                && (
                    $comObligatoria
                    || count($recepcionesSeleccionadas) > 0
                    || ($comPolitica['permite_factura_anticipada'] ?? false)
                    || ($comPolitica['bloquea_sin_com'] ?? false)
                    || (string) ($data->modo_carga ?? '') === ComprobanteProveedorModoCarga::ASIGNA_RECEPCION
                ),
            // Match SKU/precio off en AGG: ocultar solapa vacía; mostrar si hay líneas (OCR) o flags activos.
            'mostrarSolapaArticulos' => $this->mostrarSolapaArticulosFormulario(
                (int) ($data->empresa_id ?? 0),
                $prefill['articulos'] ?? collect()
            ),
            'conceptos_cuenta_meta' => $this->asientoPreviewSupport->metaConceptosParaCliente(
                $this->conceptoIvacompraRepository->all(),
                (int) ($data->empresa_id ?? 0) ?: null
            ),
            'cotizacion_dia' => $cotizacionMeta['cotizacion_dia'],
            'cotizacion_origen' => $cotizacionMeta['cotizacion_origen'],
            'cotizacion_factura' => $cotizacionMeta['cotizacion_factura'],
        ]);
    }

    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;

        return ComprobanteProveedorListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $empresaDefault ? (int) $empresaDefault : null
        );
    }

    private function resolverRecepcionesDisponiblesFormulario(
        mixed $data,
        int $ordencompraId,
        mixed $comprobanteId,
    ): \Illuminate\Support\Collection {
        $coleccion = collect();

        if ($ordencompraId > 0) {
            $coleccion = $this->recepcionesSupport->listarDisponibles($ordencompraId, $comprobanteId);
        }

        $proveedorId = (int) ($data->proveedor_id ?? 0);
        $empresaId = (int) ($data->empresa_id ?? 0);
        if ($proveedorId <= 0 || $empresaId <= 0) {
            return $coleccion;
        }

        $data?->loadMissing('ordencompras');
        $sectorId = $data?->ordencompras?->sector_legajocompra_id;
        if (! $sectorId && $ordencompraId > 0) {
            $sectorId = Ordencompra::query()->whereKey($ordencompraId)->value('sector_legajocompra_id');
        }

        $legajo = $this->recepcionesSupport->listarSinFacturarEnLegajo(
            $proveedorId,
            $empresaId,
            $sectorId ? (int) $sectorId : null,
            $comprobanteId,
        );

        return $legajo->merge($coleccion)->unique('id')->values();
    }

    /** @param list<int> $recepcionesSeleccionadas */
    private function resolverComResolucionFormulario(mixed $data, array $recepcionesSeleccionadas): ?array
    {
        if (! $data || ($data->modo_carga ?? '') !== ComprobanteProveedorModoCarga::ASIGNA_RECEPCION) {
            return null;
        }

        $precargaId = (int) ($data->precarga_comprobante_proveedor_id ?? 0);
        if ($precargaId <= 0) {
            return null;
        }

        $precarga = Precarga_Comprobante_Proveedor::query()
            ->with(['proveedores', 'precarga_comprobante_proveedor_conceptos'])
            ->find($precargaId);

        if (! $precarga) {
            return null;
        }

        $data->loadMissing('ordencompras');
        $resolucion = $this->comLegajoResolucion->resolverDesdePrecarga($precarga, $data->ordencompras);

        if ($recepcionesSeleccionadas !== []) {
            return [
                'ambigua' => false,
                'mensaje' => null,
                'importe_comparacion' => $resolucion['com_resolucion']['importe_comparacion'],
                'importe_comparacion_etiqueta' => $resolucion['com_resolucion']['importe_comparacion_etiqueta'],
                'ordencompra_id' => (int) ($data->ordencompra_id ?? 0) ?: null,
            ];
        }

        return $resolucion['com_resolucion'];
    }

    /**
     * Solapa Artículos: uso operativo = match SKU/precio (flags).
     * Si están off (AGG), solo mostrar cuando ya hay líneas (OCR/precarga/edición).
     *
     * @param  \Illuminate\Support\Collection<int, mixed>|iterable<mixed>  $articulos
     */
    private function mostrarSolapaArticulosFormulario(int $empresaId, iterable $articulos): bool
    {
        if (old('articulo_skus') !== null || old('articulo_skus_marker') !== null) {
            return true;
        }

        $cfg = ComprobanteProveedorControlesConfigSupport::paraEmpresa($empresaId);
        if (! empty($cfg['match_lineas_activo']) && ! empty($cfg['activo'])) {
            return true;
        }

        if ($articulos instanceof \Illuminate\Support\Collection) {
            return $articulos->isNotEmpty();
        }

        return count(is_countable($articulos) ? $articulos : iterator_to_array($articulos)) > 0;
    }
}
