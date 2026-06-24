<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ComprobanteProveedorListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionComprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Archivo;
use App\Models\Compras\Proveedor;
use App\Repositories\Compras\Comprobante_ProveedorRepositoryInterface;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Repositories\Compras\Tipotransaccion_CompraRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ComprobanteProveedorPersistenciaService;
use App\Services\Compras\ComprobanteProveedorPrefillService;
use App\Services\Compras\ComprobanteProveedorRecepcionesSupport;
use App\Services\Compras\ComprobanteProveedorContabilizarService;
use App\Services\Compras\ComprobanteProveedorAsientoService;
use App\Support\Compras\ComprobanteProveedorArchivoPathSupport;
use App\Support\Compras\ComprobanteProveedorArchivoTipos;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorAsientoPreviewSupport;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use App\Models\Compras\Ordencompra;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorNumeroOcSupport;
use App\Services\Arca\ConstanciaInscripcionService;
use App\Support\Ventas\ArcaPadronImpuestosClienteValidacion;
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
        private ComprobanteProveedorPrefillService $prefillService,
        private ComprobanteProveedorPersistenciaService $persistenciaService,
        private PrecargaFacturaScanPathResolver $facturaScanPathResolver,
        private ComprobanteProveedorArchivoPathSupport $archivoPathSupport,
        private ComprobanteProveedorContabilizarService $contabilizarService,
        private ComprobanteProveedorRecepcionesSupport $recepcionesSupport,
        private ComprobanteProveedorAsientoService $asientoService,
        private ComprobanteProveedorAsientoPreviewSupport $asientoPreviewSupport,
        private PrecargaProveedorNumeroOcSupport $numeroOcSupport,
    ) {}

    public function index(Request $request)
    {
        can('listar-comprobante-proveedor');

        $busqueda = $request->input('busqueda');
        $datas = $this->comprobanteRepository->leeComprobanteProveedor($busqueda, true);

        return view('compras.comprobante_proveedor.index', compact('datas', 'busqueda'));
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-comprobante-proveedor');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (! $formato) {
            return redirect()->route('comprobante_proveedor', array_filter([
                'busqueda' => $busqueda ?? $request->input('busqueda'),
            ]));
        }

        switch ($formato) {
            case 'PDF':
                $datas = $this->comprobanteRepository->leeComprobanteProveedor($busqueda, false);

                $view = \View::make('compras.comprobante_proveedor.listado', compact('datas', 'busqueda'))
                    ->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_comprobante_proveedor';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new ComprobanteProveedorListadoExport($this->comprobanteRepository))
                    ->parametros($busqueda)
                    ->download('comprobante_proveedor.xlsx');

            case 'CSV':
                return (new ComprobanteProveedorListadoExport($this->comprobanteRepository))
                    ->parametros($busqueda)
                    ->download('comprobante_proveedor.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('comprobante_proveedor', array_filter([
            'busqueda' => $busqueda,
        ]));
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
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errores', ['No se pudo guardar el comprobante: '.$e->getMessage()]);
        }

        return redirect()
            ->route('editar_comprobante_proveedor', ['id' => $comprobante->id])
            ->with('mensaje', 'Comprobante de proveedor guardado en borrador.');
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
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errores', ['No se pudo actualizar el comprobante: '.$e->getMessage()]);
        }

        return redirect()
            ->route('editar_comprobante_proveedor', ['id' => $id, 'solapa' => 'asiento'])
            ->with('mensaje', 'Comprobante de proveedor actualizado.');
    }

    public function previewAsientoContable(Request $request, ?int $id = null): JsonResponse
    {
        can('editar-comprobante-proveedor');

        $existente = null;
        if ($id) {
            $existente = $this->comprobanteRepository->find($id);
            if (! $existente) {
                abort(404);
            }
        }

        $asientoPreview = $this->asientoService->previewDesdeFormulario($request, $existente);
        $comprobanteVista = $existente
            ? $this->asientoPreviewSupport->construirDesdeRequest($request, $existente)
            : $this->asientoPreviewSupport->construirDesdeRequest($request);

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

        if ($request->ajax()) {
            $flBorro = $this->comprobanteRepository->delete($id);

            return response()->json(['mensaje' => $flBorro ? 'ok' : 'error']);
        }

        abort(404);
    }

    public function generarDesdePrecarga(int $precargaId)
    {
        can('crear-comprobante-proveedor');

        try {
            $comprobante = $this->persistenciaService->generarBorradorDesdePrecarga($precargaId);
        } catch (\Throwable $e) {
            return redirect()
                ->route('precarga_comprobante_proveedor')
                ->with('errores', ['No se pudo generar el comprobante: '.$e->getMessage()]);
        }

        return redirect()
            ->route('editar_comprobante_proveedor', ['id' => $comprobante->id])
            ->with('mensaje', 'Comprobante generado desde precarga. Revise datos y conceptos.');
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
            ->route('editar_comprobante_proveedor', ['id' => $id, 'solapa' => 'asiento'])
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

    /** @param array<string, mixed> $prefill */
    private function datosFormulario(array $prefill): array
    {
        $data = $prefill['data'] ?? null;
        $ordencompraId = (int) ($data->ordencompra_id ?? 0);
        $comprobanteId = $data->id ?? null;

        $recepcionesDisponibles = $ordencompraId > 0
            ? $this->recepcionesSupport->listarDisponibles($ordencompraId, $comprobanteId)
            : collect();

        $recepcionesSeleccionadas = [];
        $contabilizado = ($data->estado ?? '') === ComprobanteProveedorEstados::CONTABILIZADO;
        $asientoPreview = ['activo' => ! $contabilizado, 'es_preview' => true, 'lineas' => []];

        if ($comprobanteId && $data) {
            $data->loadMissing('comprobante_proveedor_recepciones');
            $recepcionesSeleccionadas = $data->comprobante_proveedor_recepciones
                ->pluck('recepcion_proveedor_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $asientoPreview = $this->asientoService->previewParaVista($data);
            if (! $contabilizado) {
                $data->loadMissing([
                    'comprobante_proveedor_conceptos.concepto_ivacompras',
                    'proveedores',
                    'comprobante_proveedor_recepciones',
                ]);
                $asientoPreview['avisos'] = $this->asientoPreviewSupport->avisosFaltantes($data);
            }
        }

        return array_merge($prefill, [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'tipotransaccion_compra_query' => $this->tipotransaccionCompraRepository->all('*'),
            'concepto_ivacompra_query' => $this->conceptoIvacompraRepository->all(),
            'modos_carga' => ComprobanteProveedorModoCarga::todos(),
            'origenes_entrada' => ComprobanteProveedorOrigenEntrada::todos(),
            'estados' => ComprobanteProveedorEstados::todos(),
            'recepciones_disponibles' => $recepcionesDisponibles,
            'recepciones_seleccionadas' => $recepcionesSeleccionadas,
            'asientoPreview' => $asientoPreview,
            'mostrarSolapaAsiento' => ! $contabilizado,
            'conceptos_cuenta_meta' => $this->asientoPreviewSupport->metaConceptosParaCliente(
                $this->conceptoIvacompraRepository->all()
            ),
        ]);
    }
}
