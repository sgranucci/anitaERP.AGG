<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTicket;
use App\Repositories\Ticket\TicketRepositoryInterface;
use App\Repositories\Ticket\Ticket_EstadoRepositoryInterface;
use App\Repositories\Ticket\Categoria_TicketRepositoryInterface;
use App\Repositories\Ticket\Subcategoria_TicketRepositoryInterface;
use App\Repositories\Ticket\AreadestinoRepositoryInterface;
use App\Repositories\Ticket\Sector_TicketRepositoryInterface;
use App\Repositories\Ticket\Turno_TicketRepositoryInterface;
use App\Repositories\Configuracion\SalaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ticket\TicketService;
use App\Models\Ticket\Ticket_Estado;
use App\Models\Ticket\Ticket_Tarea_Novedad;
use App\Queries\Ticket\TicketQueryInterface;
use App\Exports\Ticket\AdministracionTicketListadoExport;
use App\Models\Listado\ListadoEnvioProgramado;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoLienzoSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Listado\ListadoVisualSupport;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Ticket\AdministracionTicketListadoColumnas;
use App\Support\Ticket\AdministracionTicketListadoEnvioSupport;
use App\Support\Ticket\AdministracionTicketListadoFiltros;
use App\Support\Ticket\AdministracionTicketListadoPreferenciasUsuario;
use App\Support\Ticket\AdministracionTicketListadoResumen;
use App\Support\Ticket\TicketEmpresaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use DB;
use Exception;

class Administracion_TicketController extends Controller
{
	private $categoria_ticketRepository;
    private $subcategoria_ticketRepository;
    private $areadestinoRepository;
    private $sector_ticketRepository;
    private $ticketRepository;
    private $ticket_estadoRepository;
    private $turno_ticketRepository;
    private $salaRepository;
    private $empresaRepository;
    private $ticketQuery;
    private $ticketService;

	public function __construct(Categoria_TicketRepositoryInterface $categoria_ticketrepository,
                                Subcategoria_TicketRepositoryInterface $subcategoria_ticketrepository,
                                AreadestinoRepositoryInterface $areadestinorepository,
                                TicketRepositoryInterface $ticketrepository,
                                Ticket_EstadoRepositoryInterface $ticket_estadorepository,
                                SalaRepositoryInterface $salarepository,
                                Sector_TicketRepositoryInterface $sectorrepository,
                                Turno_TicketRepositoryInterface $turno_ticketrepository,
                                EmpresaRepositoryInterface $empresarepository,
                                TicketService $ticketservice,
                                TicketQueryInterface $ticketquery
                                )
    {
        $this->categoria_ticketRepository = $categoria_ticketrepository;
        $this->subcategoria_ticketRepository = $subcategoria_ticketrepository;
        $this->areadestinoRepository = $areadestinorepository;
        $this->ticketRepository = $ticketrepository;
        $this->ticket_estadoRepository = $ticket_estadorepository;
        $this->sector_ticketRepository = $sectorrepository;
        $this->turno_ticketRepository = $turno_ticketrepository;
        $this->salaRepository = $salarepository;
        $this->empresaRepository = $empresarepository;
        $this->ticketService = $ticketservice;
        $this->ticketQuery = $ticketquery;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-ticket');

        $armado = $this->armarListado($request);
        if ($armado instanceof \Illuminate\Http\RedirectResponse) {
            return $armado;
        }

        return view('ticket.administracion_ticket.index', $armado);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-ticket');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->filtrosDePantalla($request, $busqueda);

        switch ($formato) {
        case 'PDF':
            $ticket = $this->ticketQuery->leeTicketAdministracion($filtros, false);

            $calculadas = AdministracionTicketListadoFiltros::normalizarCalculadas($filtros['calculadas'] ?? []);
            $view = \View::make('ticket.administracion_ticket.listado', compact('ticket', 'filtros', 'calculadas'))
                ->render();
            $path = storage_path('pdf/listados');
            if (! is_dir($path)) {
                mkdir($path, 0775, true);
            }
            $nombre_pdf = 'listado_administracion_ticket';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal', 'landscape');
            $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');

        case 'EXCEL':
            return (new AdministracionTicketListadoExport($this->ticketQuery))
                ->parametros($filtros)
                ->download('administracion_ticket.xlsx');

        case 'CSV':
            return (new AdministracionTicketListadoExport($this->ticketQuery))
                ->parametros($filtros)
                ->download('administracion_ticket.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consulta_administracion_ticket', AdministracionTicketListadoFiltros::paraQueryString($filtros));
    }

    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = AdministracionTicketListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);

