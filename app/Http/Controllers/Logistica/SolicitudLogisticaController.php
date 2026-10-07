<?php

namespace App\Http\Controllers\Logistica;

use App\Http\Controllers\Controller;
use App\Models\Compras\Ordencompra;
use App\Models\Contable\Centrocosto;
use App\Models\Logistica\LogisticaTrabajoTipo;
use App\Models\Logistica\LogisticaUbicacion;
use App\Models\Logistica\SolicitudLogistica;
use App\Models\Logistica\SolicitudLogisticaArchivo;
use App\Models\Seguridad\Usuario;
use App\Exports\Logistica\SolicitudLogisticaListadoExport;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Logistica\ArticuloCatalogoLogisticaSupport;
use App\Support\Logistica\LogisticaAvisoSupport;
use App\Support\Logistica\LogisticaCatalogoPortalSupport;
use App\Support\Logistica\LogisticaCumplimientoSupport;
use App\Support\Logistica\LogisticaTrabajoSupport;
use App\Support\Logistica\LogisticaUidSupport;
use App\Support\Logistica\SolicitudLogisticaListadoColumnas;
use App\Support\Logistica\SolicitudLogisticaListadoFiltros;
use App\Support\Logistica\SolicitudLogisticaListadoPreferenciasUsuario;
use App\Support\Logistica\SolicitudLogisticaListadoQuery;
use App\Support\Reportes\DompdfListadoSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SolicitudLogisticaController extends Controller
{
    public function index(Request $request)
    {
        $this->puedeEntrar();

        $armado = $this->armarListado($request);

        return view('logistica.solicitud.index', $armado + [
            'puedeCrear' => can('crear-logistica-solicitud', false),
            'puedeListarTodas' => $this->puedeVerTodas(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        $this->puedeEntrar();
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $columnasVisibles = $this->columnasDesdeRequest($request);
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            SolicitudLogisticaListadoColumnas::RECURSO,
            SolicitudLogisticaListadoColumnas::catalogoActivo()
        );
        $usuarioId = (int) auth()->id();
        $puedeTodas = $this->puedeVerTodas();

        switch ($formato) {
            case 'PDF':
                $datas = SolicitudLogisticaListadoQuery::filtrada($filtros, $usuarioId, $puedeTodas)->get();
                $view = \View::make('logistica.solicitud.listado', [
                    'datas' => $datas,
                    'columnasVisibles' => $columnasVisibles,
                    'etiquetasColumnas' => $etiquetas,
                    'filtros' => $filtros,
                ])->render();
                $rutaPdf = storage_path('pdf/listados/listado_logistica_solicitud.pdf');
                DompdfListadoSupport::guardarLegalLandscape($view, $rutaPdf, [
                    'titulo_corto' => 'Solicitudes de logística',
                    'dompdf' => [
                        'isFontSubsettingEnabled' => false,
                        'isJavascriptEnabled' => false,
                    ],
                ]);

                return response()->download($rutaPdf);

            case 'EXCEL':
                return app(SolicitudLogisticaListadoExport::class)
                    ->parametros($filtros, $columnasVisibles, $etiquetas, $usuarioId, $puedeTodas)
                    ->download('solicitudes_logistica.xlsx');

            case 'CSV':
                return app(SolicitudLogisticaListadoExport::class)
                    ->parametros($filtros, $columnasVisibles, $etiquetas, $usuarioId, $puedeTodas)
                    ->download('solicitudes_logistica.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('logistica_solicitud', SolicitudLogisticaListadoFiltros::paraQueryString($filtros));
    }

    public function previewWorkbench(Request $request)
    {
        $this->puedeEntrar();

        $filtros = $this->resolverFiltrosListado($request);
        $filtros['_per_page'] = ListadoDisenadorPreviewSupport::LIMITE_MUESTRA;
        $layout = SolicitudLogisticaListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
        $page = SolicitudLogisticaListadoQuery::filtrada($filtros, (int) auth()->id(), $this->puedeVerTodas())
            ->paginate(ListadoDisenadorPreviewSupport::LIMITE_MUESTRA);
        $total = method_exists($page, 'total') ? (int) $page->total() : $page->count();
        $filas = method_exists($page, 'getCollection') ? $page->getCollection() : $page;
        $orden = ListadoOrdenamientoSupport::normalizar(
            $request->input('sort', $filtros['sort'] ?? []),
            SolicitudLogisticaListadoFiltros::camposOrdenables()
        );
        $agrupar = ListadoAgrupacionSupport::normalizar(
            $request->input('group', $filtros['agrupar'] ?? []),
            SolicitudLogisticaListadoFiltros::camposOrdenables()
        );
        $filtrosCortes = $filtros;
        $filtrosCortes['agrupar'] = $agrupar;
        $cortes = $agrupar !== []
            ? SolicitudLogisticaListadoQuery::cortes($filtrosCortes, (int) auth()->id(), $this->puedeVerTodas())
            : ['activo' => false];

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            $agrupar,
            $filas,
            static fn (object $row, string $key): string => SolicitudLogisticaListadoColumnas::valorCelda($row, $key),
            $total,
            $etiquetas,
            $cortes
        ));
    }

    public function guardarVistaListado(Request $request)
    {
        $this->puedeEntrar();

        $filtros = $this->resolverFiltrosListado($request);
        $layout = SolicitudLogisticaListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $orden = $filtros['sort'] ?? [];
        $vista = ListadoVistaSupport::guardar(
            SolicitudLogisticaListadoColumnas::RECURSO,
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
            return redirect()->route('logistica_solicitud', SolicitudLogisticaListadoFiltros::paraQueryString($filtros))
                ->with('error', 'No se pudo guardar la vista.');
        }
        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));
        $qs = SolicitudLogisticaListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs['vista_id'] = $vista->id;

        return redirect()->route('logistica_solicitud', $qs)
            ->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public function eliminarVistaListado(int $id)
    {
        $this->puedeEntrar();
        $ok = ListadoVistaSupport::eliminar($id, SolicitudLogisticaListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('logistica_solicitud', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        $this->puedeEntrar();
        $layout = SolicitudLogisticaListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;
        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            $vista = ListadoVistaSupport::findParaUsuario($vistaId, SolicitudLogisticaListadoColumnas::RECURSO, (int) auth()->id());
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            SolicitudLogisticaListadoPreferenciasUsuario::persistirGrillaEstandar($layout);
        }
        $filtros = $this->resolverFiltrosListado($request);
        $qs = SolicitudLogisticaListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs[$vistaId > 0 ? 'vista_id' : 'vista_estandar'] = $vistaId > 0 ? $vistaId : 1;

        return redirect()->route('logistica_solicitud', $qs)->with('mensaje', 'Grilla actualizada.');
    }

    public function guardarEtiquetasListado(Request $request)
    {
        $this->puedeEntrar();
        $etiquetas = $request->input('etiquetas', []);
        if (! is_array($etiquetas)) {
            $etiquetas = [];
        }
        ListadoColumnaEtiquetaSupport::guardar(
            SolicitudLogisticaListadoColumnas::RECURSO,
            $etiquetas,
            array_keys(SolicitudLogisticaListadoColumnas::catalogoActivo())
        );

        return redirect()->route(
            'logistica_solicitud',
            SolicitudLogisticaListadoFiltros::paraQueryString($this->resolverFiltrosListado($request))
        )->with('mensaje', 'Etiquetas actualizadas.');
    }

    public function crear()
    {
        can('crear-logistica-solicitud');
        if (! ArticuloCatalogoLogisticaSupport::uiActiva()) {
            abort(404);
        }

        $usuario = Usuario::query()->with('centrocostos:id,codigo,nombre')->find((int) auth()->id());
        $centrocosto = $usuario?->centrocostos;
        $catalogo = LogisticaCatalogoPortalSupport::catalogoParaUsuario((int) auth()->id());
        $puedeTrabajos = collect($catalogo['tipos'])->contains(fn (array $tipo) => $tipo['codigo'] === 'trabajos');
        $trabajos = $puedeTrabajos
            ? LogisticaTrabajoTipo::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get()
            : collect();
        $ubicaciones = $puedeTrabajos
            ? LogisticaUbicacion::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get()
            : collect();
        $empresas = app(EmpresaRepository::class)->allFiltrado();

        return view('logistica.solicitud.crear', [
            'catalogo' => $catalogo,
            'trabajos' => $trabajos,
            'ubicaciones' => $ubicaciones,
            'empresas' => $empresas->map(fn ($empresa) => [
                'id' => (int) $empresa->id,
                'nombre' => (string) $empresa->nombre,
            ])->values(),
            'centrocostoId' => (int) ($centrocosto->id ?? 0),
            'centrocostoCodigo' => (string) ($centrocosto->codigo ?? ''),
            'centrocostoNombre' => (string) ($centrocosto->nombre ?? ''),
        ]);
    }

    public function guardar(Request $request)
    {
        can('crear-logistica-solicitud');
        if (! ArticuloCatalogoLogisticaSupport::uiActiva()) {
            abort(404);
        }

        $centrocostoId = (int) $request->input('centrocosto_id', 0);
        if ($centrocostoId <= 0 || ! Centrocosto::query()->whereKey($centrocostoId)->exists()) {
            return redirect()->route('crear_logistica_solicitud')->with('mensaje-error', 'Elegí un centro de costo.');
        }

        try {
            if (trim((string) $request->input('trabajo_codigo', '')) !== '') {
                $empresas = app(EmpresaRepository::class)->allFiltrado()->pluck('id')->map(fn ($id) => (int) $id)->all();
                $solicitud = LogisticaTrabajoSupport::crear((int) auth()->id(), $centrocostoId, $request, $empresas);
            } else {
                $lineas = [];
                $ids = (array) $request->input('item_articulo_id', []);
                $cantidades = (array) $request->input('item_cantidad', []);
                foreach ($ids as $i => $articuloId) {
                    $lineas[] = [
                        'articulo_id' => (int) $articuloId,
                        'cantidad' => (float) ($cantidades[$i] ?? 0),
                    ];
                }
                $solicitud = LogisticaCatalogoPortalSupport::crearSolicitudInsumos(
                    (int) auth()->id(),
                    $centrocostoId,
                    (string) $request->input('prioridad', 'Normal'),
                    $lineas,
                );
            }
        } catch (\Throwable $e) {
            return redirect()
                ->route('crear_logistica_solicitud')
                ->with('mensaje-error', $e->getMessage());
        }

        LogisticaAvisoSupport::alta($solicitud);
        $aviso = 'Solicitud enviada: '.$solicitud->numeroVisible();
        if ($solicitud->estado === 'pendiente_aprobacion') {
            $aviso .= '. '.($solicitud->observacion ?: 'Queda pendiente de aprobación.');
        }

        return redirect()
            ->route('ver_logistica_solicitud', $solicitud->id)
            ->with('mensaje', $aviso);
    }

    public function ver(int $id)
    {
        $this->puedeEntrar();
        $solicitud = $this->cargar($id);
        $this->asegurarVisible($solicitud);

        return view('logistica.solicitud.ver', [
            'solicitud' => $solicitud,
            'puedeGestionar' => can('gestionar-logistica-solicitud', false),
            'historialUid' => LogisticaUidSupport::historial((string) $solicitud->uid_bien),
        ]);
    }

    public function gestionar(Request $request, int $id)
    {
        can('gestionar-logistica-solicitud');
        if (! ArticuloCatalogoLogisticaSupport::uiActiva()) {
            abort(404);
        }
        $solicitud = $this->cargar($id);

        try {
            $lineas = [];
            foreach ((array) $request->input('entregar_item', []) as $itemId => $cantidad) {
                $lineas[(int) $itemId] = (float) str_replace(',', '.', (string) $cantidad);
            }
            LogisticaCumplimientoSupport::aplicar(
                $solicitud,
                (string) $request->input('accion', ''),
                [
                    'deposito_id' => (int) $request->input('deposito_id', 0),
                    'deposito_destino_id' => (int) $request->input('deposito_destino_id', 0),
                    'modo' => (string) $request->input('modo_cumplimiento', ''),
                    'numero_comprobante' => (string) $request->input('numero_comprobante', ''),
                    'receptor_nombre' => (string) $request->input('receptor_nombre', ''),
                    'lineas' => $lineas,
                    'archivo' => $request->file('constancia'),
                ],
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('ver_logistica_solicitud', $solicitud->id)
                ->with('mensaje-error', $e->getMessage());
        }

        if ((string) $request->input('accion') !== 'vincular') {
            LogisticaAvisoSupport::cambio($solicitud->fresh());
        }

        return redirect()
            ->route('ver_logistica_solicitud', $solicitud->id)
            ->with('mensaje', 'Solicitud actualizada: '.$solicitud->fresh()->etiquetaEstado().'.');
    }

    public function descargarArchivo(int $id, int $archivoId)
    {
        $this->puedeEntrar();
        $solicitud = $this->cargar($id);
        $this->asegurarVisible($solicitud);
        $archivo = SolicitudLogisticaArchivo::query()
            ->where('solicitud_logistica_id', $solicitud->id)
            ->whereKey($archivoId)
            ->firstOrFail();
        if (! Storage::disk('local')->exists($archivo->ruta)) {
            abort(404);
        }

        return Storage::disk('local')->download($archivo->ruta, $archivo->nombre);
    }

    public function resolverOrdencompra(Request $request)
    {
        can('crear-logistica-solicitud');
        $numero = trim((string) $request->input('numero', ''));
        if ($numero === '' || ! ctype_digit($numero)) {
            return response()->json(['ok' => false, 'mensaje' => 'Indicá el número de orden de compra.']);
        }
        $orden = Ordencompra::query()
            ->with('proveedores:id,nombre,domicilio')
            ->where('numeroordencompra', (int) $numero)
            ->orderByDesc('id')
            ->first();
        if ($orden === null) {
            return response()->json(['ok' => false, 'mensaje' => 'No existe la orden de compra '.$numero.'.']);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $orden->id,
            'numero' => (string) $orden->numeroordencompra,
            'empresa_id' => (int) $orden->empresa_id,
            'proveedor' => (string) ($orden->proveedores->nombre ?? ''),
            'domicilio' => (string) ($orden->proveedores->domicilio ?? ''),
        ]);
    }

    private function cargar(int $id): SolicitudLogistica
    {
        return SolicitudLogistica::query()
            ->with([
                'usuario:id,nombre',
                'centrocosto:id,codigo,nombre',
                'tipo:id,nombre,codigo',
                'trabajoTipo:id,nombre,codigo,responsable,prioridad_piso',
                'ubicacionOrigen:id,nombre',
                'ubicacionDestino:id,nombre',
                'empresa:id,nombre',
                'ordencompra:id,numeroordencompra,proveedor_id',
                'ordencompra.proveedores:id,nombre,domicilio',
                'deposito:id,codigo,nombre',
                'depositoDestino:id,codigo,nombre',
                'movimientoStock:id,codigo,fecha',
                'transferencia:id,codigo,fecha',
                'requisicion:id,numerorequisicion,fecha',
                'items.articulo:id,sku,descripcion',
                'archivos',
            ])
            ->findOrFail($id);
    }

    private function asegurarVisible(SolicitudLogistica $solicitud): void
    {
        if ((int) $solicitud->usuario_id === (int) auth()->id()) {
            return;
        }
        if (can('listar-logistica-solicitud', false) || can('gestionar-logistica-solicitud', false)) {
            return;
        }
        abort(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function armarListado(Request $request): array
    {
        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $vistas = ListadoVistaSupport::listarParaUsuario(SolicitudLogisticaListadoColumnas::RECURSO, $usuarioId);
        $vistaActiva = null;
        $forzarEstandar = $request->boolean('vista_estandar')
            || $request->input('vista_modo') === 'estandar';

        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                SolicitudLogisticaListadoColumnas::RECURSO,
                $usuarioId
            );
        } elseif (
            ! $forzarEstandar
            && ! $request->has('filtro_valor')
            && ! $request->has('qbe')
            && ! $request->boolean('limpiar_filtros')
            && ! $request->boolean('filtro_limpiar')
            && ! $request->has('filtro_estado')
            && ! $request->has('filtro_alcance')
            && ! $request->has('filtro_plazo')
        ) {
            $vistaActiva = ListadoVistaSupport::defaultDelUsuario(SolicitudLogisticaListadoColumnas::RECURSO, $usuarioId);
        }

        $filtrosRequest = ListadoVistaSupport::prepararQbeContraVista(
            $this->resolverFiltrosListado($request),
            $request
        );
        $filtros = $filtrosRequest;
        if ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros = SolicitudLogisticaListadoFiltros::fusionarDesdeVista($filtros, $vistaActiva->filtros_json);
        }
        unset($filtros['_qbe_explicito']);
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

        $catalogo = SolicitudLogisticaListadoColumnas::catalogoActivo();
        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            SolicitudLogisticaListadoColumnas::RECURSO,
            $catalogo
        );
        if ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []) {
            $grillaLayout = SolicitudLogisticaListadoPreferenciasUsuario::normalizarLayout($vistaActiva->columnas_json);
        } else {
            $grillaLayout = SolicitudLogisticaListadoPreferenciasUsuario::grillaEstandar();
        }
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);

        $uid = (int) auth()->id();
        $puedeTodas = $this->puedeVerTodas();
        $datas = SolicitudLogisticaListadoQuery::filtrada($filtros, $uid, $puedeTodas)->paginate(15);

        $cortes = ['activo' => false];
        if (ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], SolicitudLogisticaListadoFiltros::camposOrdenables()) !== []) {
            $cortes = SolicitudLogisticaListadoQuery::cortes($filtros, $uid, $puedeTodas);
        }

        $camposFiltro = SolicitudLogisticaListadoFiltros::camposQbeDisponibles();
        foreach ($camposFiltro as $key => $meta) {
            $camposFiltro[$key]['label'] = $etiquetas[$key] ?? $etiquetasInstalacion[$key] ?? $meta['label'];
        }

        $filtrosQuery = SolicitudLogisticaListadoFiltros::paraQueryString($filtros);
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
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        return SolicitudLogisticaListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
    }

    /**
     * @return list<string>
     */
    private function columnasDesdeRequest(Request $request): array
    {
        $columnasRequest = $request->input('columnas');
        if (is_string($columnasRequest)) {
            $columnasRequest = array_filter(array_map('trim', explode(',', $columnasRequest)));
        }

        return SolicitudLogisticaListadoPreferenciasUsuario::resolverColumnas(
            is_array($columnasRequest) ? $columnasRequest : null
        );
    }

    private function puedeVerTodas(): bool
    {
        return can('listar-logistica-solicitud', false) || can('gestionar-logistica-solicitud', false);
    }

    private function puedeEntrar(): void
    {
        if (! ArticuloCatalogoLogisticaSupport::uiActiva()) {
            abort(404);
        }
        if (can('listar-logistica-solicitud', false)
            || can('crear-logistica-solicitud', false)
            || can('gestionar-logistica-solicitud', false)
        ) {
            return;
        }
        can('listar-logistica-solicitud');
    }
}
