<?php

namespace App\Http\Controllers\Solicitudpago;

use App\Exports\Solicitudpago\SolicitudpagoListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionSolicitudpago;
use App\Models\Contable\Centrocosto;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Solicitudpago\FormapagosolRepositoryInterface;
use App\Repositories\Solicitudpago\Sector_SolicitudpagoRepositoryInterface;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Services\Solicitudpago\SolicitudpagoArchivosFusionService;
use App\Services\Solicitudpago\SolicitudpagoArbolIntegracionService;
use App\Services\Solicitudpago\SolicitudpagoCargaMasivaCsvService;
use App\Services\Solicitudpago\SolicitudpagoComprobantePdfService;
use App\Services\Solicitudpago\SolicitudpagoPaqueteMailPdfService;
use App\Services\Caja\IngresoEgresoAnularRevertirService;
use App\Support\Archivos\ArchivoAdjuntoCacheSupport;
use App\Support\Configuracion\ArbolAprobacionEnlaceSupport;
use App\Support\Solicitudpago\SolicitudpagoArchivoStorageSupport;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoListadoFiltros;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use App\Support\Solicitudpago\SolicitudpagoVisibilidadSupport;
use Illuminate\Http\Request;
use PDF;

class SolicitudpagoController extends Controller
{
    private const SESSION_FILTROS = 'solicitudpago_listado_filtros';

    public function __construct(
        private SolicitudpagoRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
        private Sector_SolicitudpagoRepositoryInterface $sectorRepository,
        private FormapagosolRepositoryInterface $formapagosolRepository,
        private MonedaRepositoryInterface $monedaRepository,
        private CentrocostoRepositoryInterface $centrocostoRepository,
        private SolicitudpagoArbolIntegracionService $arbolIntegracionService,
        private SolicitudpagoArchivosFusionService $archivosFusionService,
        private SolicitudpagoComprobantePdfService $comprobantePdfService,
        private SolicitudpagoPaqueteMailPdfService $paqueteMailPdfService,
        private SolicitudpagoCargaMasivaCsvService $cargaMasivaCsvService,
        private IngresoEgresoAnularRevertirService $ingresoEgresoAnularRevertirService,
    ) {
    }