        return AdministracionTicketListadoFiltros::aplicarAlcanceUsuario($filtros, (int) auth()->id());
    }

    /**
     * Export y mail usan la misma pantalla: vista y consulta encima del alcance de rol.
     *
     * @return array<string, mixed>
     */
    private function filtrosDePantalla(Request $request, ?string $busquedaRuta = null): array
    {
        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $vistaJson = null;
        $filtros = $this->resolverFiltrosListado($request, $busquedaRuta);
        if ($request->filled('vista_id')) {
            $vista = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                AdministracionTicketListadoColumnas::RECURSO,
                $usuarioId
            );
            if ($vista && is_array($vista->filtros_json)) {
                $vistaJson = $vista->filtros_json;
                $filtros = AdministracionTicketListadoFiltros::fusionarDesdeVista($filtros, $vistaJson);
                $filtros = AdministracionTicketListadoFiltros::aplicarAlcanceUsuario($filtros, (int) auth()->id());
            }
        }
        $filtros = ListadoVisualSupport::aplicarPedido(
            $filtros,
            $request,
            $vistaJson,
            AdministracionTicketListadoFiltros::camposVisual()
        );

        return AdministracionTicketListadoFiltros::mezclarCalculadas($filtros, $request, $vistaJson);
    }

    /**
     * @return array<string, mixed>|\Illuminate\Http\RedirectResponse
     */
    private function armarListado(Request $request): array|\Illuminate\Http\RedirectResponse
    {
        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $vistas = ListadoVistaSupport::listarParaUsuario(AdministracionTicketListadoColumnas::RECURSO, $usuarioId);
        $vistaActiva = null;
        $forzarEstandar = $request->boolean('vista_estandar');
        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                AdministracionTicketListadoColumnas::RECURSO,
                $usuarioId
            );
        } elseif (
            ! $forzarEstandar
            && ! $request->has('filtro_valor')
            && ! $request->has('qbe')
            && ! $request->boolean('filtro_limpiar')
            && ! $request->has('filtro_estado')
            && ! $request->has('ver_todos_tickets')
            && ! $request->has('fecha_desde')
            && ! $request->has('fecha_hasta')
            && ! $request->has('fecha_resolucion_desde')
            && ! $request->has('fecha_resolucion_hasta')
        ) {
            $vistaActiva = ListadoVistaSupport::defaultDelUsuario(AdministracionTicketListadoColumnas::RECURSO, $usuarioId)
                ?? ListadoVistaSupport::defaultDelRol(AdministracionTicketListadoColumnas::RECURSO, (int) session('rol_id'));
        }

        $filtros = $this->resolverFiltrosListado($request);
        $vistaJson = ($vistaActiva && is_array($vistaActiva->filtros_json)) ? $vistaActiva->filtros_json : null;
        if ($vistaJson) {
            $filtros = AdministracionTicketListadoFiltros::fusionarDesdeVista($filtros, $vistaJson);
            $filtros = AdministracionTicketListadoFiltros::aplicarAlcanceUsuario($filtros, (int) auth()->id());
        }
        $filtros = ListadoVisualSupport::aplicarPedido(
            $filtros,
            $request,
            $vistaJson,
            AdministracionTicketListadoFiltros::camposVisual()
        );
        $filtros = AdministracionTicketListadoFiltros::mezclarCalculadas($filtros, $request, $vistaJson);

        if ($request->boolean('quitar_orden')) {
            $filtros['sort'] = [];
            if ($vistaActiva && (int) $vistaActiva->usuario_id === (int) auth()->id()) {
                ListadoVistaSupport::recordarOrdenYAgrupar($vistaActiva, [], $filtros['agrupar'] ?? []);
            }
            $params = $this->queryDePantalla($filtros, $vistaActiva, $forzarEstandar);

            return redirect()->route('consulta_administracion_ticket', $params)
                ->with('mensaje', 'Se quitó el orden de la grilla.');
        }

        $catalogo = AdministracionTicketListadoColumnas::catalogoActivo();
        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            AdministracionTicketListadoColumnas::RECURSO,
            $catalogo
        );
        $grillaLayout = ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== [])
            ? AdministracionTicketListadoPreferenciasUsuario::normalizarLayout($vistaActiva->columnas_json)
            : AdministracionTicketListadoPreferenciasUsuario::grillaEstandar();
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);
        $ticket = $this->ticketQuery->leeTicketAdministracion($filtros, true);
        $hayVisual = ($filtros['grafico']['tipo'] ?? '') !== ''
            || (is_array($filtros['graficos'] ?? null) && $filtros['graficos'] !== [])
            || ($filtros['agrupar'] ?? []) !== [];
        $universo = $hayVisual ? $this->ticketQuery->leeTicketAdministracion($filtros, false) : collect();
        $cortes = AdministracionTicketListadoResumen::desdeFilas($universo, $filtros['agrupar'] ?? [], $etiquetas)['cortes'];
        $graficoSeries = $this->seriesGraficos($filtros, $etiquetas, $universo);
        return [
            'ticket' => $ticket,
            'filtros' => $filtros,
            'filtrosQuery' => $this->queryDePantalla($filtros, $vistaActiva, $forzarEstandar, $columnasVisibles),
            'camposFiltro' => AdministracionTicketListadoFiltros::CAMPOS,
            'ver_todos_tickets' => ! empty($filtros['ver_todos_tickets']),
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
            'rolesVista' => ListadoVisualSupport::rolesParaInstalacion(),
            'enviosProgramados' => ListadoEnvioProgramado::query()
                ->where('usuario_id', (int) auth()->id())
                ->where('recurso', AdministracionTicketListadoColumnas::RECURSO)
                ->where('activo', true)
                ->orderByDesc('id')
                ->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $columnasVisibles
     * @return array<string, mixed>
     */
    private function queryDePantalla(array $filtros, mixed $vistaActiva, bool $forzarEstandar, array $columnasVisibles = []): array
    {
        $qs = AdministracionTicketListadoFiltros::paraQueryString($filtros);
        $qs = array_merge($qs, ListadoVisualSupport::paraQueryString($filtros));
        if ($columnasVisibles !== []) {
            $qs['columnas'] = implode(',', $columnasVisibles);
        }
        if ($vistaActiva) {
            $qs['vista_id'] = $vistaActiva->id;
        } elseif ($forzarEstandar) {
            $qs['vista_estandar'] = 1;
        }

        return $qs;
    }

    public function previewWorkbench(Request $request)
    {
        can('listar-ticket');
        $filtros = $this->filtrosDePantalla($request);
        $layout = AdministracionTicketListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
        $orden = ListadoOrdenamientoSupport::normalizar(
            $request->input('sort', $filtros['sort'] ?? []),
            AdministracionTicketListadoFiltros::camposOrdenables()
        );
        $agrupar = ListadoAgrupacionSupport::normalizar(
            $request->input('group', $filtros['agrupar'] ?? []),
            AdministracionTicketListadoFiltros::camposOrdenables()
        );
        $universo = $this->ticketQuery->leeTicketAdministracion($filtros, false);
        $filas = $universo->take(ListadoDisenadorPreviewSupport::LIMITE_MUESTRA);
        $cortes = ['activo' => false];
        if ($agrupar !== []) {
            $cortes = AdministracionTicketListadoResumen::desdeFilas($universo, $agrupar, $etiquetas)['cortes'];
        }

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            $agrupar,
            $filas,
            static fn (object $row, string $key): string => AdministracionTicketListadoColumnas::valorCelda($row, $key),
            $universo->count(),
            $etiquetas,
            $cortes
        ));
    }

    public function guardarVistaListado(Request $request)
    {
        can('listar-ticket');
        $filtros = $this->filtrosDePantalla($request);
        $graficosVista = ListadoLienzoSupport::normalizar(
            $request->input('graficos'),
            $request->input('grafico'),
            $request->exists('graficos'),
            AdministracionTicketListadoFiltros::camposVisual()
        );
        $layout = AdministracionTicketListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $vista = ListadoVistaSupport::guardar(
            AdministracionTicketListadoColumnas::RECURSO,
            (int) auth()->id(),
            (string) $request->input('nombre', ''),
            [
                'modo' => $filtros['modo'] ?? 'todos',
                'qbe' => $filtros['qbe'] ?? [],
                'sort' => $filtros['sort'] ?? [],
                'orden' => $filtros['sort'] ?? [],
                'agrupar' => $filtros['agrupar'] ?? [],
                'grafico' => ListadoLienzoSupport::primero($graficosVista),
                'graficos' => $graficosVista,
                'formato' => ListadoVisualSupport::normalizarFormato($request->input('formato'), AdministracionTicketListadoFiltros::camposVisual()),
                'calculadas' => AdministracionTicketListadoFiltros::normalizarCalculadas($request->input('calculadas')),
            ],
            $layout,
            $request->boolean('es_default'),
            $request->boolean('compartida'),
            $request->filled('vista_id') ? (int) $request->input('vista_id') : null
        );
        if (! $vista) {
            return redirect()->route('consulta_administracion_ticket', AdministracionTicketListadoFiltros::paraQueryString($filtros))
                ->with('error', 'No se pudo guardar la vista.');
        }
        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));
        ListadoVisualSupport::asignarRol($vista, $request, AdministracionTicketListadoColumnas::RECURSO);
        $qs = $this->queryDePantalla($filtros, $vista, false, ListadoGrillaConfigSupport::keysVisibles($layout));

        return redirect()->route('consulta_administracion_ticket', $qs)
            ->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public function eliminarVistaListado(int $id)
    {
        can('listar-ticket');
        $ok = ListadoVistaSupport::eliminar($id, AdministracionTicketListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('consulta_administracion_ticket', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        can('listar-ticket');
        $layout = AdministracionTicketListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;
        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            $vista = ListadoVistaSupport::findParaUsuario($vistaId, AdministracionTicketListadoColumnas::RECURSO, (int) auth()->id());
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            AdministracionTicketListadoPreferenciasUsuario::persistirGrillaEstandar($layout);
        }
        $filtros = $this->filtrosDePantalla($request);
        $qs = $this->queryDePantalla($filtros, $vistaId > 0 ? (object) ['id' => $vistaId] : null, $vistaId < 1, ListadoGrillaConfigSupport::keysVisibles($layout));

        return redirect()->route('consulta_administracion_ticket', $qs)->with('mensaje', 'Grilla actualizada.');
    }

    public function guardarEtiquetasListado(Request $request)
    {
        can('listar-ticket');
        $etiquetas = $request->input('etiquetas', []);
        if (! is_array($etiquetas)) {
            $etiquetas = [];
        }
        ListadoColumnaEtiquetaSupport::guardar(
            AdministracionTicketListadoColumnas::RECURSO,
            $etiquetas,
            array_keys(AdministracionTicketListadoColumnas::catalogoActivo())
        );

        return redirect()->route(
            'consulta_administracion_ticket',
            AdministracionTicketListadoFiltros::paraQueryString($this->resolverFiltrosListado($request))
        )->with('mensaje', 'Etiquetas actualizadas.');
    }

    public function enviarListado(Request $request)
    {
        can('listar-ticket');
        $email = trim((string) $request->input('email', ''));
        $filtros = $this->filtrosDePantalla($request);
        $qs = $this->queryDePantalla($filtros, $request->filled('vista_id') ? (object) ['id' => (int) $request->input('vista_id')] : null, false);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('consulta_administracion_ticket', $qs)->with('error', 'El correo no es válido.');
        }
        $frecuencia = (string) $request->input('programar', '');
        if (in_array($frecuencia, ['diaria', 'semanal'], true)) {
            $filtros['_rol_id'] = (int) session('rol_id');
            $filtros['_rol_nombre'] = (string) session('rol_nombre');
            ListadoEnvioProgramado::query()->create([
                'recurso' => AdministracionTicketListadoColumnas::RECURSO,
                'usuario_id' => (int) auth()->id(),
                'email' => $email,
                'frecuencia' => $frecuencia,
                'filtros_json' => $filtros,
                'activo' => true,
            ]);

            return redirect()->route('consulta_administracion_ticket', $qs)->with(
                'mensaje',
                $frecuencia === 'semanal'
                    ? 'El listado queda programado cada lunes a '.$email.'.'
                    : 'El listado queda programado todos los días a '.$email.'.'
            );
        }
        try {
            $filas = AdministracionTicketListadoEnvioSupport::enviar($filtros, $email);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('consulta_administracion_ticket', $qs)
                ->with('error', 'No se pudo enviar el listado. Revisá el correo o la configuración de mail.');
        }

        return redirect()->route('consulta_administracion_ticket', $qs)
            ->with('mensaje', 'Listado enviado a '.$email.' ('.$filas.' filas).');
    }

    public function bajaEnvioProgramado(int $id)
    {
        can('listar-ticket');
        $envio = ListadoEnvioProgramado::query()
            ->where('usuario_id', (int) auth()->id())
            ->where('recurso', AdministracionTicketListadoColumnas::RECURSO)
            ->whereKey($id)
            ->first();
        if ($envio) {
            $envio->activo = false;
            $envio->save();
        }

        return redirect()->back()->with('mensaje', 'Se dio de baja el envío programado.');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, string>  $etiquetas
     * @param  iterable<int, object>  $universo
     * @return list<array<string, mixed>>
     */
    private function seriesGraficos(array $filtros, array $etiquetas, iterable $universo): array
    {
        $campos = AdministracionTicketListadoFiltros::camposVisual();
        $lista = is_array($filtros['graficos'] ?? null) ? $filtros['graficos'] : [];
        if ($lista === [] && ($filtros['grafico']['tipo'] ?? '') !== '') {
            $lista = [$filtros['grafico']];
        }
        $out = [];
        foreach ($lista as $grafico) {
            if (! is_array($grafico) || ($grafico['tipo'] ?? '') === '') {
                continue;
            }
            $para = $filtros;
            $para['grafico'] = $grafico;
            $cortesPara = ListadoVisualSupport::filtrosParaCortes($para, $campos);
            if ($cortesPara === null) {
                continue;
            }
            $filas = ($cortesPara['qbe'] ?? []) == ($filtros['qbe'] ?? [])
                ? $universo
                : $this->ticketQuery->leeTicketAdministracion($cortesPara, false);
            $cortes = AdministracionTicketListadoResumen::desdeFilas(
                $filas,
                $cortesPara['agrupar'] ?? [],
                $etiquetas
            )['cortes'];
            $out[] = ListadoVisualSupport::serie($cortesPara['grafico'], $cortes, $etiquetas);
        }

        return $out;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear(Request $request)
    {
        can('crear-ticket');

        $areadestino_query = $this->areadestinoRepository->all();
        $sector_query = $this->sector_ticketRepository->all();
        $sala_query = $this->salaRepository->all();
        $turno_query = $this->turno_ticketRepository->all();
        $estado_novedad_enum = Ticket_Tarea_Novedad::$enumEstado;
        $estado_novedad_json = json_encode(Ticket_Tarea_Novedad::$enumEstado);
        $estado_enum = Ticket_Estado::$enumEstado;
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, AdministracionTicketListadoFiltros::class);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = old('empresa_id');
        if (! (int) $empresa_id) {
            $salaOld = (int) old('sala_id', 0);
            $empresa_id = $salaOld > 0 ? TicketEmpresaSupport::empresaIdDesdeSala($salaOld) : null;
        }

        return view('ticket.administracion_ticket.crear', compact('areadestino_query', 'sector_query', 'sala_query',
                                                                'turno_query', 'estado_novedad_enum',
                                                                'estado_novedad_json', 'estado_enum', 'filtrosQuery',
                                                                'empresa_query', 'empresa_id'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTicket $request)
    {
        $resultado = $this->ticketService->guardaTicket($request, 'administracion');

        if (! empty($resultado['errores'])) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'No se pudo guardar el ticket. Revisá los datos e intentá de nuevo.']);
        }

        return redirect()->route('consulta_administracion_ticket', QueryRetornoListado::desdeRequest($request, AdministracionTicketListadoFiltros::class))
            ->with('mensaje', 'Ticket creado con éxito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar(Request $request, $id)
    {
        can('editar-ticket');

		$data = $this->ticketRepository->find($id);
        $data->loadMissing('salas');
        $areadestino_query = $this->areadestinoRepository->all();
        $sector_query = $this->sector_ticketRepository->all();
        $sala_query = $this->salaRepository->all();
        $turno_query = $this->turno_ticketRepository->all();
        $estado_novedad_enum = Ticket_Tarea_Novedad::$enumEstado;
        $estado_novedad_json = json_encode(Ticket_Tarea_Novedad::$enumEstado);
        $estado_enum = Ticket_Estado::$enumEstado;
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, AdministracionTicketListadoFiltros::class);
        $empresa_id = old('empresa_id', TicketEmpresaSupport::empresaIdDesdeTicket($data));
        $empresa_query = TicketEmpresaSupport::asegurarEnColeccion(
            $this->empresaRepository->allFiltrado(),
            (int) $empresa_id
        );

        return view('ticket.administracion_ticket.editar', compact('data', 'areadestino_query', 'sector_query', 
                                                                    'sala_query', 'turno_query', 'estado_novedad_enum',
                                                                    'estado_novedad_json', 'estado_enum', 'filtrosQuery',
                                                                    'empresa_query', 'empresa_id'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTicket $request, $id)
    {
        can('actualizar-ticket');

        $this->ticketService->actualizaTicket($request, $id, 'administracion');

        return redirect()->route(
            'edita_administracion_ticket',
            QueryRetornoListado::paramsRutaEditar($request, AdministracionTicketListadoFiltros::class, (int) $id)
        )->with('mensaje', 'Ticket actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-ticket');

        if ($request->ajax()) 
		{
			$fl_borro = false;
            
			if ($this->ticketRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function guardarTicketTareaNovedad(Request $request)
    {
        return $this->ticketService->grabaTicketTareaNovedad($request->all());
    }

    public function leerTicketTareaNovedad($ticket_tarea_id)
    {
        return $this->ticketService->leeTicketTareaNovedad($ticket_tarea_id);
    }

    public function leerHistoriaTicket($ticket_id)
    {
        return $this->ticketService->leeHistoriaTicket($ticket_id);
    }

    public function cambiarTecnico($ticket_tarea_id, $tecnico_ticket_id)
    {
        return $this->ticketService->cambiarTecnico($ticket_tarea_id, $tecnico_ticket_id);
    }

    public function finalizarTarea($ticket_tarea_id, $fechafinalizacion, $tiempoinsumido)
    {
        return $this->ticketService->finalizarTarea($ticket_tarea_id, $fechafinalizacion, $tiempoinsumido);
    }

    public function cambiarEstadoTarea(Request $request, $ticket_tarea_id)
    {
        $request->validate([
            'estado' => 'required|string|max:50',
        ]);

        try {
            return response()->json(
                $this->ticketService->cambiarEstadoTarea((int) $ticket_tarea_id, (string) $request->input('estado'))
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['mensaje' => 'ng', 'error' => 'No se pudo cambiar el estado de la tarea.'], 500);
        }
    }

    public function limpiafiltro(Request $request) {
        return redirect()->route('consulta_administracion_ticket');
	}

}
