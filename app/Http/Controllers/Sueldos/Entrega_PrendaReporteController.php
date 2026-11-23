<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\EntregaPrendaReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Sueldos\Agrupamiento_Sueldos;
use App\Models\Sueldos\Entrega_Prenda_Sueldos;
use App\Models\Sueldos\Prenda_Articulo_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Sueldos\EntregaPrendaService;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoLienzoSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Listado\ListadoVisualSupport;
use App\Support\Configuracion\EmpresaFiltroExternoSupport;
use App\Support\Sueldos\EntregaPrendaListadoColumnas;
use App\Support\Sueldos\EntregaPrendaListadoFiltros;
use App\Support\Sueldos\EntregaPrendaListadoPreferenciasUsuario;
use App\Support\Sueldos\EntregaPrendaReporteConsulta;
use Illuminate\Http\Request;

class Entrega_PrendaReporteController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private EntregaPrendaService $servicio,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-entrega-prenda');

        [$filtros, $vistaActiva, $forzarEstandar] = $this->filtrosDePantalla($request);
        if ($request->boolean('quitar_orden')) {
            $params = EntregaPrendaListadoFiltros::paraQueryString($filtros, true);
            if ($vistaActiva) {
                $params['vista_id'] = $vistaActiva->id;
            } elseif ($forzarEstandar) {
                $params['vista_estandar'] = 1;
            }

            return redirect()
                ->route('entrega_prenda_reporte', $params)
                ->with('mensaje', 'Se volvió al orden estándar.');
        }
        $filtrosConsulta = $filtros;
        $filtrosConsulta['agrupar'] = EntregaPrendaListadoFiltros::agruparEfectivo($filtros);

        $catalogo = EntregaPrendaListadoColumnas::catalogoActivo();
        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            EntregaPrendaListadoColumnas::RECURSO,
            $catalogo
        );
        if ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []) {
            $grillaLayout = EntregaPrendaListadoPreferenciasUsuario::normalizarLayout($vistaActiva->columnas_json);
        } else {
            $grillaLayout = EntregaPrendaListadoPreferenciasUsuario::grillaEstandar();
        }
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);

        $datas = EntregaPrendaReporteConsulta::lee($filtrosConsulta, true);
        $cortes = ['activo' => false];
        if ($filtrosConsulta['agrupar'] !== []) {
            $cortes = EntregaPrendaReporteConsulta::cortes($filtrosConsulta);
        }

        $paraPrenda = $filtros;
        $paraPrenda['agrupar'] = ['prenda'];
        $graficoPorPrenda = $this->graficoDesdeCortes(EntregaPrendaReporteConsulta::cortes($paraPrenda));

        $camposFiltro = EntregaPrendaListadoFiltros::camposQbeDisponibles();
        foreach ($camposFiltro as $key => $meta) {
            $camposFiltro[$key]['label'] = $etiquetas[$key] ?? $etiquetasInstalacion[$key] ?? $meta['label'];
        }

        $filtrosQuery = $this->queryDeFiltros($filtros, $columnasVisibles, $vistaActiva, $forzarEstandar, $request);

        return view('sueldos.entrega_prenda.index', [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'agrupamientos' => Agrupamiento_Sueldos::query()->orderBy('descripcion')->get(['id', 'descripcion']),
            'filtros' => $filtros,
            'filtrosConsulta' => $filtrosConsulta,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => true,
            'datas' => $datas,
            'totalCantidad' => EntregaPrendaReporteConsulta::totalCantidad($filtrosConsulta),
            'camposFiltro' => $camposFiltro,
            'columnasVisibles' => $columnasVisibles,
            'grillaLayout' => $grillaLayout,
            'catalogoColumnas' => $catalogo,
            'etiquetasColumnas' => $etiquetas,
            'etiquetasInstalacion' => $etiquetasInstalacion,
            'vistasListado' => ListadoVistaSupport::listarParaUsuario(
                EntregaPrendaListadoColumnas::RECURSO,
                auth()->id() ? (int) auth()->id() : null
            ),
            'vistaActiva' => $vistaActiva,
            'workbenchListo' => ListadoVistaSupport::tablasDisponibles(),
            'cortes' => $cortes,
            'graficoPorPrenda' => $graficoPorPrenda,
            'graficoSeries' => ($graficoSeries = ListadoVisualSupport::seriesDeConsulta(
                $filtros,
                EntregaPrendaListadoFiltros::camposOrdenables(),
                $etiquetas,
                fn (array $para): array => EntregaPrendaReporteConsulta::cortes($para)
            )),
            'graficoSerie' => $graficoSeries[0] ?? ['labels' => [], 'series' => [], 'tipo' => '', 'titulo' => ''],
        ]);
    }

    public function exportar(Request $request, $formato = null)
    {
        can('listar-entrega-prenda');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        [$filtros] = $this->filtrosDePantalla($request);
        $filtros['agrupar'] = EntregaPrendaListadoFiltros::agruparEfectivo($filtros);
        $columnasRequest = $request->input('columnas');
        if (is_string($columnasRequest)) {
            $columnasRequest = array_filter(array_map('trim', explode(',', $columnasRequest)));
        }
        $columnasVisibles = EntregaPrendaListadoPreferenciasUsuario::resolverColumnas(
            is_array($columnasRequest) ? array_values($columnasRequest) : null
        );
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            EntregaPrendaListadoColumnas::RECURSO,
            EntregaPrendaListadoColumnas::catalogoActivo()
        );

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $datas = EntregaPrendaReporteConsulta::lee($filtros, false);
                $view = \View::make('sueldos.entrega_prenda.listado', [
                    'datas' => $datas,
                    'subtitulo' => EntregaPrendaListadoFiltros::subtitulo($filtros),
                    'columnasVisibles' => $columnasVisibles,
                    'etiquetasColumnas' => $etiquetas,
                    'totalCantidad' => (float) $datas->sum('cantidad'),
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre = 'listado_entrega_prenda';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre.'.pdf');

                return response()->download($path.'/'.$nombre.'.pdf');

            case 'EXCEL':
                return app(EntregaPrendaReporteExport::class)
                    ->parametros($filtros, $columnasVisibles, $etiquetas)
                    ->download('entregas_indumentaria.xlsx');

            case 'CSV':
                return app(EntregaPrendaReporteExport::class)
                    ->parametros($filtros, $columnasVisibles, $etiquetas)
                    ->download('entregas_indumentaria.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('entrega_prenda_reporte', EntregaPrendaListadoFiltros::paraQueryString($filtros, true));
    }

    public function previewWorkbench(Request $request)
    {
        can('listar-entrega-prenda');

        [$filtros] = $this->filtrosDePantalla($request);
        $filtros['_per_page'] = ListadoDisenadorPreviewSupport::LIMITE_MUESTRA;
        $filtros['agrupar'] = ListadoAgrupacionSupport::normalizar(
            $request->input('group', $filtros['agrupar'] ?? []),
            EntregaPrendaListadoFiltros::camposOrdenables()
        );
        $layout = EntregaPrendaListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
        $page = EntregaPrendaReporteConsulta::lee($filtros, true);
        $total = method_exists($page, 'total') ? (int) $page->total() : $page->count();
        $filas = method_exists($page, 'getCollection') ? $page->getCollection() : $page;
        $orden = ListadoOrdenamientoSupport::normalizar(
            $request->input('sort', $filtros['sort'] ?? []),
            EntregaPrendaListadoFiltros::camposOrdenables()
        );
        $cortes = $filtros['agrupar'] !== [] ? EntregaPrendaReporteConsulta::cortes($filtros) : ['activo' => false];

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            $filtros['agrupar'],
            $filas,
            static fn (object $row, string $key): string => EntregaPrendaListadoColumnas::valorCelda($row, $key),
            $total,
            $etiquetas,
            $cortes
        ));
    }

    public function guardarVistaListado(Request $request)
    {
        can('listar-entrega-prenda');

        [$filtros] = $this->filtrosDePantalla($request);
        $layout = EntregaPrendaListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $orden = $filtros['sort'] ?? [];
        $vista = ListadoVistaSupport::guardar(
            EntregaPrendaListadoColumnas::RECURSO,
            (int) auth()->id(),
            (string) $request->input('nombre', ''),
            [
                'modo' => $filtros['modo'],
                'qbe' => $filtros['qbe'] ?? [],
                'sort' => $orden,
                'orden' => $orden,
                'agrupar' => $filtros['agrupar'] ?? [],
                'grafico' => ListadoLienzoSupport::primero($graficosVista = ListadoLienzoSupport::normalizar(
                    $request->input('graficos'),
                    $request->input('grafico'),
                    $request->exists('graficos'),
                    EntregaPrendaListadoFiltros::camposOrdenables()
                )),
                'graficos' => $graficosVista,
            ],
            $layout,
            $request->boolean('es_default'),
            $request->boolean('compartida'),
            $request->filled('vista_id') ? (int) $request->input('vista_id') : null
        );
        if (! $vista) {
            return redirect()->route('entrega_prenda_reporte', EntregaPrendaListadoFiltros::paraQueryString($filtros, true))
                ->with('error', 'No se pudo guardar la vista.');
        }
        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));
        $qs = EntregaPrendaListadoFiltros::paraQueryString($filtros, true);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs['vista_id'] = $vista->id;

        return redirect()->route('entrega_prenda_reporte', $qs)
            ->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public function eliminarVistaListado(int $id)
    {
        can('listar-entrega-prenda');
        $ok = ListadoVistaSupport::eliminar($id, EntregaPrendaListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('entrega_prenda_reporte', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        can('listar-entrega-prenda');
        $layout = EntregaPrendaListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;
        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            $vista = ListadoVistaSupport::findParaUsuario($vistaId, EntregaPrendaListadoColumnas::RECURSO, (int) auth()->id());
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            EntregaPrendaListadoPreferenciasUsuario::persistirGrillaEstandar($layout);
        }
        [$filtros] = $this->filtrosDePantalla($request);
        $qs = EntregaPrendaListadoFiltros::paraQueryString($filtros, true);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs[$vistaId > 0 ? 'vista_id' : 'vista_estandar'] = $vistaId > 0 ? $vistaId : 1;

        return redirect()->route('entrega_prenda_reporte', $qs)->with('mensaje', 'Grilla actualizada.');
    }

    public function guardarEtiquetasListado(Request $request)
    {
        can('listar-entrega-prenda');
        $etiquetas = $request->input('etiquetas', []);
        if (! is_array($etiquetas)) {
            $etiquetas = [];
        }
        ListadoColumnaEtiquetaSupport::guardar(
            EntregaPrendaListadoColumnas::RECURSO,
            $etiquetas,
            array_keys(EntregaPrendaListadoColumnas::catalogoActivo())
        );
        [$filtros] = $this->filtrosDePantalla($request);

        return redirect()->route(
            'entrega_prenda_reporte',
            EntregaPrendaListadoFiltros::paraQueryString($filtros, true)
        )->with('mensaje', 'Etiquetas actualizadas.');
    }

    public function comprobante($entregaId)
    {
        can('listar-entrega-prenda');

        $entrega = Entrega_Prenda_Sueldos::query()
            ->with([
                'empleado.empresa:id,nombre,domicilio,nroinscripcion,pais_id,provincia_id,localidad_id,codigopostal',
                'empleado.empresa.pais:id,nombre',
                'empleado.empresa.provincia:id,nombre',
                'empleado.empresa.localidad:id,nombre,codigopostal',
                'empleado.agrupamiento:id,descripcion',
                'empleado.categoria:id,descripcion',
                'empleado.lugartrabajo:id,nombre',
                'articulos.prenda:id,codigo,descripcion,marca,requiere_certificacion,norma',
                'articulos.color:id,nombre',
                'articulos.talle:id,nombre',
                'usuario:id,nombre',
                'deposito:id,nombre',
            ])
            ->findOrFail($entregaId);

        $view = \View::make('sueldos.entrega_prenda.comprobante', ['entrega' => $entrega])->render();
        $path = storage_path('pdf/comprobantes');
        @mkdir($path, 0775, true);
        $archivo = $path.'/entrega_prenda_'.$entrega->id.'.pdf';
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4');
        $pdf->loadHTML($view)->save($archivo);

        return response()->file($archivo);
    }

    public function editar(Request $request, $entregaId)
    {
        $puedeCabecera = can('editar-entrega-prenda', false);
        $puedeLineas = can('editar-lineas-entrega-prenda', false);
        if (! $puedeCabecera && ! $puedeLineas) {
            can('editar-entrega-prenda');
        }

        $entrega = Entrega_Prenda_Sueldos::query()
            ->with([
                'empleado.empresa:id,nombre',
                'empleado.agrupamiento:id,descripcion',
                'articulos.prenda:id,codigo,descripcion',
                'articulos.color:id,nombre',
                'articulos.talle:id,nombre',
                'deposito:id,nombre',
                'usuario:id,nombre',
            ])
            ->findOrFail($entregaId);

        $config = $this->servicio->configuracion();
        $config->loadMissing('deposito:id,nombre');
        $resumen = $this->servicio->resumenEmpleado($entrega->empleado, (int) ($entrega->anio ?: date('Y')));

        // Filas de la grilla: las del último intento fallido (old) o las líneas grabadas.
        $filasLineas = [];
        $oldLineas = $request->old('lineas');
        if (is_array($oldLineas) && $oldLineas !== []) {
            $varianteIds = collect($oldLineas)->pluck('prenda_articulo_id')->filter()->map(fn ($v) => (int) $v)->all();
            $variantes = Prenda_Articulo_Sueldos::query()
                ->with(['prenda:id,codigo,descripcion', 'color:id,nombre', 'talle:id,nombre'])
                ->whereIn('id', $varianteIds)
                ->get()
                ->keyBy('id');
            foreach ($oldLineas as $l) {
                $v = $variantes->get((int) ($l['prenda_articulo_id'] ?? 0));
                $sku = (string) ($v->sku ?? '');
                $etiqueta = $v
                    ? trim(($v->color->nombre ?? '').' '.($v->talle->nombre ?? '').' · '.($sku !== '' ? $sku : 's/SKU'))
                    : '';
                $filasLineas[] = [
                    'prenda_id' => (int) ($v->prenda_id ?? 0),
                    'prenda_codigo' => (string) ($v->prenda->codigo ?? ''),
                    'prenda_descripcion' => (string) ($v->prenda->descripcion ?? ''),
                    'variante_id' => (int) ($l['prenda_articulo_id'] ?? 0),
                    'variante_sku' => $sku,
                    'variante_etiqueta' => $etiqueta,
                    'cantidad' => (float) ($l['cantidad'] ?? 1),
                ];
            }
        } else {
            foreach ($entrega->articulos as $a) {
                $sku = (string) ($a->sku ?? '');
                $etiqueta = trim(($a->color->nombre ?? '').' '.($a->talle->nombre ?? '').' · '.($sku !== '' ? $sku : 's/SKU'));
                $filasLineas[] = [
                    'prenda_id' => (int) $a->prenda_id,
                    'prenda_codigo' => (string) ($a->prenda->codigo ?? ''),
                    'prenda_descripcion' => (string) ($a->prenda->descripcion ?? ''),
                    'variante_id' => (int) $a->prenda_articulo_id,
                    'variante_sku' => $sku,
                    'variante_etiqueta' => $etiqueta,
                    'cantidad' => (float) $a->cantidad,
                ];
            }
        }

        $nombreDeposito = optional($entrega->deposito)->nombre
            ?: optional($config->deposito ?? null)->nombre;

        return view('sueldos.entrega_prenda.editar', [
            'filasLineas' => $filasLineas,
            'entrega' => $entrega,
            'empleado' => $entrega->empleado,
            'config' => $config,
            'resumen' => $resumen,
            'nombreDeposito' => $nombreDeposito,
            'puedeCabecera' => $puedeCabecera,
            'puedeLineas' => $puedeLineas && $config->estaCompleta(),
            'puedeLineasSinConfig' => $puedeLineas && ! $config->estaCompleta(),
            'puedeOmitirCupo' => $puedeLineas && can('entregar-prenda', false),
            'puedeVerEmpleado' => can('actualizar-empleado-sueldos', false) || can('editar-empleado-sueldos', false),
            'urlRetorno' => $this->urlRetorno($request),
            'retorno' => $request->query('retorno', $request->input('retorno')),
        ]);
    }

    public function actualizar(Request $request, $entregaId)
    {
        $puedeCabecera = can('editar-entrega-prenda', false);
        $puedeLineas = can('editar-lineas-entrega-prenda', false);
        if (! $puedeCabecera && ! $puedeLineas) {
            can('editar-entrega-prenda');
        }

        $request->validate([
            'fecha' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:255'],
            'modo' => ['nullable', 'string', 'in:cabecera,lineas'],
            'lineas' => ['nullable', 'array'],
            'lineas.*.prenda_articulo_id' => ['nullable', 'integer'],
            'lineas.*.cantidad' => ['nullable', 'numeric', 'min:0'],
        ]);

        $entrega = Entrega_Prenda_Sueldos::query()->findOrFail($entregaId);
        $urlRetorno = $this->urlRetorno($request);
        $urlEditar = route('editar_entrega_prenda', array_filter([
            'entrega' => $entrega->id,
            'retorno' => $request->input('retorno'),
        ]));

        $esLineas = $puedeLineas
            && ($request->input('modo') === 'lineas' || $request->has('lineas') || ! $puedeCabecera);
        $resetTulegajo = $puedeCabecera && $request->boolean('reset_tulegajo');

        try {
            if ($esLineas) {
                $lineas = [];
                foreach ((array) $request->input('lineas', []) as $l) {
                    $lineas[] = [
                        'prenda_articulo_id' => (int) ($l['prenda_articulo_id'] ?? 0),
                        'cantidad' => (float) ($l['cantidad'] ?? 0),
                    ];
                }

                $this->servicio->actualizarLineas(
                    $entrega,
                    $lineas,
                    $request->input('fecha'),
                    $request->input('observacion'),
                    auth()->id() ? (int) auth()->id() : null,
                    $request->boolean('omitir_cupo') && can('entregar-prenda', false),
                );
                if ($resetTulegajo) {
                    $this->servicio->actualizarCabecera(
                        $entrega->fresh(),
                        $request->input('fecha'),
                        $request->input('observacion'),
                        true
                    );
                }
                $mensaje = 'Entrega #'.$entrega->id.' actualizada (cabecera y líneas; stock y asiento regrabados).';
            } else {
                $this->servicio->actualizarCabecera(
                    $entrega,
                    $request->input('fecha'),
                    $request->input('observacion'),
                    $resetTulegajo
                );
                $mensaje = 'Entrega #'.$entrega->id.' actualizada (cabecera).';
            }
        } catch (\Throwable $e) {
            return redirect()->to($urlEditar)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to($urlRetorno)->with('mensaje', $mensaje);
    }

    /**
     * Vuelve al listado (con sus filtros si vino `retorno` local) o al index por defecto.
     */
    private function urlRetorno(Request $request): string
    {
        $retorno = trim((string) ($request->query('retorno', $request->input('retorno', ''))));
        $porDefecto = route('entrega_prenda_reporte');
        if ($retorno === '') {
            return $porDefecto;
        }

        $host = $request->getSchemeAndHttpHost();
        if (str_starts_with($retorno, $host.'/') || (str_starts_with($retorno, '/') && ! str_starts_with($retorno, '//'))) {
            return $retorno;
        }

        return $porDefecto;
    }

    /**
     * @return array{0: array<string, mixed>, 1: mixed, 2: bool}
     */
    private function filtrosDePantalla(Request $request): array
    {
        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $forzarEstandar = $request->boolean('vista_estandar') || $request->input('vista_modo') === 'estandar';
        $vistaActiva = null;

        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                EntregaPrendaListadoColumnas::RECURSO,
                $usuarioId
            );
        } elseif (
            ! $forzarEstandar
            && ! $request->has('filtro_valor')
            && ! $request->has('qbe')
            && ! $request->boolean('filtro_limpiar')
            && ! $request->has('anio')
            && ! $request->has('fecha_desde')
            && ! $request->has('agrupamiento_id')
            && ! $request->has('empresa_id')
            && ! $request->has('empresa_todas')
        ) {
            $vistaActiva = ListadoVistaSupport::defaultDelUsuario(EntregaPrendaListadoColumnas::RECURSO, $usuarioId);
        }

        $filtrosRequest = ListadoVistaSupport::prepararQbeContraVista(
            EntregaPrendaListadoFiltros::resolverDesdeRequest($request),
            $request
        );
        $filtros = $filtrosRequest;
        if ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros = EntregaPrendaListadoFiltros::fusionarDesdeVista($filtros, $vistaActiva->filtros_json);
        }
        unset($filtros['_qbe_explicito']);
        $filtros = ListadoVisualSupport::aplicarPedido(
            $filtros,
            $request,
            ($vistaActiva && is_array($vistaActiva->filtros_json)) ? $vistaActiva->filtros_json : null,
            EntregaPrendaListadoFiltros::camposOrdenables()
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

        if ($request->boolean('quitar_orden')) {
            $filtros['sort'] = [];
            if ($vistaActiva) {
                ListadoVistaSupport::recordarOrdenYAgrupar(
                    $vistaActiva,
                    [],
                    $filtros['agrupar'] ?? []
                );
            }
        }


        $filtros = $this->aplicarEmpresas($request, $filtros);

        return [$filtros, $vistaActiva, $forzarEstandar];
    }

    /**
     * Valida el chip de empresa contra las asignadas al usuario.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarEmpresas(Request $request, array $filtros): array
    {
        $permitidos = $this->empresaRepository->allFiltrado()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (($filtros['empresa_scope'] ?? 'todas') === 'todas' || empty($filtros['empresa_id'])) {
            $filtros['empresa_id'] = null;
            $filtros['empresa_scope'] = 'todas';

            return $filtros;
        }

        $empresaId = (int) $filtros['empresa_id'];
        if ($permitidos !== [] && ! in_array($empresaId, $permitidos, true)) {
            $filtros['empresa_id'] = EmpresaFiltroExternoSupport::empresaDefaultDesdeSesion();
            $filtros['empresa_scope'] = $filtros['empresa_id'] ? 'una' : 'todas';
        }

        return $filtros;
    }

    /**
     * @param  list<string>  $columnasVisibles
     * @return array<string, mixed>
     */
    private function queryDeFiltros(array $filtros, array $columnasVisibles, mixed $vistaActiva, bool $forzarEstandar, Request $request): array
    {
        $filtrosQuery = EntregaPrendaListadoFiltros::paraQueryString($filtros, true);
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

        return $filtrosQuery;
    }

    /**
     * Entregas por prenda (nivel 0): barras = líneas (legible);
     * unidades van al tooltip. Top N + «Otras» para no aplastar la escala
     * con un outlier de unidades (ej. Guantes Latex histórico 60.919).
     *
     * @param  array<string, mixed>  $cortes
     * @return array{labels: list<string>, cantidades: list<float>, lineas: list<int>, total: int, truncado: bool, aviso: string}
     */
    private function graficoDesdeCortes(array $cortes): array
    {
        $filas = [];
        foreach ($cortes['filas'] ?? [] as $fila) {
            if ((int) ($fila['nivel'] ?? 0) !== 0) {
                continue;
            }
            $valor = trim((string) ($fila['valor'] ?? ''));
            $filas[] = [
                'label' => $valor !== '' ? $valor : '(sin prenda)',
                'cantidad' => round((float) (($fila['sumas']['cantidad'] ?? 0)), 3),
                'lineas' => (int) ($fila['count'] ?? 0),
            ];
        }

        // Orden por líneas (entregas): refleja mejor “entregas por prenda”.
        usort($filas, static fn (array $a, array $b): int => $b['lineas'] <=> $a['lineas']);

        $tope = 12;
        $truncado = count($filas) > $tope;
        $principales = array_slice($filas, 0, $tope);
        $resto = array_slice($filas, $tope);

        $labels = [];
        $cantidades = [];
        $lineas = [];
        foreach ($principales as $f) {
            $labels[] = $f['label'];
            $cantidades[] = $f['cantidad'];
            $lineas[] = $f['lineas'];
        }
        if ($resto !== []) {
            $labels[] = 'Otras ('.count($resto).')';
            $cantidades[] = round(array_sum(array_column($resto, 'cantidad')), 3);
            $lineas[] = (int) array_sum(array_column($resto, 'lineas'));
        }

        $maxUnidades = 0.0;
        $medianaLineas = 0;
        if ($filas !== []) {
            $maxUnidades = max(array_column($filas, 'cantidad'));
            $soloLineas = array_column($filas, 'lineas');
            sort($soloLineas);
            $medianaLineas = $soloLineas[(int) floor((count($soloLineas) - 1) / 2)] ?? 0;
        }

        $aviso = '';
        // Si alguna prenda concentra unidades desproporcionadas vs el resto, avisar.
        $unidadesOrden = array_column($filas, 'cantidad');
        rsort($unidadesOrden);
        $segunda = (float) ($unidadesOrden[1] ?? 0);
        if ($maxUnidades > 0 && $segunda > 0 && ($maxUnidades / $segunda) >= 10) {
            $aviso = 'Hay una prenda con unidades muy por encima del resto (dato histórico). '
                .'Las barras muestran cantidad de líneas; el tip incluye las unidades.';
        } elseif ($medianaLineas > 0) {
            $aviso = 'Barras = líneas de entrega por prenda (top '.$tope.'). El tip muestra también las unidades.';
        }

        return [
            'labels' => $labels,
            'cantidades' => $cantidades,
            'lineas' => $lineas,
            'total' => (int) ($cortes['total'] ?? 0),
            'truncado' => $truncado || ($cortes['truncado'] ?? false),
            'aviso' => $aviso,
            // Serie principal del canvas: líneas (no unidades).
            'serie_principal' => 'lineas',
        ];
    }
}