    public function previewCargaMasiva(Request $request)
    {
        can('crear-solicitud-pago');
        $request->validate([
            'archivo' => [
                'required',
                'file',
                'max:10240',
                function (string $attribute, $value, $fail) {
                    if (! $value instanceof \Illuminate\Http\UploadedFile) {
                        $fail('Archivo inválido.');

                        return;
                    }
                    $ext = strtolower($value->getClientOriginalExtension() ?: '');
                    if (! in_array($ext, ['csv', 'txt'], true)) {
                        $fail('El archivo debe ser CSV.');
                    }
                },
            ],
        ], [
            'archivo.required' => 'Seleccione un archivo CSV.',
        ]);

        try {
            $preview = $this->cargaMasivaCsvService->preview($request->file('archivo'));

            return response()->json(['ok' => true] + $preview);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function confirmarCargaMasiva(Request $request)
    {
        can('crear-solicitud-pago');
        $request->validate([
            'token' => 'required|string|max:64',
        ]);

        try {
            $resultado = $this->cargaMasivaCsvService->confirmar((string) $request->input('token'));

            return response()->json(['ok' => true] + $resultado);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function index(Request $request)
    {
        can('listar-solicitud-pago');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $empresaDefault = $empresaDefault ? (int) $empresaDefault : null;

        // Limpiar filtros de texto: borra recuerdo de sesión y conserva empresa externa
        if ($request->boolean('limpiar_filtros')) {
            session()->forget(self::SESSION_FILTROS);
            $filtrosEmpresa = SolicitudpagoListadoFiltros::resolverDesdeRequest($request, null, $empresaDefault);

            return redirect()->route(
                'consultar_solicitudpago',
                SolicitudpagoListadoFiltros::paraQueryStringEmpresa($filtrosEmpresa)
            );
        }

        // Memoria de filtros: con query se persiste; sin query se restaura el último filtro
        if ($this->requestTraeFiltrosListado($request)) {
            $filtros = SolicitudpagoListadoFiltros::resolverDesdeRequest($request, null, $empresaDefault);
            if (! SolicitudpagoVisibilidadSupport::puedeVerTodasSinRestriccion()) {
                $filtros['alcance'] = SolicitudpagoVisibilidadSupport::ALCANCE_TODAS;
            }
            $filtrosQuery = SolicitudpagoListadoFiltros::paraQueryString($filtros);
            $page = (int) $request->input('page', 0);
            if ($page > 1) {
                $filtrosQuery['page'] = $page;
            }
            if (
                SolicitudpagoListadoFiltros::tieneCriteriosAplicados($filtros)
                || SolicitudpagoListadoFiltros::tieneAlcanceMiCentrocosto($filtros)
                || SolicitudpagoListadoFiltros::paraQueryStringEmpresa($filtros) !== []
                || $page > 1
            ) {
                session([self::SESSION_FILTROS => $filtrosQuery]);
            } else {
                session()->forget(self::SESSION_FILTROS);
            }
        } else {
            $guardados = session(self::SESSION_FILTROS, []);
            if (is_array($guardados) && $guardados !== []) {
                return redirect()->route('consultar_solicitudpago', $guardados);
            }
            $filtros = SolicitudpagoListadoFiltros::resolverDesdeRequest($request, null, $empresaDefault);
            $filtrosQuery = SolicitudpagoListadoFiltros::paraQueryString($filtros);
        }

        $camposFiltro = SolicitudpagoListadoFiltros::CAMPOS;
        $coleccion = $this->repository->leeSolicitudpago($filtros, true);
        $estado_enum = SolicitudpagoEstados::opciones();
        $tratamiento_enum = SolicitudpagoTratamientos::opciones();
        $limpiarFiltrosUrl = route(
            'consultar_solicitudpago',
            array_merge(SolicitudpagoListadoFiltros::paraQueryStringEmpresa($filtros), ['limpiar_filtros' => 1])
        );
        $puedeVerTodas = SolicitudpagoVisibilidadSupport::puedeVerTodasSinRestriccion();
        $alcanceListado = $filtros['alcance'] ?? SolicitudpagoVisibilidadSupport::ALCANCE_TODAS;
        $alcanceToggleUrl = null;
        if ($puedeVerTodas) {
            $paramsToggle = $filtrosQuery;
            unset($paramsToggle['page']);
            // alcance=todas debe ir explícito: si se omite, la sesión restaura mi_cc.
            $paramsToggle['alcance'] = $alcanceListado === SolicitudpagoVisibilidadSupport::ALCANCE_MI_CC
                ? SolicitudpagoVisibilidadSupport::ALCANCE_TODAS
                : SolicitudpagoVisibilidadSupport::ALCANCE_MI_CC;
            $alcanceToggleUrl = route('consultar_solicitudpago', $paramsToggle);
        }
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('solicitudpago.solicitudpago.index', compact(
            'coleccion',
            'filtros',
            'filtrosQuery',
            'camposFiltro',
            'estado_enum',
            'tratamiento_enum',
            'limpiarFiltrosUrl',
            'puedeVerTodas',
            'alcanceListado',
            'alcanceToggleUrl',
            'empresa_query'
        ));
    }

    /**
     * true si la request trae parámetros de filtro/paginación del listado.
     */
    private function requestTraeFiltrosListado(Request $request): bool
    {
        foreach ([
            'filtro_valor',
            'filtro_campo',
            'filtro_operador',
            'filtro_busqueda_rapida',
            'madre_hija',
            'estado',
            'tratamiento',
            'fecha_desde',
            'fecha_hasta',
            'alcance',
            'empresa_id',
            'empresa_todas',
            'empresa_scope',
            'page',
        ] as $key) {
            if ($request->query->has($key)) {
                return true;
            }
        }

        return false;
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-solicitud-pago');
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '300');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SolicitudpagoListadoFiltros::resolverDesdeRequest(
            $request,
            $busqueda,
            $empresaDefault ? (int) $empresaDefault : null
        );
        if (! SolicitudpagoVisibilidadSupport::puedeVerTodasSinRestriccion()) {
            $filtros['alcance'] = SolicitudpagoVisibilidadSupport::ALCANCE_TODAS;
        }
        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $datas = $this->repository->leeSolicitudpago($filtros, false);
                $titulo = 'Solicitudes de pago';
                $pdf = PDF::loadView('solicitudpago.solicitudpago.listado', compact('datas', 'titulo', 'filtros'));
                $pdf->setPaper('legal', 'landscape');
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $pdf->save($path.'/listado_solicitudpago.pdf');

                return response()->download($path.'/listado_solicitudpago.pdf');
            case 'EXCEL':
                return (new SolicitudpagoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('solicitudpago.xlsx');
            case 'CSV':
                return (new SolicitudpagoListadoExport($this->repository))
                    ->parametros($filtros, true)
                    ->download('solicitudpago.csv', \Maatwebsite\Excel\Excel::CSV);
            default:
                return redirect()->route('consultar_solicitudpago', SolicitudpagoListadoFiltros::paraQueryString($filtros));
        }
    }

    public function crear()
    {
        can('crear-solicitud-pago');

        return view('solicitudpago.solicitudpago.crear', $this->datosFormulario());
    }

    public function guardar(ValidacionSolicitudpago $request)
    {
        try {
            $data = $request->validated();
            $data['archivos_nuevos'] = array_values(array_filter(
                array_merge(
                    (array) $request->file('nombrearchivos', []),
                    (array) $request->file('archivos_nuevos', [])
                )
            ));
            $sp = $this->repository->create($data);
            $pdfUrl = route('imprimir_pdf_solicitudpago', $sp->id);

            $redirect = redirect()
                ->route('editar_solicitudpago', $sp->id)
                ->with('mensaje', 'Solicitud de pago #'.$sp->codigo.' creada con éxito.')
                ->with('abrir_pdf_solicitudpago', $pdfUrl);

            if (
                config('solicitudpago.arbol_al_crear', true)
                && ($sp->estado ?? '') === SolicitudpagoEstados::EMITIDA
                && ! $this->arbolIntegracionService->findPorSolicitudpago((int) $sp->id)->count()
            ) {
                $redirect->with('advertencias', [
                    'No se generaron pendientes de aprobación ni correos: el concepto de la solicitud no tiene firmantes '
                    .'operativos (solapa Usuarios del concepto) aplicables al monto. Configure el árbol en el concepto y use «Reenviar al árbol».',
                ]);
            }

            return $redirect;
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta'
            || $request->query('vista') === 'consulta';
        if ($soloConsulta) {
            if (! can('listar-solicitud-pago', false) && ! can('editar-solicitud-pago', false)) {
                abort(403);
            }
        } else {
            can('editar-solicitud-pago');
        }

        $data = $this->repository->findOrFail($id);
        $this->asegurarAccesoSolicitud((int) $id);
        $ocultarVolver = $soloConsulta;
        $bloqueaEdicion = SolicitudpagoEstados::bloqueaEdicion($data->estado ?? '');
        $puedeActualizar = can('actualizar-solicitud-pago', false) && ! $bloqueaEdicion;
        if ($bloqueaEdicion) {
            $soloConsulta = true;
        }
        $tienePendientesCorreoArbol = $this->arbolIntegracionService->tienePendientesConCorreo((int) $id);
        $arbolMovimientos = $this->arbolIntegracionService->findPorSolicitudpago((int) $id);

        return view('solicitudpago.solicitudpago.editar', array_merge(
            $this->datosFormulario($data),
            compact('data', 'soloConsulta', 'ocultarVolver', 'puedeActualizar', 'bloqueaEdicion', 'tienePendientesCorreoArbol', 'arbolMovimientos')
        ));
    }

    /**
     * Visualización desde enlace del árbol de aprobación (correo).
     * Acceso por hashvisualizar: no exige permiso de edición ni filtro de visibilidad del ABM.
     */
    public function visualizar($id, $hash = null)
    {
        $hash = is_string($hash) ? $hash : '';
        $flEncontro = $hash === '';
        if ($hash !== '') {
            foreach ($this->arbolIntegracionService->findPorSolicitudpago((int) $id) as $movimiento) {
                if (ArbolAprobacionEnlaceSupport::hashesCoinciden($hash, (string) ($movimiento->hashvisualizar ?? ''))) {
                    $flEncontro = true;
                    break;
                }
            }
        }

        if (! $flEncontro) {
            return redirect()->route('inicio')
                ->with('mensaje', 'No tiene permisos para visualizar la solicitud de pago');
        }

        if ($hash === '') {
            can('editar-solicitud-pago');
            $this->asegurarAccesoSolicitud((int) $id);
        }

        $data = $this->repository->findOrFail($id);
        $soloConsulta = true;
        $ocultarVolver = false;
        $puedeActualizar = false;
        $acceso_visualizacion_por_hash = $hash !== '';
        $tienePendientesCorreoArbol = $this->arbolIntegracionService->tienePendientesConCorreo((int) $id);
        $arbolMovimientos = $this->arbolIntegracionService->findPorSolicitudpago((int) $id);

        return view('solicitudpago.solicitudpago.editar', array_merge(
            $this->datosFormulario($data),
            compact(
                'data',
                'soloConsulta',
                'ocultarVolver',
                'puedeActualizar',
                'acceso_visualizacion_por_hash',
                'tienePendientesCorreoArbol',
                'arbolMovimientos'
            )
        ));
    }

    public function actualizar(ValidacionSolicitudpago $request, $id)
    {
        can('actualizar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);
        $actual = $this->repository->findOrFail($id);
        if (SolicitudpagoEstados::bloqueaEdicion($actual->estado ?? '')) {
            return redirect()
                ->route('editar_solicitudpago', $id)
                ->with('mensaje_error', 'La solicitud en estado Controlada no puede modificarse. Para anularla use Suspender.');
        }

        try {
            $data = $request->validated();
            $data['archivos_nuevos'] = array_values(array_filter(
                array_merge(
                    (array) $request->file('nombrearchivos', []),
                    (array) $request->file('archivos_nuevos', [])
                )
            ));
            if ($request->boolean('archivos_gestionados')) {
                $data['archivo_ids_existentes'] = $request->input('archivo_ids_existentes', []);
            }
            $this->repository->update($data, $id);

            if ($request->input('origen') === 'modal_consulta' || $request->input('vista') === 'consulta') {
                return redirect()
                    ->route('editar_solicitudpago', [
                        'id' => $id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ])
                    ->with('mensaje', 'Solicitud de pago actualizada con éxito');
            }

            return redirect('solicitudpago/solicitudpago')->with('mensaje', 'Solicitud de pago actualizada con éxito');
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    public function familiaVinculos($id)
    {
        can('listar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);

        $data = $this->repository->findOrFail($id);

        // Si es hija, mostrar el plan de la madre
        if ((int) ($data->solicitudpago_madre_id ?? 0) > 0 && $data->madre) {
            $data = $this->repository->findOrFail((int) $data->solicitudpago_madre_id);
        }

        if (($data->cuotas ?? collect())->isEmpty()) {
            return response(
                '<div class="alert alert-secondary mb-0">Esta solicitud no tiene plan de cuotas.</div>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }

        return view('solicitudpago.solicitudpago.partials.familia_vinculos', [
            'data' => $data,
            'modo_modal' => true,
        ]);
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-solicitud-pago');
        if (! SolicitudpagoEstados::esAdministradorSesion()) {
            if ($request->ajax()) {
                return response()->json([
                    'mensaje' => 'ng',
                    'error' => 'Solo el rol administrador puede borrar. Para anular use Suspender.',
                ], 403);
            }

            return redirect()
                ->route('consultar_solicitudpago')
                ->with('mensaje_error', 'Solo el rol administrador puede borrar solicitudes. Para anular use Suspender.');
        }
        $this->asegurarAccesoSolicitud((int) $id);

        if ($request->ajax()) {
            return response()->json(['mensaje' => $this->repository->delete($id) ? 'ok' : 'ng']);
        }

        abort(404);
    }

    public function suspender($id)
    {
        can('actualizar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);
        $this->repository->cambiarEstado((int) $id, SolicitudpagoEstados::SUSPENDIDA, 'SUSPENDIDA');

        return redirect()->route('editar_solicitudpago', $id)->with('mensaje', 'Solicitud suspendida');
    }

    public function levantarSuspension($id)
    {
        can('actualizar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);
        $this->repository->cambiarEstado((int) $id, SolicitudpagoEstados::EMITIDA, 'Levanta suspensión');

        return redirect()->route('editar_solicitudpago', $id)->with('mensaje', 'Suspensión levantada');
    }

    public function reenviarArbolAprobacion($id)
    {
        can('actualizar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);

        try {
            $resultado = $this->arbolIntegracionService->reenviarAlArbolAprobacion((int) $id);
        } catch (\Throwable $e) {
            return redirect()->route('editar_solicitudpago', $id)
                ->with('mensaje_error', $e->getMessage());
        }

        return redirect()->route('editar_solicitudpago', $id)
            ->with(
                ! empty($resultado['ok']) ? 'mensaje' : 'mensaje_error',
                $resultado['mensaje'] ?? 'No se pudo reenviar al árbol de aprobación.'
            );
    }

    public function reenviarCorreoArbol($id)
    {
        can('actualizar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);

        try {
            $resultado = $this->arbolIntegracionService->reenviarCorreoNivelPendiente((int) $id);
        } catch (\Throwable $e) {
            return redirect()->route('editar_solicitudpago', $id)
                ->with('mensaje_error', $e->getMessage());
        }

        return redirect()->route('editar_solicitudpago', $id)
            ->with(
                ! empty($resultado['ok']) ? 'mensaje' : 'mensaje_error',
                $resultado['mensaje'] ?? 'No se pudo reenviar el correo del árbol.'
            );
    }

    public function imprimirPdf($id)
    {
        if (! can('listar-solicitud-pago', false) && ! can('editar-solicitud-pago', false)) {
            return redirect()->route('inicio')->with('mensaje', 'No tiene permisos para emitir la solicitud de pago.');
        }
        $this->asegurarAccesoSolicitud((int) $id);

        try {
            $resultado = $this->comprobantePdfService->generar((int) $id);
        } catch (\Throwable $e) {
            return redirect()
                ->route('editar_solicitudpago', $id)
                ->with('mensaje_error', 'No se pudo emitir el comprobante: '.$e->getMessage());
        }

        return $resultado['pdf']->stream($resultado['nombre']);
    }

    /**
     * Descarga pública desde el mail del árbol: comprobante PDF + adjuntos (autorizada por hash).
     */
    public function descargarPaqueteMail($id, $hash)
    {
        $hash = is_string($hash) ? $hash : '';
        if (! $this->arbolIntegracionService->hashAutorizaDescargaPaquete((int) $id, $hash)) {
            return redirect()->route('inicio')
                ->with('mensaje', 'No tiene permisos para descargar la solicitud de pago o el enlace ya no es válido.');
        }

        try {
            $resultado = $this->paqueteMailPdfService->generar((int) $id);
        } catch (\Throwable $e) {
            report($e);

            return response(
                'No se pudo generar el PDF de la solicitud: '.$e->getMessage(),
                500,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        return response($resultado['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$resultado['nombre'].'"',
        ]);
    }

    public function descargarArchivo(Request $request, $id, $archivoId)
    {
        can('listar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);
        $sp = $this->repository->findOrFail($id);
        $arch = $sp->archivos->firstWhere('id', (int) $archivoId);
        if (! $arch) {
            abort(404);
        }

        $ruta = SolicitudpagoArchivoStorageSupport::rutaAbsoluta($arch, (int) $sp->codigo);
        if ($ruta === null) {
            abort(404, 'Archivo no encontrado en el repositorio de solicitudes de pago.');
        }

        $nombre = $arch->nombre_original ?: basename((string) $arch->archivo);
        if ($request->boolean('inline')) {
            return ArchivoAdjuntoCacheSupport::aplicarAntiCacheNavegador(response()->file($ruta, [
                'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            ]));
        }

        return response()->download($ruta, $nombre);
    }

    public function unirArchivos($id)
    {
        can('listar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);
        $sp = $this->repository->findOrFail($id);

        try {
            $resultado = $this->archivosFusionService->fusionar($sp);
        } catch (\Throwable $e) {
            return redirect()
                ->route('editar_solicitudpago', ['id' => $id, 'tab' => 'archivos'])
                ->with('mensaje_error', $e->getMessage());
        }

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$resultado['nombre'].'"',
        ];

        return response($resultado['contenido'], 200, $headers);
    }

    public function importarCuotas(Request $request, $id)
    {
        can('actualizar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);
        $request->validate([
            'archivo_cuotas' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $filas = \App\Support\Solicitudpago\SolicitudpagoCuotasImportColumnasSupport::leerFilas(
                $request->file('archivo_cuotas')
            );
            if ($filas === []) {
                return back()->with('mensaje', 'No se encontraron cuotas válidas en el Excel.');
            }

            $sp = $this->repository->findOrFail($id);
            $payload = $sp->toArray();
            $payload['nro_cuotas'] = array_column($filas, 'nro_cuota');
            $payload['fecha_vencimientos_cuota'] = array_column($filas, 'fecha_vencimiento');
            $payload['montos_cuota'] = array_column($filas, 'monto');
            $payload['solicitudpago_hija_ids'] = array_fill(0, count($filas), null);

            // Conservar cuentas existentes
            $payload['empresa_ids'] = $sp->cuentas->pluck('empresa_id')->all();
            $payload['cuentacontable_ids'] = $sp->cuentas->pluck('cuentacontable_id')->all();
            $payload['centrocosto_ids'] = $sp->cuentas->pluck('centrocosto_id')->all();
            $payload['debe_haberes'] = $sp->cuentas->pluck('debe_haber')->all();
            $payload['montos_cuenta'] = $sp->cuentas->pluck('monto')->all();
            $payload['archivo_ids_existentes'] = $sp->archivos->pluck('id')->all();

            $this->repository->update($payload, $id);

            return redirect()->route('editar_solicitudpago', $id)
                ->with('mensaje', 'Se importaron '.count($filas).' cuota(s) desde Excel.');
        } catch (\Throwable $e) {
            return back()->with('mensaje', $e->getMessage());
        }
    }

    public function marcarPagada($id)
    {
        can('actualizar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);
        $sp = $this->repository->findOrFail($id);
        if ($sp->estado !== SolicitudpagoEstados::AUTORIZADA) {
            return redirect()->route('editar_solicitudpago', $id)
                ->with('mensaje', 'Solo se puede marcar PAGADA una solicitud AUTORIZADA.');
        }
        $this->repository->cambiarEstado((int) $id, SolicitudpagoEstados::PAGADA, 'Pagada manual');

        return redirect()->route('editar_solicitudpago', $id)->with('mensaje', 'Solicitud marcada como pagada');
    }

    public function irAPago($id)
    {
        can('actualizar-solicitud-pago');
        can('crear-ingresos-egresos-caja');
        $this->asegurarAccesoSolicitud((int) $id);
        $sp = $this->repository->findOrFail($id);
        if ($sp->estado !== SolicitudpagoEstados::AUTORIZADA) {
            return redirect()->route('editar_solicitudpago', $id)
                ->with('mensaje', 'Solo se puede pagar una solicitud AUTORIZADA.');
        }

        return redirect()->route('crear_ingresoegreso', [
            'solicitudpago_id' => $sp->id,
            'empresa_id' => $sp->empresa_id,
            'proveedor_id' => $sp->proveedor_id,
            'detalle' => 'Pago SP '.$sp->codigo,
            'origen' => 'solicitudpago',
        ]);
    }

    public function anularPago(Request $request, $id)
    {
        can('anular-pago-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);

        $mov = $this->ingresoEgresoAnularRevertirService->movimientoPagoDeSolicitud((int) $id);
        if ($mov === null) {
            $msg = 'No hay orden de pago (IE) vinculada a esta solicitud.';
            if ($request->ajax()) {
                return response()->json(['mensaje' => $msg], 422);
            }

            return redirect()->back()->with('mensaje', $msg);
        }

        try {
            $resultado = $this->ingresoEgresoAnularRevertirService->anularFisicamente((int) $mov->id);
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ok', 'resultado' => $resultado]);
            }

            return redirect()->route('consultar_solicitudpago')->with('mensaje', 'Pago anulado físicamente. Solicitud vuelve a AUTORIZADA.');
        } catch (\Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('mensaje', $e->getMessage());
        }
    }

    public function revertirPago(Request $request, $id)
    {
        can('revertir-pago-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);

        $mov = $this->ingresoEgresoAnularRevertirService->movimientoPagoDeSolicitud((int) $id);
        if ($mov === null) {
            $msg = 'No hay orden de pago (IE) vinculada a esta solicitud.';
            if ($request->ajax()) {
                return response()->json(['mensaje' => $msg], 422);
            }

            return redirect()->back()->with('mensaje', $msg);
        }

        try {
            $resultado = $this->ingresoEgresoAnularRevertirService->revertir((int) $mov->id, $request->input('fecha'));
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ok', 'resultado' => $resultado]);
            }

            return redirect()->route('consultar_solicitudpago')->with(
                'mensaje',
                'Pago revertido. Anulación N° '.$resultado['numerotransaccion'].'. Solicitud AUTORIZADA.'
            );
        } catch (\Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('mensaje', $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function datosFormulario($data = null): array
    {
        $centrocostoCabecera = null;
        if ($data !== null && $data->centrocosto_id) {
            $centrocostoCabecera = $data->relationLoaded('centrocostos')
                ? $data->centrocostos
                : $data->centrocostos()->first();
        } elseif ($data === null) {
            $ccId = (int) (auth()->user()->centrocosto_id ?? 0);
            if ($ccId > 0) {
                $centrocostoCabecera = Centrocosto::query()->find($ccId);
            }
        }

        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'sector_query' => $this->sectorRepository->all(),
            'formapagosol_query' => $this->formapagosolRepository->all(),
            'moneda_query' => $this->monedaRepository->all(),
            'centrocosto_query' => $this->centrocostoRepository->all(),
            'centrocosto_cabecera' => $centrocostoCabecera,
            'estado_enum' => SolicitudpagoEstados::opciones(),
            'tratamiento_enum' => SolicitudpagoTratamientos::opciones(),
        ];
    }

    private function asegurarAccesoSolicitud(int $id): void
    {
        if (! SolicitudpagoVisibilidadSupport::solicitudAccesiblePorId($id)) {
            abort(403, 'No tiene acceso a esta solicitud de pago.');
        }
    }
}
