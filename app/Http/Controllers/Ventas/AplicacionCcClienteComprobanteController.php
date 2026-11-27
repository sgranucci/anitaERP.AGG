<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\AplicacionCcClienteComprobanteListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Cliente;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\AplicacionCcClienteComprobantePdfService;
use App\Services\Ventas\ClienteCuentacorrienteAplicacionService;
use App\Support\Ventas\AplicacionCcClienteComprobanteListadoColumnas;
use App\Support\Ventas\AplicacionCcClienteComprobanteListadoFiltros;
use App\Support\Ventas\AplicacionCcClienteComprobanteListadoPreferenciasUsuario;
use App\Support\Ventas\AplicacionCcClienteComprobanteListadoQuery;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoLienzoSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Listado\ListadoVisualSupport;
use App\Support\Listado\RecuerdaFiltrosListado;
use App\Support\Reportes\DompdfListadoSupport;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Index (workbench) de comprobantes de aplicación de cuenta corriente de clientes.
 * El matching (alta de aplicaciones) vive en ClienteCuentacorrienteAplicacionController (…/nueva).
 */
class AplicacionCcClienteComprobanteController extends Controller
{
    use RecuerdaFiltrosListado;

    private const SESSION_FILTROS = 'filtros_aplicacion_cc_cliente';

    private const RUTA_INDEX = 'aplicacion_cc_cliente';

