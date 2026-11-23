<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\OrdencompraLegajoBandejaExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\OrdencompraGestionService;
use App\Services\Compras\OrdencompraLegajoBandejaPaqueteService;
use App\Services\Compras\OrdencompraLegajoBandejaService;
use App\Services\Compras\OrdencompraLegajoScanAnitaDescartarService;
use App\Services\Stock\RecepcionProveedorPdfService;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use App\Support\Compras\OrdencompraLegajoAnitaScanFacturaSupport;
use App\Support\Compras\OrdencompraLegajoBandejaFiltros;
use App\Support\Compras\OrdencompraLegajoBandejaListadoColumnas;
use App\Support\Compras\OrdencompraLegajoBandejaListadoPreferenciasUsuario;
use App\Support\Compras\OrdencompraListadoFiltros;
use App\Support\Compras\OrdencompraSectorVisibilidadSupport;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVistaSupport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class OrdencompraLegajoBandejaController extends Controller
{
    public function __construct(
        private OrdencompraLegajoBandejaService $service,
        private OrdencompraLegajoBandejaPaqueteService $paqueteService,
        private OrdencompraGestionService $gestionService,
        private RecepcionProveedorPdfService $comPdfService,
        private EmpresaRepositoryInterface $empresaRepository,
        private OrdencompraLegajoScanAnitaDescartarService $scanDescartarService,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->autorizar();

        if (OrdencompraSectorVisibilidadSupport::esUsuarioSectorCuentasAPagar()) {
            if (! OrdencompraLegajoBandejaFiltros::requestTraeContexto($request)) {
                $guardados = OrdencompraLegajoBandejaFiltros::guardados();
                if ($guardados === []) {
                    $guardados = OrdencompraLegajoBandejaFiltros::defaultsCuentasAPagarQuery();
                }

                return redirect()->route('consultar_legajo_compra', $guardados);
            }
        }

        $armado = $this->armarListado($request);
        if ($armado instanceof \Illuminate\Http\RedirectResponse) {
            return $armado;
        }

        if (OrdencompraSectorVisibilidadSupport::esUsuarioSectorCuentasAPagar()) {
            $persistir = $armado['filtrosQuery'] ?? [];
            $page = (int) $request->query('page', 0);
            if ($page > 1) {
                $persistir['page'] = $page;
            }
            OrdencompraLegajoBandejaFiltros::persistir($persistir);
        }

        return view('compras.legajo_bandeja.index', $armado);
    }

    public function previewWorkbench(Request $request)
    {
        $this->autorizar();

        $filtros = $this->filtrosDesdeRequest($request);
        $filtros['_per_page'] = ListadoDisenadorPreviewSupport::LIMITE_MUESTRA;
        $layout = OrdencompraLegajoBandejaListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
        $perPage = (int) ($filtros['_per_page'] ?? 20);
        $page = $this->service->paginar($filtros, max(1, $perPage));
        $total = (int) $page->total();
        $filas = $page->getCollection();
        $orden = ListadoOrdenamientoSupport::normalizar(
            $request->input('sort', $filtros['sort'] ?? []),
            OrdencompraLegajoBandejaFiltros::camposOrdenables()
        );
        $agrupar = ListadoAgrupacionSupport::normalizar(
            $request->input('group', $filtros['agrupar'] ?? []),
            OrdencompraLegajoBandejaFiltros::camposOrdenables()
        );
        $filtrosCortes = $filtros;
        $filtrosCortes['agrupar'] = $agrupar;
        $cortes = $agrupar !== []
            ? $this->service->cortes($filtrosCortes)
            : ['activo' => false];

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            $agrupar,
            $filas,
            static fn ($row, string $key): string => OrdencompraLegajoBandejaListadoColumnas::valorCelda($row, $key),
            $total,
            $etiquetas,
            $cortes
        ));
    }

    public function guardarVistaListado(Request $request)
    {
        $this->autorizar();

        $filtros = $this->filtrosDesdeRequest($request);
        $layout = OrdencompraLegajoBandejaListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $orden = $filtros['sort'] ?? [];
        $vista = ListadoVistaSupport::guardar(
            OrdencompraLegajoBandejaListadoColumnas::RECURSO,
            (int) auth()->id(),
            (string) $request->input('nombre', ''),
            [
                'modo' => $filtros['modo'] ?? OrdencompraLegajoBandejaFiltros::MODO_TODOS,
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
            return redirect()
                ->route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryString($filtros))
                ->with('error', 'No se pudo guardar la vista.');
        }
        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));
        $qs = OrdencompraLegajoBandejaFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs['vista_id'] = $vista->id;

        return redirect()->route('consultar_legajo_compra', $qs)
            ->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public function eliminarVistaListado(int $id)
    {
        $this->autorizar();
        $ok = ListadoVistaSupport::eliminar(
            $id,
            OrdencompraLegajoBandejaListadoColumnas::RECURSO,
            (int) auth()->id()
        );

        return redirect()->route('consultar_legajo_compra', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        $this->autorizar();
        $layout = OrdencompraLegajoBandejaListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;
        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            $vista = ListadoVistaSupport::findParaUsuario(
                $vistaId,
                OrdencompraLegajoBandejaListadoColumnas::RECURSO,
                (int) auth()->id()
            );
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            OrdencompraLegajoBandejaListadoPreferenciasUsuario::persistirGrillaEstandar($layout);
        }
        $filtros = $this->filtrosDesdeRequest($request);
        $qs = OrdencompraLegajoBandejaFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs[$vistaId > 0 ? 'vista_id' : 'vista_estandar'] = $vistaId > 0 ? $vistaId : 1;

        return redirect()->route('consultar_legajo_compra', $qs)->with('mensaje', 'Grilla actualizada.');
    }

    public function guardarEtiquetasListado(Request $request)
    {
        $this->autorizar();
        $etiquetas = $request->input('etiquetas', []);
        if (! is_array($etiquetas)) {
            $etiquetas = [];
        }
        ListadoColumnaEtiquetaSupport::guardar(
            OrdencompraLegajoBandejaListadoColumnas::RECURSO,
            $etiquetas,
            array_keys(OrdencompraLegajoBandejaListadoColumnas::catalogoActivo())
        );

        return redirect()->route(
            'consultar_legajo_compra',
            OrdencompraLegajoBandejaFiltros::paraQueryString($this->filtrosDesdeRequest($request))
        )->with('mensaje', 'Etiquetas actualizadas.');
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        $this->autorizar();

        ini_set('memory_limit', '512M');

        $filtros = $this->filtrosDesdeRequest($request);
        $coleccion = $this->service->listar($filtros);
        $filas = $coleccion->all();
        $titulo = $this->tituloVista((string) ($filtros['vista'] ?? ''));
        $subtitulo = $this->subtitulo($filtros);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('compras.legajo_bandeja.listado', compact(
                    'filas',
                    'titulo',
                    'subtitulo',
                ))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'legajos_compras_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new OrdencompraLegajoBandejaExport($filas, $titulo, $subtitulo))
                    ->download('legajos_compras.xlsx');

            case 'CSV':
                return (new OrdencompraLegajoBandejaExport($filas, $titulo, $subtitulo))
                    ->download('legajos_compras.csv', Excel::CSV);
        }

        return redirect()->route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryString($filtros));
    }

    public function historia(int $id)
    {
        $this->autorizar();
        $oc = $this->ocParaLectura($id);

        return response()->json($this->gestionService->leerHistoriaLegajo((int) $oc->id));
    }

    public function paquete(int $id)
    {
        $this->autorizar();
        $oc = $this->ocParaLectura($id);

        return response()->json($this->paqueteService->paquete($oc));
    }

    public function asignarCom(Request $request, int $id)
    {
        $this->autorizar();
        if (! $this->puedeAsignarCom()) {
            can('actualizar-ordencompra');
        }

        // La referencia de factura es un id de precarga o el sintético "anita-N" / "cp-N".
        $refFactura = ['nullable', 'regex:/^(\d+|anita-\d+|cp-\d+)$/i'];
        $request->validate([
            'asignaciones' => 'sometimes|array|max:200',
            'asignaciones.*.precarga_id' => $refFactura,
            'asignaciones.*.recepcion_ids' => 'nullable|array|max:200',
            'asignaciones.*.recepcion_ids.*' => 'nullable|integer|min:0',
            'precarga_id' => $refFactura,
            'recepcion_ids' => 'sometimes|array|max:200',
            'recepcion_ids.*' => 'nullable|integer|min:0',
        ]);

        $oc = $this->paqueteService->encontrarOcConsulta($id);
        $asignaciones = $request->input('asignaciones');
        if (is_array($asignaciones) && $asignaciones !== []) {
            $this->paqueteService->asignarMultiples($oc, $asignaciones);
            $mensaje = 'Asignaciones de COM guardadas para los comprobantes del legajo.';
        } else {
            $facturaRef = (string) $request->input('precarga_id', '');
            $recepcionIds = (array) $request->input('recepcion_ids', []);
            $this->paqueteService->asignar($oc, $facturaRef, $recepcionIds);
            $mensaje = $recepcionIds === [] || $recepcionIds === ['']
                ? 'Se quitó la asignación de COM de la factura.'
                : 'COM asignada a la factura. Cuentas a pagar puede cargar el comprobante.';
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'mensaje' => $mensaje,
                'paquete' => $this->paqueteService->paquete($oc),
            ]);
        }

        return redirect()
            ->route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryString($this->filtrosDesdeRequest($request)))
            ->with('mensaje', $mensaje);
    }

    public function marcarPendienteEntrega(Request $request, int $id)
    {
        $this->autorizar();
        if (! $this->puedeAsignarCom()) {
            can('actualizar-ordencompra');
        }

        $validated = $request->validate([
            'precarga_ids' => 'required|array|min:1|max:50',
            'precarga_ids.*' => 'integer|min:1',
        ]);

        $oc = $this->paqueteService->encontrarOcConsulta($id);
        $this->paqueteService->marcarPendienteEntrega($oc, $validated['precarga_ids']);

        $mensaje = 'Factura(s) marcadas como pendiente de entrega. Cuentas a pagar las omitirá hasta que se liberen.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'mensaje' => $mensaje,
                'paquete' => $this->paqueteService->paquete($oc),
                'gate' => OrdencompraEnvioCuentasAPagarGateSupport::preflightCuentasAPagar($oc),
            ]);
        }

        return redirect()
            ->route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryString($this->filtrosDesdeRequest($request)))
            ->with('mensaje', $mensaje);
    }

    public function liberarPendienteEntrega(Request $request, int $id)
    {
        $this->autorizar();
        if (! $this->puedeAsignarCom()) {
            can('actualizar-ordencompra');
        }

        $validated = $request->validate([
            'precarga_ids' => 'required|array|min:1|max:50',
            'precarga_ids.*' => 'integer|min:1',
        ]);

        $oc = $this->paqueteService->encontrarOcConsulta($id);
        $this->paqueteService->liberarPendienteEntrega($oc, $validated['precarga_ids']);

        $mensaje = 'Factura(s) liberadas. Asigná la COM y volvé a enviar el legajo a Cuentas a pagar.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'mensaje' => $mensaje,
                'paquete' => $this->paqueteService->paquete($oc),
                'gate' => OrdencompraEnvioCuentasAPagarGateSupport::preflightCuentasAPagar($oc),
            ]);
        }

        return redirect()
            ->route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryString($this->filtrosDesdeRequest($request)))
            ->with('mensaje', $mensaje);
    }

    public function corregirTipoDocumento(Request $request, int $id)
    {
        $this->autorizar();
        if (! $this->puedeAsignarCom()) {
            can('actualizar-ordencompra');
        }

        $validated = $request->validate([
            'precarga_id' => 'required|integer|min:1',
            'tipo' => ['required', 'string', 'max:8', 'regex:/^[A-Za-z]{2,6}$/'],
        ]);

        $oc = $this->paqueteService->encontrarOcVisible($id);
        $this->paqueteService->corregirTipoDocumento(
            $oc,
            (int) $validated['precarga_id'],
            (string) $validated['tipo']
        );
        $avisos = $this->paqueteService->ultimosAvisosConceptosTipo();
        $mensaje = 'Tipo de comprobante actualizado.';
        if ($avisos !== []) {
            $mensaje .= ' '.implode(' ', $avisos);
        }

        return response()->json([
            'ok' => true,
            'mensaje' => $mensaje,
            'avisos' => $avisos,
            'paquete' => $this->paqueteService->paquete($oc),
        ]);
    }

    public function descartarScanAnita(Request $request, int $id)
    {
        $this->autorizar();
        if (! $this->puedeAsignarCom()) {
            can('actualizar-ordencompra');
        }

        $validated = $request->validate([
            'documento_id' => 'required|integer|min:1',
            'motivo' => 'required|string|min:5|max:255',
        ]);

        $oc = $this->paqueteService->encontrarOcVisible($id);
        $this->scanDescartarService->descartarManual(
            $oc,
            (int) $validated['documento_id'],
            (string) $validated['motivo']
        );

        return response()->json([
            'ok' => true,
            'mensaje' => 'Factura escaneada descartada del legajo. Se puede deshacer desde el mismo panel.',
            'paquete' => $this->paqueteService->paquete($oc),
        ]);
    }

    public function revertirDescarteScanAnita(Request $request, int $id)
    {
        $this->autorizar();
        if (! $this->puedeAsignarCom()) {
            can('actualizar-ordencompra');
        }

        $validated = $request->validate([
            'documento_id' => 'required|integer|min:1',
            'motivo' => 'required|string|min:5|max:255',
        ]);

        $oc = $this->paqueteService->encontrarOcVisible($id);
        $this->scanDescartarService->revertir(
            $oc,
            (int) $validated['documento_id'],
            (string) $validated['motivo']
        );

        return response()->json([
            'ok' => true,
            'mensaje' => 'Se deshizo el descarte: la factura escaneada vuelve al legajo.',
            'paquete' => $this->paqueteService->paquete($oc),
        ]);
    }

    public function verFacturaPdf(Request $request, int $id, int $precarga)
    {
        $this->autorizar();
        $oc = $this->ocParaLectura($id);
        $precargaModel = $this->paqueteService->assertPrecargaDelLegajo($oc, $precarga);
        $path = $this->paqueteService->rutaFacturaPdf($precargaModel);
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

    public function verFacturaAnitaPdf(Request $request, int $id, int $documento)
    {
        $this->autorizar();
        $oc = $this->ocParaLectura($id);
        $oc->loadMissing('empresas:id,codigo,nombre');
        if (! OrdencompraLegajoAnitaScanFacturaSupport::perteneceAlLegajo($oc, $documento)) {
            abort(404, 'La factura escaneada no pertenece a este legajo.');
        }
        $path = OrdencompraLegajoAnitaScanFacturaSupport::rutaPdf($documento);
        if ($path === null) {
            abort(404, 'No se encontró el PDF escaneado en Anita.');
        }
        $nombre = basename($path);

        if ($request->boolean('inline', true)) {
            return response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            ]);
        }

        return response()->download($path, $nombre);
    }

    public function verComPdf(Request $request, int $id, int $recepcion)
    {
        $this->autorizar();
        $oc = $this->ocParaLectura($id);
        $this->paqueteService->assertComDelLegajo($oc, $recepcion);

        return $this->comPdfService->descargarCom($recepcion, $request->boolean('inline', true));
    }

    public function guardarNota(Request $request, int $id)
    {
        $this->autorizar();
        $oc = $this->paqueteService->encontrarOcVisible($id);

        $validated = $request->validate([
            'nota_legajo' => 'nullable|string|max:4000',
        ]);
        $nota = trim((string) ($validated['nota_legajo'] ?? ''));
        $oc->nota_legajo = $nota !== '' ? $nota : null;
        $oc->save();

        $mensaje = $nota !== ''
            ? 'Nota del legajo guardada.'
            : 'Nota del legajo eliminada.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'mensaje' => $mensaje,
                'nota_legajo' => $nota,
                'tiene_nota' => $nota !== '',
            ]);
        }

        return redirect()
            ->route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryString($this->filtrosDesdeRequest($request)))
            ->with('mensaje', $mensaje);
    }

    /**
     * @return array<string, mixed>|\Illuminate\Http\RedirectResponse
     */
    private function armarListado(Request $request): array|\Illuminate\Http\RedirectResponse
    {
        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $vistas = ListadoVistaSupport::listarParaUsuario(OrdencompraLegajoBandejaListadoColumnas::RECURSO, $usuarioId);
        $vistaActiva = null;
        $forzarEstandar = $request->boolean('vista_estandar')
            || $request->input('vista_modo') === 'estandar';

        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                OrdencompraLegajoBandejaListadoColumnas::RECURSO,
                $usuarioId
            );
        } elseif (
            ! $forzarEstandar
            && ! $request->has('filtro_valor')
            && ! $request->has('qbe')
            && ! $request->boolean('limpiar_filtros')
            && ! $request->boolean('filtro_limpiar')
            && ! $request->has('empresa_id')
            && ! $request->has('empresa_todas')
            && ! $request->has('vista')
            && ! $request->has('tab')
            && ! $request->has('atajo')
            && ! $request->has('nro_oc')
            && ! $request->has('nro_factura')
            && ! $request->has('nro_com')
            && ! $request->has('nro_op')
            && ! $request->has('presupuesto')
        ) {
            $vistaActiva = ListadoVistaSupport::defaultDelUsuario(OrdencompraLegajoBandejaListadoColumnas::RECURSO, $usuarioId)
                ?? ListadoVistaSupport::defaultDelRol(
                    OrdencompraLegajoBandejaListadoColumnas::RECURSO,
                    (int) session('rol_id')
                );
        }

        $filtrosRequest = ListadoVistaSupport::prepararQbeContraVista(
            $this->filtrosDesdeRequest($request),
            $request
        );
        $filtros = $filtrosRequest;
        if ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros = OrdencompraLegajoBandejaFiltros::fusionarDesdeVista($filtros, $vistaActiva->filtros_json);
        }
        unset($filtros['_qbe_explicito'], $filtros['_limpiar']);

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

        if ($request->boolean('quitar_orden')) {
            $filtros['sort'] = [];
            OrdencompraLegajoBandejaListadoPreferenciasUsuario::olvidarOrden();
            if ($vistaActiva) {
                ListadoVistaSupport::recordarOrdenYAgrupar(
                    $vistaActiva,
                    [],
                    $filtros['agrupar'] ?? []
                );
            }
            $params = OrdencompraLegajoBandejaFiltros::paraQueryString($filtros);
            if ($vistaActiva) {
                $params['vista_id'] = $vistaActiva->id;
            } elseif ($forzarEstandar) {
                $params['vista_estandar'] = 1;
            }

            return redirect()
                ->route('consultar_legajo_compra', $params)
                ->with('mensaje', 'Se volvió al orden estándar de la bandeja.');
        }

        if ($request->exists('sort')) {
            OrdencompraLegajoBandejaListadoPreferenciasUsuario::persistirOrden($filtros['sort'] ?? []);
        } elseif (ListadoOrdenamientoSupport::normalizar(
            $filtros['sort'] ?? [],
            OrdencompraLegajoBandejaFiltros::camposOrdenables()
        ) === []) {
            $ordenRecordado = OrdencompraLegajoBandejaListadoPreferenciasUsuario::leerOrden();
            if ($ordenRecordado !== []) {
                $filtros['sort'] = $ordenRecordado;
            }
        }

        $catalogo = OrdencompraLegajoBandejaListadoColumnas::catalogoActivo();
        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            OrdencompraLegajoBandejaListadoColumnas::RECURSO,
            $catalogo
        );
        if ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []) {
            $grillaLayout = OrdencompraLegajoBandejaListadoPreferenciasUsuario::normalizarLayout($vistaActiva->columnas_json);
        } else {
            $grillaLayout = OrdencompraLegajoBandejaListadoPreferenciasUsuario::grillaEstandar();
        }
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);

        $filas = $this->service->paginar($filtros);
        $cortes = ['activo' => false];
        if (ListadoAgrupacionSupport::normalizar(
            $filtros['agrupar'] ?? [],
            OrdencompraLegajoBandejaFiltros::camposOrdenables()
        ) !== []) {
            $cortes = $this->service->cortes($filtros);
        }

        $camposFiltro = OrdencompraLegajoBandejaFiltros::camposQbeDisponibles();
        foreach ($camposFiltro as $key => $meta) {
            $camposFiltro[$key]['label'] = $etiquetas[$key] ?? $etiquetasInstalacion[$key] ?? $meta['label'];
        }

        if ($vistaActiva) {
            $filtros['vista_id'] = $vistaActiva->id;
            unset($filtros['vista_estandar']);
        } elseif ($forzarEstandar) {
            $filtros['vista_estandar'] = 1;
            unset($filtros['vista_id']);
        }
        $filtros['columnas'] = implode(',', $columnasVisibles);
        $filtrosQuery = OrdencompraLegajoBandejaFiltros::paraQueryString($filtros);

        return [
            'filas' => $filas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => $camposFiltro,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'columnasVisibles' => $columnasVisibles,
            'grillaLayout' => $grillaLayout,
            'catalogoColumnas' => $catalogo,
            'etiquetasColumnas' => $etiquetas,
            'etiquetasInstalacion' => $etiquetasInstalacion,
            'vistasListado' => $vistas,
            'vistaActiva' => $vistaActiva,
            'workbenchListo' => ListadoVistaSupport::tablasDisponibles(),
            'cortes' => $cortes,
            'puede_actualizar' => can('actualizar-ordencompra', false),
            'puede_asignar_com' => $this->puedeAsignarCom(),
            'puede_cargar_cxp' => can('crear-comprobante-proveedor', false),
            'puede_enviar_pagos' => can('crear-comprobante-proveedor', false)
                || can('actualizar-ordencompra', false)
                || can('listar-legajo-compra', false),
            'puede_devolver_cxp' => can('editar-pagoproveedor', false)
                || can('crear-pagoproveedor', false)
                || can('actualizar-ordencompra', false)
                || can('listar-legajo-compra', false),
            'puede_devolver_compras' => can('crear-comprobante-proveedor', false)
                || can('actualizar-ordencompra', false)
                || can('editar-pagoproveedor', false)
                || can('crear-pagoproveedor', false)
                || can('listar-legajo-compra', false),
            'puede_ver_comprobante' => can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false),
            'puede_ver_pago' => can('editar-pagoproveedor', false) || can('listar-pagoproveedor', false),
            'puede_archivar' => can('actualizar-ordencompra', false)
                || can('editar-pagoproveedor', false)
                || can('crear-pagoproveedor', false)
                || can('listar-legajo-compra', false),
            'dias_recordatorio' => (int) config('compras.legajo.recordatorio_dias', 3),
            'alcanceSector' => OrdencompraSectorVisibilidadSupport::etiquetaAlcance(),
            'sinSectorAsignado' => OrdencompraSectorVisibilidadSupport::sinSectorAsignado(),
            'vistasPermitidas' => OrdencompraSectorVisibilidadSupport::vistasBandejaPermitidas(),
        ];
    }

    private function filtrosDesdeRequest(Request $request): array
    {
        $esCxp = OrdencompraSectorVisibilidadSupport::esUsuarioSectorCuentasAPagar();
        $traeEmpresa = $request->filled('empresa_id')
            || $request->boolean('empresa_todas')
            || $request->input('empresa_scope') === 'todas';

        // CxP sin empresa en la URL → Todas mis empresas. Resto: primera asignada.
        $empresaDefault = null;
        if (! $esCxp || $traeEmpresa) {
            $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
            $empresaDefault = $empresaDefault ? (int) $empresaDefault : null;
        }

        $filtros = OrdencompraLegajoBandejaFiltros::resolverDesdeRequest(
            $request,
            $empresaDefault
        );

        $vistaForzada = OrdencompraSectorVisibilidadSupport::vistaBandejaForzada();
        if ($vistaForzada !== null) {
            $filtros['vista'] = $vistaForzada;
        } elseif (! $request->has('vista')) {
            $filtros['vista'] = $this->vistaInicialSegunRol();
        }

        return $filtros;
    }

    private function vistaInicialSegunRol(): string
    {
        $porSector = OrdencompraSectorVisibilidadSupport::vistaBandejaForzada();
        if ($porSector !== null) {
            return $porSector;
        }

        $nombre = OrdencompraSectorVisibilidadSupport::nombreSectorUsuario();
        if ($nombre === OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_CUENTAS_A_PAGAR) {
            return OrdencompraLegajoBandejaFiltros::VISTA_CXP;
        }
        if ($nombre === OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_PAGOS) {
            return OrdencompraLegajoBandejaFiltros::VISTA_PAGOS;
        }

        $compras = can('actualizar-ordencompra', false);
        $cxp = can('crear-comprobante-proveedor', false);
        $pagos = can('editar-pagoproveedor', false) || can('crear-pagoproveedor', false);
        if ($cxp && ! $compras) {
            return OrdencompraLegajoBandejaFiltros::VISTA_CXP;
        }
        if ($pagos && ! $compras && ! $cxp) {
            return OrdencompraLegajoBandejaFiltros::VISTA_PAGOS;
        }

        return OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES;
    }

    private function autorizar(): void
    {
        if (can('listar-legajo-compra', false) || can('listar-ordencompra', false)) {
            return;
        }
        can('listar-legajo-compra');
    }

    /**
     * Lectura de paquete/PDF/historia: sector propio, o consulta global (seguimiento / ver todos).
     */
    private function ocParaLectura(int $id): \App\Models\Compras\Ordencompra
    {
        if (OrdencompraSectorVisibilidadSupport::puedeVerTodos()
            || can('listar-seguimiento-legajo-compra', false)) {
            return $this->paqueteService->encontrarOcConsulta($id);
        }

        return $this->paqueteService->encontrarOcVisible($id);
    }

    private function puedeAsignarCom(): bool
    {
        return can('actualizar-ordencompra', false)
            || can('crear-comprobante-proveedor', false)
            || can('editar-precarga-proveedores', false)
            || can('listar-legajo-compra', false);
    }

    private function tituloVista(string $vista): string
    {
        return match ($vista) {
            OrdencompraLegajoBandejaFiltros::VISTA_ESTADOS => 'Legajos — estados',
            OrdencompraLegajoBandejaFiltros::VISTA_HISTORICO => 'Legajos — histórico',
            default => 'Legajos — pendientes',
        };
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function subtitulo(array $filtros): string
    {
        $partes = [];
        $tab = (string) ($filtros['tab'] ?? '');
        if ($tab === OrdencompraLegajoBandejaFiltros::TAB_GASTRONOMIA) {
            $partes[] = 'Gastronomía';
        } elseif ($tab === OrdencompraLegajoBandejaFiltros::TAB_RESTO) {
            $partes[] = 'Resto de centros de costo';
        }
        if (! empty($filtros['atajo'])) {
            $partes[] = (string) $filtros['atajo'];
        }
        if (OrdencompraListadoFiltros::tieneCriteriosTexto($filtros)) {
            $partes[] = trim((string) ($filtros['valor'] ?? ''));
        }
        foreach (['nro_oc' => 'OC', 'nro_factura' => 'FC', 'nro_com' => 'COM', 'nro_op' => 'OP'] as $k => $etq) {
            if (! empty($filtros[$k])) {
                $partes[] = $etq.' '.$filtros[$k];
            }
        }

        return implode(' · ', $partes);
    }
}