    private const PERMISO = 'aplicar-cuentacorriente-cliente';

    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private ClienteCuentacorrienteAplicacionService $aplicacionService,
    ) {}

    public function index(Request $request)
    {
        can(self::PERMISO);

        $forzarEstandar = $request->boolean('vista_estandar') || $request->input('vista_modo') === 'estandar';
        $redir = $this->redirigirMemoriaOLimpiar($request, self::RUTA_INDEX, [], $forzarEstandar);
        if ($redir) {
            return $redir;
        }

        $recurso = AplicacionCcClienteComprobanteListadoColumnas::RECURSO;
        $campos = AplicacionCcClienteComprobanteListadoFiltros::camposOrdenables();
        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $vistas = ListadoVistaSupport::listarParaUsuario($recurso, $usuarioId);
        $vistaActiva = null;

        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario((int) $request->input('vista_id'), $recurso, $usuarioId);
        } elseif (
            ! $forzarEstandar
            && ! $request->has('filtro_valor')
            && ! $request->has('qbe')
            && ! $request->boolean('limpiar_filtros')
            && ! $request->boolean('filtro_limpiar')
            && ! $request->has('filtro_estado')
            && ! $request->has('empresa_id')
            && ! $request->has('empresa_todas')
            && ! $request->has('cliente_id')
        ) {
            $vistaActiva = ListadoVistaSupport::defaultDelUsuario($recurso, $usuarioId);
        }

        $filtrosRequest = ListadoVistaSupport::prepararQbeContraVista(
            AplicacionCcClienteComprobanteListadoFiltros::resolverDesdeRequest($request),
            $request
        );
        $filtros = $filtrosRequest;
        if ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros = AplicacionCcClienteComprobanteListadoFiltros::fusionarDesdeVista($filtros, $vistaActiva->filtros_json);
        }
        unset($filtros['_qbe_explicito']);
        $filtros = ListadoVisualSupport::aplicarPedido(
            $filtros,
            $request,
            ($vistaActiva && is_array($vistaActiva->filtros_json)) ? $vistaActiva->filtros_json : null,
            $campos
        );
        ListadoVistaSupport::recordarQbeSiEnvio($vistaActiva, $request, $filtros);
        if ($vistaActiva && ($request->exists('group') || $request->exists('sort'))) {
            if ($request->exists('group')) {
                $filtros['agrupar'] = $filtrosRequest['agrupar'] ?? [];
            }
            if ($request->exists('sort')) {
                $filtros['sort'] = $filtrosRequest['sort'] ?? [];
            }
            ListadoVistaSupport::recordarOrdenYAgrupar($vistaActiva, $filtros['sort'] ?? [], $filtros['agrupar'] ?? []);
        }
        if ($request->boolean('quitar_orden')) {
            $filtros['sort'] = [];
            if ($vistaActiva) {
                ListadoVistaSupport::recordarOrdenYAgrupar($vistaActiva, [], $filtros['agrupar'] ?? []);
            }
            $params = array_merge(
                AplicacionCcClienteComprobanteListadoFiltros::paraQueryString($filtros),
                ListadoVisualSupport::paraQueryString($filtros)
            );
            if ($vistaActiva) {
                $params['vista_id'] = $vistaActiva->id;
            } elseif ($forzarEstandar) {
                $params['vista_estandar'] = 1;
            }

            return redirect()->route(self::RUTA_INDEX, $params)->with('mensaje', 'Se quitó el orden de la grilla.');
        }

        $catalogo = AplicacionCcClienteComprobanteListadoColumnas::catalogoActivo();
        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas($recurso, $catalogo);
        if ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []) {
            $grillaLayout = AplicacionCcClienteComprobanteListadoPreferenciasUsuario::normalizarLayout($vistaActiva->columnas_json);
        } else {
            $grillaLayout = AplicacionCcClienteComprobanteListadoPreferenciasUsuario::grillaEstandar();
        }
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);

        $datas = AplicacionCcClienteComprobanteListadoQuery::lee($filtros, true);
        $resumen = AplicacionCcClienteComprobanteListadoQuery::resumen($filtros);

        $cortes = ['activo' => false];
        if (ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos) !== []) {
            $cortes = AplicacionCcClienteComprobanteListadoQuery::cortes($filtros);
        }

        $camposFiltro = AplicacionCcClienteComprobanteListadoFiltros::camposQbeDisponibles();
        foreach ($camposFiltro as $key => $meta) {
            $camposFiltro[$key]['label'] = $etiquetas[$key] ?? $etiquetasInstalacion[$key] ?? $meta['label'];
        }

        $filtrosQuery = array_merge(
            AplicacionCcClienteComprobanteListadoFiltros::paraQueryString($filtros),
            ListadoVisualSupport::paraQueryString($filtros)
        );
        $filtrosQuery['columnas'] = implode(',', $columnasVisibles);
        if ($vistaActiva) {
            $filtrosQuery['vista_id'] = $vistaActiva->id;
        } elseif ($forzarEstandar) {
            $filtrosQuery['vista_estandar'] = 1;
        }
        $filtrosQuery = $this->recordarFiltrosListadoSiCorresponde($request, $filtrosQuery);

        $clienteFiltro = ! empty($filtros['cliente_id'])
            ? Cliente::query()->find((int) $filtros['cliente_id'], ['id', 'codigo', 'nombre'])
            : null;

        $graficoSeries = ListadoVisualSupport::seriesDeConsulta(
            $filtros,
            $campos,
            $etiquetas,
            static fn (array $para): array => AplicacionCcClienteComprobanteListadoQuery::cortes($para)
        );

        return view('ventas.aplicacion_cc_comprobante.index', [
            'datas' => $datas,
            'resumen' => $resumen,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => $camposFiltro,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'clienteFiltro' => $clienteFiltro,
            'columnasVisibles' => $columnasVisibles,
            'grillaLayout' => $grillaLayout,
            'catalogoColumnas' => $catalogo,
            'etiquetasColumnas' => $etiquetas,
            'etiquetasInstalacion' => $etiquetasInstalacion,
            'vistasListado' => $vistas,
            'vistaActiva' => $vistaActiva,
            'workbenchListo' => ListadoVistaSupport::tablasDisponibles(),
            'cortes' => $cortes,
            'graficoSeries' => $graficoSeries,
            'graficoSerie' => $graficoSeries[0] ?? ['labels' => [], 'series' => [], 'tipo' => '', 'titulo' => ''],
            'puedeAnular' => can('desaplicar-cuentacorriente-cliente', false) || can(self::PERMISO, false),
        ]);
    }

    public function listar(Request $request, $formato = null)
    {
        can(self::PERMISO);
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = AplicacionCcClienteComprobanteListadoFiltros::resolverDesdeRequest($request);
        $columnasRequest = $request->input('columnas');
        if (is_string($columnasRequest)) {
            $columnasRequest = array_filter(array_map('trim', explode(',', $columnasRequest)));
        }
        $columnasVisibles = AplicacionCcClienteComprobanteListadoPreferenciasUsuario::resolverColumnas(
            is_array($columnasRequest) ? $columnasRequest : null
        );
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            AplicacionCcClienteComprobanteListadoColumnas::RECURSO,
            AplicacionCcClienteComprobanteListadoColumnas::catalogoActivo()
        );

        switch ($formato) {
            case 'PDF':
                $view = \View::make('ventas.aplicacion_cc_comprobante.listado', [
                    'datas' => AplicacionCcClienteComprobanteListadoQuery::lee($filtros, false),
                    'resumen' => AplicacionCcClienteComprobanteListadoQuery::resumen($filtros),
                    'columnasVisibles' => $columnasVisibles,
                    'etiquetasColumnas' => $etiquetas,
                    'filtros' => $filtros,
                ])->render();
                $rutaPdf = storage_path('pdf/listados/listado_aplicacion_cc_cliente.pdf');
                DompdfListadoSupport::guardarLegalLandscape($view, $rutaPdf, [
                    'titulo_corto' => 'Comprobantes de aplicación CC clientes',
                    'dompdf' => [
                        'isFontSubsettingEnabled' => false,
                        'isJavascriptEnabled' => false,
                    ],
                ]);

                return response()->download($rutaPdf);

            case 'EXCEL':
                return app(AplicacionCcClienteComprobanteListadoExport::class)
                    ->parametros($filtros, $columnasVisibles, $etiquetas)
                    ->download('aplicaciones_cc_clientes.xlsx');

            case 'CSV':
                return app(AplicacionCcClienteComprobanteListadoExport::class)
                    ->parametros($filtros, $columnasVisibles, $etiquetas)
                    ->download('aplicaciones_cc_clientes.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(self::RUTA_INDEX, AplicacionCcClienteComprobanteListadoFiltros::paraQueryString($filtros));
    }

    public function previewWorkbench(Request $request)
    {
        can(self::PERMISO);

        $campos = AplicacionCcClienteComprobanteListadoFiltros::camposOrdenables();
        $filtros = AplicacionCcClienteComprobanteListadoFiltros::resolverDesdeRequest($request);
        $filtros['_per_page'] = ListadoDisenadorPreviewSupport::LIMITE_MUESTRA;
        $layout = AplicacionCcClienteComprobanteListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
        $page = AplicacionCcClienteComprobanteListadoQuery::lee($filtros, true);
        $orden = ListadoOrdenamientoSupport::normalizar($request->input('sort', $filtros['sort'] ?? []), $campos);
        $agrupar = ListadoAgrupacionSupport::normalizar($request->input('group', $filtros['agrupar'] ?? []), $campos);
        $filtrosCortes = $filtros;
        $filtrosCortes['agrupar'] = $agrupar;

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            $agrupar,
            $page->getCollection(),
            static fn (object $row, string $key): string => AplicacionCcClienteComprobanteListadoColumnas::valorCelda($row, $key),
            (int) $page->total(),
            $etiquetas,
            $agrupar !== [] ? AplicacionCcClienteComprobanteListadoQuery::cortes($filtrosCortes) : ['activo' => false]
        ));
    }

    public function guardarVistaListado(Request $request)
    {
        can(self::PERMISO);

        $filtros = AplicacionCcClienteComprobanteListadoFiltros::resolverDesdeRequest($request);
        $layout = AplicacionCcClienteComprobanteListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $orden = $filtros['sort'] ?? [];
        $graficosVista = ListadoLienzoSupport::normalizar(
            $request->input('graficos'),
            $request->input('grafico'),
            $request->exists('graficos'),
            AplicacionCcClienteComprobanteListadoFiltros::camposOrdenables()
        );
        $vista = ListadoVistaSupport::guardar(
            AplicacionCcClienteComprobanteListadoColumnas::RECURSO,
            (int) auth()->id(),
            (string) $request->input('nombre', ''),
            [
                'modo' => $filtros['modo'],
                'qbe' => $filtros['qbe'] ?? [],
                'sort' => $orden,
                'orden' => $orden,
                'agrupar' => $filtros['agrupar'] ?? [],
                'grafico' => ListadoLienzoSupport::primero($graficosVista),
                'graficos' => $graficosVista,
            ],
            $layout,
            $request->boolean('es_default'),
            $request->boolean('compartida'),
            $request->filled('vista_id') ? (int) $request->input('vista_id') : null
        );
        if (! $vista) {
            return redirect()->route(self::RUTA_INDEX, AplicacionCcClienteComprobanteListadoFiltros::paraQueryString($filtros))
                ->with('error', 'No se pudo guardar la vista.');
        }
        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));
        $qs = AplicacionCcClienteComprobanteListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', ListadoGrillaConfigSupport::keysVisibles($layout));
        $qs['vista_id'] = $vista->id;

        return redirect()->route(self::RUTA_INDEX, $qs)->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public function eliminarVistaListado(int $id)
    {
        can(self::PERMISO);
        $ok = ListadoVistaSupport::eliminar($id, AplicacionCcClienteComprobanteListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route(self::RUTA_INDEX, ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        can(self::PERMISO);
        $layout = AplicacionCcClienteComprobanteListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;
        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            $vista = ListadoVistaSupport::findParaUsuario(
                $vistaId,
                AplicacionCcClienteComprobanteListadoColumnas::RECURSO,
                (int) auth()->id()
            );
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            AplicacionCcClienteComprobanteListadoPreferenciasUsuario::persistirGrillaEstandar($layout);
        }
        $qs = AplicacionCcClienteComprobanteListadoFiltros::paraQueryString(
            AplicacionCcClienteComprobanteListadoFiltros::resolverDesdeRequest($request)
        );
        $qs['columnas'] = implode(',', ListadoGrillaConfigSupport::keysVisibles($layout));
        $qs[$vistaId > 0 ? 'vista_id' : 'vista_estandar'] = $vistaId > 0 ? $vistaId : 1;

        return redirect()->route(self::RUTA_INDEX, $qs)->with('mensaje', 'Grilla actualizada.');
    }

    public function guardarEtiquetasListado(Request $request)
    {
        can(self::PERMISO);
        $etiquetas = $request->input('etiquetas', []);
        ListadoColumnaEtiquetaSupport::guardar(
            AplicacionCcClienteComprobanteListadoColumnas::RECURSO,
            is_array($etiquetas) ? $etiquetas : [],
            array_keys(AplicacionCcClienteComprobanteListadoColumnas::catalogoActivo())
        );

        return redirect()->route(
            self::RUTA_INDEX,
            AplicacionCcClienteComprobanteListadoFiltros::paraQueryString(
                AplicacionCcClienteComprobanteListadoFiltros::resolverDesdeRequest($request)
            )
        )->with('mensaje', 'Etiquetas actualizadas.');
    }

    public function imprimir(int $id)
    {
        can(self::PERMISO);

        return app(AplicacionCcClienteComprobantePdfService::class)->generarRespuesta($id);
    }

    public function anular(Request $request, int $id)
    {
        if (! can('desaplicar-cuentacorriente-cliente', false) && ! can(self::PERMISO, false)) {
            abort(403);
        }

        $volver = AplicacionCcClienteComprobanteListadoFiltros::paraQueryString(
            AplicacionCcClienteComprobanteListadoFiltros::resolverDesdeRequest($request)
        );

        try {
            $comprobante = $this->aplicacionService->anularComprobante($id);
        } catch (RuntimeException $e) {
            return redirect()->route(self::RUTA_INDEX, $volver)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->route(self::RUTA_INDEX, $volver)->with('error', 'No se pudo anular: '.$e->getMessage());
        }

        return redirect()->route(self::RUTA_INDEX, $volver)->with(
            'mensaje',
            'Comprobante '.$comprobante->numeroFormateado().' anulado: se revirtieron sus aplicaciones.'
        );
    }
}
