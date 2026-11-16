<?php

declare(strict_types=1);

namespace App\Http\Controllers\Direccion;

use App\Exports\Direccion\ConsultaInteligenteListadoExport;
use App\Exports\Direccion\ConsultaInteligenteMatrizExport;
use App\Http\Controllers\Controller;
use App\Models\Listado\ListadoEnvioProgramado;
use App\Services\Direccion\ConsultaInteligenteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\ConsultaInteligente\ConsultaInteligenteAlertaSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteAuditoriaSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteJoinCatalogo;
use App\Support\ConsultaInteligente\ConsultaInteligenteMultiEntidadSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteConsultaMetricasSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteNlContextoSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteNlDiccionarioSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteNlEnlaceSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteNlFavoritoSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteNlLlmSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteNlMetricasDiccionarioSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteNlSugerenciasSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteNlSupport;
use App\Support\ConsultaInteligente\ConsultaInteligentePermisosSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteSnapshotCompareSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteSnapshotSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteTableroSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteUnirSupport;
use App\Support\ConsultaInteligente\EntidadSemanticaCatalogo;
use App\Support\Listado\ListadoQbeSupport;
use App\Support\Reportes\DompdfListadoSupport;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

class ConsultaInteligenteController extends Controller
{
    public function __construct(
        private readonly ConsultaInteligenteService $consultaService,
    ) {
    }

    public function index(Request $request)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        $modulo = trim((string) $request->query('modulo', ''));
        $q = trim((string) $request->query('q', ''));

        if ($q !== '' || $modulo !== '') {
            ConsultaInteligenteAuditoriaSupport::registrar(
                ConsultaInteligenteAuditoriaSupport::ACCION_CATALOGO,
                null,
                $q !== '' ? 'filtro: '.$q : null,
                ['modulo' => $modulo]
            );
        }

        return view('direccion.consulta_inteligente.index', $this->datosIndex($request, [
            'pregunta' => (string) $request->query('pregunta', ''),
            'nlResultado' => null,
        ]));
    }

    public function preguntar(Request $request)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        $pregunta = trim((string) $request->input('pregunta', ''));
        $ctx = ConsultaInteligenteNlContextoSupport::actual();
        if ($request->boolean('refinar') && $ctx !== null) {
            $pregunta = ConsultaInteligenteNlContextoSupport::componer($ctx['pregunta'], $pregunta);
        }

        $resultado = ConsultaInteligenteNlSupport::interpretar($pregunta);
        if (! empty($resultado['ok'])) {
            $resultado['_pregunta'] = $pregunta;
            try {
                $narrativa = ConsultaInteligenteConsultaMetricasSupport::narrativaDesdeNl($resultado);
                if ($narrativa !== null) {
                    $resultado['narrativa'] = $narrativa;
                }
            } catch (\Throwable) {
                // Preview sin números si el universo falla: el QBE sigue válido.
            }
            unset($resultado['_pregunta']);
        }
        ConsultaInteligenteAuditoriaSupport::registrar(
            ConsultaInteligenteAuditoriaSupport::ACCION_NL,
            $resultado['entidad'] ?? null,
            mb_substr($pregunta, 0, 200),
            [
                'ok' => (bool) ($resultado['ok'] ?? false),
                'refinado' => $request->boolean('refinar'),
                'conteo' => $resultado['narrativa']['conteo'] ?? null,
            ]
        );
        if (! empty($resultado['ok'])) {
            ConsultaInteligenteNlContextoSupport::recordar($pregunta, $resultado);
        }

        // Abrir directo (checkbox) o preview del QBE armado en el catálogo.
        if ($resultado['ok'] && ! empty($resultado['url']) && $request->boolean('abrir_directo')) {
            $msg = 'Consulta interpretada: '.($resultado['explicacion'] ?? '');
            if (! empty($resultado['narrativa']['texto'])) {
                $msg = (string) $resultado['narrativa']['texto'];
            }

            return redirect()->to($resultado['url'])->with('mensaje', $msg);
        }

        return view('direccion.consulta_inteligente.index', $this->datosIndex($request, [
            'pregunta' => $pregunta,
            'nlResultado' => $resultado,
        ]));
    }

    public function sugerenciasNl(Request $request)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        $q = trim((string) $request->query('q', ''));
        $items = ConsultaInteligenteNlSugerenciasSupport::paraUsuario($q !== '' ? $q : null, 10);

        return response()->json(['ok' => true, 'items' => $items]);
    }

    public function crearEnlaceNl(Request $request)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        $pregunta = trim((string) $request->input('pregunta', ''));
        $titulo = trim((string) $request->input('titulo', ''));
        $entidad = trim((string) $request->input('entidad', ''));
        $query = null;
        $rawQuery = $request->input('query_json');
        if (is_string($rawQuery) && $rawQuery !== '') {
            $decoded = json_decode($rawQuery, true);
            $query = is_array($decoded) ? $decoded : null;
        } elseif (is_array($rawQuery)) {
            $query = $rawQuery;
        }

        $creado = ConsultaInteligenteNlEnlaceSupport::crear(
            $pregunta,
            $titulo !== '' ? $titulo : null,
            $entidad !== '' ? $entidad : null,
            $query
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($creado, ! empty($creado['ok']) ? 200 : 422);
        }

        if (empty($creado['ok'])) {
            return redirect()
                ->route('consulta_inteligente', array_filter(['pregunta' => $pregunta ?: null]))
                ->with('mensaje_error', $creado['mensaje'] ?? 'No se pudo crear el enlace.');
        }

        return redirect()
            ->route('consulta_inteligente', array_filter(['pregunta' => $pregunta ?: null]))
            ->with('mensaje', 'Enlace listo: '.$creado['url'])
            ->with('ci_enlace_url', $creado['url']);
    }

    public function abrirEnlaceNl(string $token)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        $enlace = ConsultaInteligenteNlEnlaceSupport::resolver($token);
        if ($enlace === null) {
            return redirect()
                ->route('consulta_inteligente')
                ->with('mensaje_error', 'Enlace inválido o vencido.');
        }

        // Si trae entidad+query, ir al explorador (respeta permiso de esa entidad).
        if (! empty($enlace['entidad']) && is_array($enlace['query']) && EntidadSemanticaCatalogo::existe($enlace['entidad'])) {
            try {
                $this->consultaService->entidadAutorizada($enlace['entidad']);
            } catch (InvalidArgumentException $e) {
                return redirect()->route('consulta_inteligente')->with('mensaje_error', $e->getMessage());
            }
            $query = $enlace['query'];
            $query['consultar'] = 1;

            return redirect()
                ->to(EntidadSemanticaCatalogo::urlExplorar($enlace['entidad'], $query))
                ->with('mensaje', 'Consulta compartida: '.$enlace['titulo']);
        }

        // Fallback: interpretar la pregunta NL y mostrar preview (con narrativa).
        $resultado = ConsultaInteligenteNlSupport::interpretar($enlace['pregunta']);
        if (! empty($resultado['ok'])) {
            $resultado['_pregunta'] = $enlace['pregunta'];
            try {
                $narrativa = ConsultaInteligenteConsultaMetricasSupport::narrativaDesdeNl($resultado);
                if ($narrativa !== null) {
                    $resultado['narrativa'] = $narrativa;
                }
            } catch (\Throwable) {
            }
            unset($resultado['_pregunta']);
            ConsultaInteligenteNlContextoSupport::recordar($enlace['pregunta'], $resultado);
        }

        return view('direccion.consulta_inteligente.index', $this->datosIndex(request(), [
            'pregunta' => $enlace['pregunta'],
            'nlResultado' => $resultado,
        ]))->with('mensaje', 'Consulta compartida: '.$enlace['titulo']);
    }

    public function guardarFavoritoNl(Request $request)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        $pregunta = trim((string) $request->input('pregunta', ''));
        $titulo = trim((string) $request->input('titulo', ''));
        $ok = ConsultaInteligenteNlFavoritoSupport::guardar($pregunta, $titulo !== '' ? $titulo : null);

        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok]);
        }

        return redirect()
            ->route('consulta_inteligente', array_filter(['pregunta' => $pregunta ?: null]))
            ->with(
                $ok ? 'mensaje' : 'mensaje_error',
                $ok
                    ? 'Pregunta guardada en favoritos.'
                    : 'No se pudo guardar (¿máximo '.ConsultaInteligenteNlFavoritoSupport::MAX.' o vacía?).'
            );
    }

    public function eliminarFavoritoNl(string $id)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);
        $ok = ConsultaInteligenteNlFavoritoSupport::eliminar($id);

        return redirect()
            ->route('consulta_inteligente')
            ->with($ok ? 'mensaje' : 'mensaje_error', $ok ? 'Favorito eliminado.' : 'No se pudo eliminar.');
    }

    public function limpiarContextoNl()
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);
        ConsultaInteligenteNlContextoSupport::limpiar();

        return redirect()->route('consulta_inteligente')->with('mensaje', 'Contexto de refinamiento limpiado.');
    }

    public function deshacerContextoNl()
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);
        $ok = ConsultaInteligenteNlContextoSupport::deshacerUltimo();

        return redirect()
            ->route('consulta_inteligente')
            ->with(
                $ok ? 'mensaje' : 'mensaje_error',
                $ok ? 'Último refinamiento deshecho.' : 'No hay turnos para deshacer.'
            );
    }

    public function programarEnvio(Request $request, string $entidad)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        try {
            $ent = $this->consultaService->entidadAutorizada($entidad);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('consulta_inteligente')->with('mensaje_error', $e->getMessage());
        }

        $email = trim((string) $request->input('email', auth()->user()->email ?? ''));
        $frecuencia = (string) $request->input('frecuencia', 'diario');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()
                ->route('explorar_consulta_inteligente', ['entidad' => $entidad, 'consultar' => 1])
                ->with('mensaje_error', 'Indicá un email válido para el envío programado.');
        }
        if (! in_array($frecuencia, ['diario', 'semanal'], true)) {
            $frecuencia = 'diario';
        }

        $filtros = $this->filtrosDesdeRequestOJson($ent, $request);
        $filtros['consultar'] = 1;

        ListadoEnvioProgramado::query()->create([
            'recurso' => EntidadSemanticaCatalogo::recursoVista($entidad),
            'usuario_id' => (int) auth()->id(),
            'email' => $email,
            'frecuencia' => $frecuencia,
            'filtros_json' => $filtros,
            'activo' => true,
        ]);

        return redirect()
            ->to(EntidadSemanticaCatalogo::urlExplorar($entidad, $filtros))
            ->with('mensaje', 'Envío '.$frecuencia.' programado a '.$email.'.');
    }

    public function tablero()
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        $tablero = ConsultaInteligenteTableroSupport::armar();

        return view('direccion.consulta_inteligente.tablero', [
            'tablero' => $tablero,
            'alertas' => ConsultaInteligenteAlertaSupport::paraUsuario(),
        ]);
    }

    public function configuracion()
    {
        can(ConsultaInteligentePermisosSupport::CONFIGURAR);

        $defs = ConsultaInteligenteAlertaSupport::definicionesParametro();
        $valores = [];
        foreach (array_keys($defs) as $clave) {
            if (($defs[$clave]['tipo'] ?? '') === 'boolean') {
                $valores[$clave] = \App\Support\Configuracion\ParametroSistemaSupport::boolean($clave, true);
            } else {
                $valores[$clave] = (int) \App\Support\Configuracion\ParametroSistemaSupport::decimal(
                    $clave,
                    match ($clave) {
                        ConsultaInteligenteAlertaSupport::CLAVE_CHEQUES_DEPOSITAR_ALTA => 10.0,
                        ConsultaInteligenteAlertaSupport::CLAVE_CHEQUES_VENCEN_DIAS => 7.0,
                        ConsultaInteligenteAlertaSupport::CLAVE_FACTURAS_VAR_PCT => 25.0,
                        ConsultaInteligenteAlertaSupport::CLAVE_CHEQUES_SUMA_UMBRAL => 5000000.0,
                        ConsultaInteligenteAlertaSupport::CLAVE_FACTURAS_SUMA_VAR_PCT => 25.0,
                        ConsultaInteligenteAlertaSupport::CLAVE_COBRANZAS_VAR_PCT => 25.0,
                        ConsultaInteligenteAlertaSupport::CLAVE_VENTA_SIN_REMITO_ALTA => 5.0,
                        default => 0.0,
                    }
                );
            }
        }

        return view('direccion.consulta_inteligente.configuracion', [
            'definiciones' => $defs,
            'valores' => $valores,
        ]);
    }

    public function guardarConfiguracion(Request $request)
    {
        can(ConsultaInteligentePermisosSupport::CONFIGURAR);

        $payload = [];
        foreach (ConsultaInteligenteAlertaSupport::clavesParametro() as $clave) {
            $def = ConsultaInteligenteAlertaSupport::definicionesParametro()[$clave] ?? null;
            if (! $def) {
                continue;
            }
            if (($def['tipo'] ?? '') === 'boolean') {
                $payload[$clave] = $request->boolean($clave) ? '1' : '0';
            } else {
                $payload[$clave] = (string) max(0, (int) $request->input($clave, 0));
            }
        }
        \App\Support\Configuracion\ParametroSistemaSupport::guardar($payload);

        return redirect()
            ->route('configuracion_consulta_inteligente')
            ->with('mensaje', 'Umbrales de alertas actualizados.');
    }

    public function cruzar(Request $request)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        $entidadesDisponibles = EntidadSemanticaCatalogo::disponiblesParaUsuario();
        $claves = ConsultaInteligenteMultiEntidadSupport::normalizarClaves(
            (array) $request->input('entidades', [])
        );
        $filtroValor = trim((string) $request->input('filtro_valor', ''));
        $periodo = (string) $request->input('periodo', '');
        $consultado = $request->boolean('consultar') && $claves !== [];

        $resultado = $consultado
            ? ConsultaInteligenteMultiEntidadSupport::consultar($claves, $filtroValor, $periodo)
            : [
                'paneles' => [],
                'filtros' => [
                    'filtro_valor' => $filtroValor,
                    'periodo' => $periodo,
                    'entidades' => $claves,
                ],
                'periodos' => ConsultaInteligenteMultiEntidadSupport::periodosDisponibles(),
            ];

        if ($consultado) {
            ConsultaInteligenteAuditoriaSupport::registrar(
                ConsultaInteligenteAuditoriaSupport::ACCION_CRUZAR,
                implode(',', $claves),
                $filtroValor !== '' ? $filtroValor : null,
                ['periodo' => $periodo]
            );
        }

        return view('direccion.consulta_inteligente.cruzar', [
            'entidadesDisponibles' => $entidadesDisponibles,
            'consultado' => $consultado,
            'paneles' => $resultado['paneles'],
            'filtros' => $resultado['filtros'],
            'periodos' => $resultado['periodos'],
            'maxEntidades' => ConsultaInteligenteMultiEntidadSupport::MAX_ENTIDADES,
        ]);
    }

    public function pinTablero(Request $request, string $entidad)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        try {
            $ent = $this->consultaService->entidadAutorizada($entidad);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('consulta_inteligente')->with('mensaje_error', $e->getMessage());
        }

        $filtros = $this->filtrosDesdeRequestOJson($ent, $request);
        $filtros['consultar'] = 1;
        $titulo = trim((string) $request->input('titulo', $ent->nombre()));
        $tipo = (string) $request->input('tipo', 'atajo');
        if (! in_array($tipo, ['atajo', 'kpi', 'barras', 'linea', 'torta', 'aging'], true)) {
            $tipo = 'atajo';
        }
        if ($tipo === 'aging' && ! ConsultaInteligenteConsultaMetricasSupport::soportaAging($entidad)) {
            $tipo = 'kpi';
        }
        $compartir = $request->boolean('compartir');
        $empresaComp = (int) $request->input('compartido_empresa_id', 0);
        $medida = (string) $request->input('medida', 'conteo');
        if (! in_array($medida, ['conteo', 'suma', 'promedio'], true)) {
            $medida = 'conteo';
        }
        if (in_array($tipo, ['barras', 'linea', 'torta'], true)) {
            $filtros['_ci_medida'] = $medida === 'promedio' ? 'suma' : $medida;
        }
        if ($tipo === 'kpi') {
            $filtros['_ci_metrica'] = $medida;
        }
        $ok = ConsultaInteligenteTableroSupport::pin(
            $entidad,
            $titulo,
            $filtros,
            $tipo,
            null,
            $compartir,
            $empresaComp > 0 ? $empresaComp : null
        );

        return redirect()
            ->to(EntidadSemanticaCatalogo::urlExplorar($entidad, $filtros))
            ->with(
                $ok ? 'mensaje' : 'mensaje_error',
                $ok
                    ? ($compartir
                        ? 'Consulta fijada y compartida en el tablero.'
                        : 'Consulta agregada al tablero personal.')
                    : 'No se pudo agregar (máximo '.ConsultaInteligenteTableroSupport::MAX_WIDGETS.' widgets propios).'
            );
    }

    public function unpinTablero(string $widgetId)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);
        ConsultaInteligenteTableroSupport::unpin($widgetId);

        return redirect()
            ->route('tablero_consulta_inteligente')
            ->with('mensaje', 'Widget quitado del tablero.');
    }

    public function explorar(Request $request, string $entidad)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        try {
            $ctx = $this->consultaService->explorar($entidad, $request);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('consulta_inteligente')
                ->with('mensaje_error', $e->getMessage());
        }

        /** @var \App\Support\ConsultaInteligente\EntidadSemantica $ent */
        $ent = $ctx['entidad'];

        if (
            $ent instanceof \App\Support\ConsultaInteligente\EntidadSemanticaPuenteReporte
            && $request->boolean('abrir_reporte')
        ) {
            ConsultaInteligenteAuditoriaSupport::registrar(
                ConsultaInteligenteAuditoriaSupport::ACCION_EXPLORAR,
                $ent->clave(),
                'abrir_reporte'
            );

            return redirect()->to($ent->urlReporte($ctx['filtros']));
        }

        if ($ctx['consultado'] ?? false) {
            ConsultaInteligenteAuditoriaSupport::registrar(
                ConsultaInteligenteAuditoriaSupport::ACCION_EXPLORAR,
                $ent->clave(),
                null,
                ['vista_id' => $ctx['vistaActiva']->id ?? null]
            );
        }

        $envios = ListadoEnvioProgramado::query()
            ->where('usuario_id', (int) auth()->id())
            ->where('recurso', EntidadSemanticaCatalogo::recursoVista($ent->clave()))
            ->where('activo', true)
            ->orderByDesc('id')
            ->get();

        return view('direccion.consulta_inteligente.explorar', [
            'entidad' => $ent,
            'filtros' => $ctx['filtros'],
            'filtrosQuery' => $ctx['filtrosQuery'],
            'camposFiltro' => $ctx['camposFiltro'],
            'columnasVisibles' => $ctx['columnasVisibles'],
            'etiquetas' => $ctx['etiquetas'],
            'filas' => $ctx['filas'],
            'consultado' => $ctx['consultado'],
            'vistas' => $ctx['vistas'],
            'vistaActiva' => $ctx['vistaActiva'],
            'workbenchListo' => $ctx['workbenchListo'],
            'enviosProgramados' => $envios,
            'cruces' => ConsultaInteligenteJoinCatalogo::atajosDesde($ent->clave()),
            'grillaLayout' => $ctx['grillaLayout'] ?? [],
            'esPuenteReporte' => $ent instanceof \App\Support\ConsultaInteligente\EntidadSemanticaPuenteReporte,
            'soportaGraficoTemporal' => ConsultaInteligenteTableroSupport::soportaGraficoTemporal($ent->clave()),
            'campoMedidaSuma' => ConsultaInteligenteTableroSupport::campoMedidaSuma($ent->clave()),
            'soportaAging' => ConsultaInteligenteConsultaMetricasSupport::soportaAging($ent->clave()),
            'empresasPin' => app(\App\Repositories\Configuracion\EmpresaRepositoryInterface::class)->allFiltrado(),
            'ayudaPuente' => $ent instanceof \App\Support\ConsultaInteligente\EntidadSemanticaPuenteReporte
                ? $ent->ayudaPuente()
                : null,
            'urlReporte' => $ent instanceof \App\Support\ConsultaInteligente\EntidadSemanticaPuenteReporte
                ? $ent->urlReporte($ctx['filtros'])
                : null,
            'qbeEstructura' => $ctx['filtros']['qbe'] ?? [],
            'opsTexto' => [
                'contiene' => 'Contiene',
                'empieza' => 'Empieza con',
                'termina' => 'Termina con',
                'igual' => 'Igual a',
                'distinto' => 'Distinto de',
                'vacio' => 'Vacío',
            ],
            'opsEntero' => [
                'igual' => 'Igual a',
                'mayor' => 'Mayor que',
                'mayor_igual' => 'Mayor o igual',
                'menor' => 'Menor que',
                'menor_igual' => 'Menor o igual',
                'entre' => 'Entre',
                'vacio' => 'Vacío',
            ],
            'opsFecha' => ListadoQbeSupport::OPERADORES_FECHA,
            'opsDecimal' => ListadoQbeSupport::OPERADORES_DECIMAL,
            'opsBool' => [
                'igual' => 'Es',
                'vacio' => 'Sin dato',
            ],
            'urlNativa' => route($ent->rutaNativa()),
            'plantillas' => array_map(
                static function (array $p) use ($ent) {
                    $url = $ent instanceof \App\Support\ConsultaInteligente\EntidadSemanticaPuenteReporte
                        ? $ent->urlReporte($p['query'])
                        : EntidadSemanticaCatalogo::urlExplorar($ent->clave(), $p['query']);

                    return [
                        'titulo' => $p['titulo'],
                        'descripcion' => $p['descripcion'],
                        'url' => $url,
                    ];
                },
                $ent->plantillas()
            ),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $entidad = trim((string) $request->query('entidad', $request->input('entidad', '')));
        $formato = strtoupper($formato);
        if ($entidad === '' || ! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->route('consulta_inteligente');
        }

        try {
            $datos = $this->consultaService->datosExport($entidad, $request);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('consulta_inteligente')
                ->with('mensaje_error', $e->getMessage());
        }

        $ent = $datos['entidad'];
        $filas = $datos['filas'];
        $columnas = $datos['columnasVisibles'];
        $etiquetas = $datos['etiquetas'];

        ConsultaInteligenteAuditoriaSupport::registrar(
            ConsultaInteligenteAuditoriaSupport::ACCION_EXPORTAR,
            $ent->clave(),
            $formato,
            ['filas' => is_countable($filas) ? count($filas) : 0]
        );

        if ($formato === 'EXCEL') {
            return Excel::download(
                new ConsultaInteligenteListadoExport($ent, $filas, $columnas, $etiquetas),
                'consulta_inteligente_'.$ent->clave().'.xlsx'
            );
        }

        if ($formato === 'CSV') {
            return Excel::download(
                new ConsultaInteligenteListadoExport($ent, $filas, $columnas, $etiquetas),
                'consulta_inteligente_'.$ent->clave().'.csv',
                \Maatwebsite\Excel\Excel::CSV
            );
        }

        $matriz = [];
        foreach ($filas as $row) {
            $linea = [];
            foreach ($columnas as $key) {
                $linea[$key] = trim(html_entity_decode(strip_tags($ent->valorCelda($row, $key)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            $matriz[] = $linea;
        }

        $html = view('direccion.consulta_inteligente.listado', [
            'titulo' => 'Consulta Inteligente · '.$ent->nombre(),
            'subtitulo' => 'Exportación del explorador',
            'columnas' => $columnas,
            'etiquetas' => $etiquetas,
            'matriz' => $matriz,
            'totalFilas' => count($matriz),
            'logosCabecera' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas),
        ])->render();

        $ruta = storage_path('pdf/listados/consulta_inteligente_'.$ent->clave().'.pdf');
        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }
        DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
            'titulo_corto' => 'CI '.$ent->nombre(),
        ]);

        return response()->download($ruta);
    }

    public function guardarVista(Request $request, string $entidad)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        try {
            $vista = $this->consultaService->guardarVista($entidad, $request);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('consulta_inteligente')
                ->with('mensaje_error', $e->getMessage());
        }

        if (! $vista) {
            return redirect()
                ->route('explorar_consulta_inteligente', array_merge(
                    ['entidad' => $entidad, 'consultar' => 1],
                    $request->except(['_token', 'nombre', 'es_default', 'compartida'])
                ))
                ->with('mensaje_error', 'No se pudo guardar la vista. Revisá el nombre y que existan las tablas de workbench.');
        }

        return redirect()
            ->route('explorar_consulta_inteligente', [
                'entidad' => $entidad,
                'vista_id' => $vista->id,
                'consultar' => 1,
            ])
            ->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public function eliminarVista(Request $request, string $entidad, int $id)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        try {
            $ok = $this->consultaService->eliminarVista($entidad, $id);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('consulta_inteligente')
                ->with('mensaje_error', $e->getMessage());
        }

        return redirect()
            ->route('explorar_consulta_inteligente', ['entidad' => $entidad])
            ->with($ok ? 'mensaje' : 'mensaje_error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function unir(Request $request)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        $pares = ConsultaInteligenteUnirSupport::paresDisponibles();
        $desde = trim((string) $request->input('desde', ''));
        $hacia = trim((string) $request->input('hacia', ''));
        $consultado = $request->boolean('consultar') && $desde !== '' && $hacia !== '';
        $resultado = [
            'ok' => false,
            'columnas' => [],
            'filas' => [],
            'total' => 0,
        ];

        if ($consultado) {
            try {
                $entDesde = $this->consultaService->entidadAutorizada($desde);
            } catch (InvalidArgumentException $e) {
                return redirect()
                    ->route('unir_consulta_inteligente')
                    ->with('mensaje_error', $e->getMessage());
            }
            $filtrosOrigen = $entDesde->resolverFiltros($request);
            $filtrosOrigen['consultar'] = 1;
            $page = max(1, (int) $request->input('page', 1));
            $resultado = ConsultaInteligenteUnirSupport::unir($desde, $hacia, $filtrosOrigen, $page);
            ConsultaInteligenteAuditoriaSupport::registrar(
                ConsultaInteligenteAuditoriaSupport::ACCION_UNIR,
                $desde.'→'.$hacia,
                $resultado['ok'] ? null : ($resultado['mensaje'] ?? 'error'),
                ['total' => $resultado['total'] ?? 0]
            );
        }

        return view('direccion.consulta_inteligente.unir', [
            'pares' => $pares,
            'desde' => $desde,
            'hacia' => $hacia,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filtrosQuery' => $consultado
                ? array_merge(
                    isset($entDesde) ? $entDesde->paraQueryString($filtrosOrigen) : [],
                    ['desde' => $desde, 'hacia' => $hacia, 'consultar' => 1]
                )
                : ['desde' => $desde, 'hacia' => $hacia],
        ]);
    }

    public function exportarUnir(Request $request, string $formato)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $formato = strtoupper($formato);
        $desde = trim((string) $request->input('desde', ''));
        $hacia = trim((string) $request->input('hacia', ''));
        if ($desde === '' || $hacia === '' || ! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->route('unir_consulta_inteligente');
        }

        try {
            $entDesde = $this->consultaService->entidadAutorizada($desde);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('unir_consulta_inteligente')->with('mensaje_error', $e->getMessage());
        }

        $filtrosOrigen = $entDesde->resolverFiltros($request);
        $filtrosOrigen['consultar'] = 1;
        $resultado = ConsultaInteligenteUnirSupport::unir($desde, $hacia, $filtrosOrigen, 1);
        if (! ($resultado['ok'] ?? false)) {
            return redirect()
                ->route('unir_consulta_inteligente', ['desde' => $desde, 'hacia' => $hacia, 'consultar' => 1])
                ->with('mensaje_error', $resultado['mensaje'] ?? 'No se pudo exportar el join.');
        }

        $pack = ConsultaInteligenteUnirSupport::aMatrizExport($resultado);
        $titulo = 'Join · '.($resultado['desde']->nombre() ?? $desde).' → '.($resultado['hacia']->nombre() ?? $hacia);

        ConsultaInteligenteAuditoriaSupport::registrar(
            ConsultaInteligenteAuditoriaSupport::ACCION_EXPORTAR,
            $desde.'→'.$hacia,
            'unir '.$formato,
            ['filas' => count($pack['matriz'])]
        );

        if ($formato === 'EXCEL' || $formato === 'CSV') {
            $export = new ConsultaInteligenteMatrizExport(
                $titulo,
                $pack['columnas'],
                $pack['etiquetas'],
                $pack['matriz']
            );
            if ($formato === 'CSV') {
                return Excel::download($export, 'ci_unir_'.$desde.'_'.$hacia.'.csv', \Maatwebsite\Excel\Excel::CSV);
            }

            return Excel::download($export, 'ci_unir_'.$desde.'_'.$hacia.'.xlsx');
        }

        $html = view('direccion.consulta_inteligente.listado', [
            'titulo' => $titulo,
            'subtitulo' => 'Exportación del join unificado',
            'columnas' => $pack['columnas'],
            'etiquetas' => $pack['etiquetas'],
            'matriz' => $pack['matriz'],
            'totalFilas' => count($pack['matriz']),
            'logosCabecera' => [],
        ])->render();

        $ruta = storage_path('pdf/listados/consulta_inteligente_unir.pdf');
        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }
        DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
            'titulo_corto' => 'CI Join',
        ]);

        return response()->download($ruta);
    }

    public function diccionarioNl()
    {
        can(ConsultaInteligentePermisosSupport::CONFIGURAR);

        return view('direccion.consulta_inteligente.diccionario', [
            'sinonimos' => ConsultaInteligenteNlDiccionarioSupport::listarTodos(),
            'entidades' => EntidadSemanticaCatalogo::disponiblesParaUsuario(),
            'tablaLista' => \Illuminate\Support\Facades\Schema::hasTable('consulta_inteligente_nl_sinonimo'),
            'tieneMetrica' => ConsultaInteligenteNlMetricasDiccionarioSupport::tieneColumnaMetrica(),
            'metricas' => ConsultaInteligenteNlMetricasDiccionarioSupport::METRICAS,
            'metricasFijas' => [
                'aging' => ConsultaInteligenteNlMetricasDiccionarioSupport::frasesFijas('aging'),
                'suma' => ConsultaInteligenteNlMetricasDiccionarioSupport::frasesFijas('suma'),
                'promedio' => ConsultaInteligenteNlMetricasDiccionarioSupport::frasesFijas('promedio'),
                'conteo' => ConsultaInteligenteNlMetricasDiccionarioSupport::frasesFijas('conteo'),
            ],
        ]);
    }

    public function exportarPreguntaPdf(Request $request)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        $pregunta = trim((string) $request->input('pregunta', ''));
        if ($pregunta === '') {
            return redirect()->route('consulta_inteligente')->with('mensaje_error', 'Falta la pregunta para el PDF.');
        }

        $resultado = ConsultaInteligenteNlSupport::interpretar($pregunta);
        if (empty($resultado['ok']) || empty($resultado['entidad'])) {
            return redirect()
                ->route('consulta_inteligente', ['pregunta' => $pregunta])
                ->with('mensaje_error', $resultado['mensaje'] ?? 'No se pudo interpretar la pregunta.');
        }

        $resultado['_pregunta'] = $pregunta;
        $narrativa = null;
        try {
            $narrativa = ConsultaInteligenteConsultaMetricasSupport::narrativaDesdeNl($resultado);
        } catch (\Throwable) {
        }
        unset($resultado['_pregunta']);

        $entidadClave = (string) $resultado['entidad'];
        $ent = $this->consultaService->entidadAutorizada($entidadClave);
        $query = is_array($resultado['query'] ?? null) ? $resultado['query'] : [];
        $query['consultar'] = 1;

        $filas = [];
        $columnas = array_slice($ent->columnasVisibles(), 0, 8);
        $etiquetas = $ent->etiquetasColumnas();
        try {
            $req = Request::create('/', 'GET', $query);
            $filtros = $ent->resolverFiltros($req);
            $coleccion = $ent->consultar($filtros, false);
            $n = 0;
            foreach ($coleccion as $row) {
                $celda = [];
                foreach ($columnas as $col) {
                    $celda[$col] = $ent->valorCelda($row, $col);
                }
                $filas[] = $celda;
                $n++;
                if ($n >= 40) {
                    break;
                }
            }
        } catch (\Throwable) {
            $filas = [];
        }

        $html = view('direccion.consulta_inteligente.pdf_pregunta', [
            'pregunta' => $pregunta,
            'explicacion' => (string) ($resultado['explicacion'] ?? ''),
            'narrativa' => $narrativa,
            'entidadNombre' => $ent->nombre(),
            'qbeLegible' => is_array($resultado['qbe_legible'] ?? null) ? $resultado['qbe_legible'] : [],
            'columnas' => $columnas,
            'etiquetas' => $etiquetas,
            'filas' => $filas,
            'totalMostrado' => count($filas),
            'urlExplorar' => (string) ($resultado['url'] ?? ''),
            'logos' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect()),
            'generado' => now()->format('d/m/Y H:i'),
        ])->render();

        $dir = storage_path('pdf/listados');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $ruta = $dir.'/ci_pregunta_'.auth()->id().'_'.time().'.pdf';
        DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
            'titulo_corto' => 'CI pregunta',
        ]);

        return response()->download($ruta)->deleteFileAfterSend(true);
    }

    public function guardarDiccionarioNl(Request $request)
    {
        can(ConsultaInteligentePermisosSupport::CONFIGURAR);

        $id = (int) $request->input('id', 0);
        $row = ConsultaInteligenteNlDiccionarioSupport::guardar($id > 0 ? $id : null, [
            'frase' => (string) $request->input('frase', ''),
            'entidad' => (string) $request->input('entidad', ''),
            'prioridad' => (int) $request->input('prioridad', 100),
            'activo' => $request->boolean('activo', true),
            'metrica' => (string) $request->input('metrica', ''),
        ]);

        return redirect()
            ->route('diccionario_consulta_inteligente')
            ->with(
                $row ? 'mensaje' : 'mensaje_error',
                $row ? 'Sinónimo guardado.' : 'No se pudo guardar (revisá frase, entidad y que exista la tabla).'
            );
    }

    public function eliminarDiccionarioNl(int $id)
    {
        can(ConsultaInteligentePermisosSupport::CONFIGURAR);
        $ok = ConsultaInteligenteNlDiccionarioSupport::eliminar($id);

        return redirect()
            ->route('diccionario_consulta_inteligente')
            ->with($ok ? 'mensaje' : 'mensaje_error', $ok ? 'Sinónimo eliminado.' : 'No se pudo eliminar.');
    }

    public function snapshots()
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        return view('direccion.consulta_inteligente.snapshots', [
            'snapshots' => ConsultaInteligenteSnapshotSupport::listar(),
            'tablaLista' => \Illuminate\Support\Facades\Schema::hasTable('consulta_inteligente_snapshot'),
            'ttlHoras' => ConsultaInteligenteSnapshotSupport::TTL_HORAS,
            'maxFilas' => ConsultaInteligenteSnapshotSupport::MAX_FILAS,
        ]);
    }

    public function guardarSnapshot(Request $request, string $entidad)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        try {
            $entPre = $this->consultaService->entidadAutorizada($entidad);
            $filtrosSnap = $this->filtrosDesdeRequestOJson($entPre, $request);
            $reqSnap = Request::create('/', 'GET', array_merge($filtrosSnap, [
                'consultar' => 1,
                'entidad' => $entidad,
            ]));
            $reqSnap->setUserResolver(static fn () => $request->user());
            $datos = $this->consultaService->datosExport($entidad, $reqSnap);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('explorar_consulta_inteligente', ['entidad' => $entidad])
                ->with('mensaje_error', $e->getMessage());
        }

        $ent = $datos['entidad'];
        $filas = $datos['filas'];
        $columnas = $datos['columnasVisibles'];
        $etiquetas = $datos['etiquetas'];
        $matriz = [];
        foreach ($filas as $row) {
            $linea = [];
            foreach ($columnas as $key) {
                $linea[$key] = trim(html_entity_decode(strip_tags($ent->valorCelda($row, $key)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            $matriz[] = $linea;
        }

        $titulo = trim((string) $request->input('titulo', $ent->nombre()));
        $snap = ConsultaInteligenteSnapshotSupport::guardar(
            $ent->clave(),
            $titulo,
            $datos['filtrosQuery'] ?? [],
            array_map(static fn ($k) => $etiquetas[$k] ?? $k, $columnas),
            $matriz
        );
        ConsultaInteligenteAuditoriaSupport::registrar(
            ConsultaInteligenteAuditoriaSupport::ACCION_SNAPSHOT,
            $ent->clave(),
            $snap ? 'id:'.$snap->id : 'fallo',
            ['filas' => count($matriz)]
        );

        $redir = EntidadSemanticaCatalogo::urlExplorar($entidad, array_merge(
            $datos['filtrosQuery'] ?? [],
            ['consultar' => 1]
        ));

        return redirect()
            ->to($redir)
            ->with(
                $snap ? 'mensaje' : 'mensaje_error',
                $snap
                    ? 'Snapshot guardado ('.count($matriz).' filas, vence en '.ConsultaInteligenteSnapshotSupport::TTL_HORAS.' h).'
                    : 'No se pudo guardar el snapshot (¿tabla migrada?).'
            );
    }

    public function verSnapshot(int $id)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);
        $snap = ConsultaInteligenteSnapshotSupport::encontrar($id);
        if (! $snap) {
            return redirect()
                ->route('snapshots_consulta_inteligente')
                ->with('mensaje_error', 'Snapshot no encontrado o vencido.');
        }

        return view('direccion.consulta_inteligente.snapshot_ver', [
            'snap' => $snap,
            'columnas' => $snap->columnas_json ?? [],
            'filas' => $snap->filas_json ?? [],
        ]);
    }

    public function eliminarSnapshot(int $id)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);
        $ok = ConsultaInteligenteSnapshotSupport::eliminar($id);

        return redirect()
            ->route('snapshots_consulta_inteligente')
            ->with($ok ? 'mensaje' : 'mensaje_error', $ok ? 'Snapshot eliminado.' : 'No se pudo eliminar.');
    }

    public function compararSnapshots(Request $request)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        $idA = (int) $request->input('a', 0);
        $idB = (int) $request->input('b', 0);
        $snapshots = ConsultaInteligenteSnapshotSupport::listar();
        $resultado = null;
        if ($idA > 0 && $idB > 0 && $idA !== $idB) {
            $resultado = ConsultaInteligenteSnapshotCompareSupport::comparar($idA, $idB);
            ConsultaInteligenteAuditoriaSupport::registrar(
                ConsultaInteligenteAuditoriaSupport::ACCION_SNAPSHOT,
                null,
                'comparar '.$idA.' vs '.$idB,
                ['ok' => (bool) ($resultado['ok'] ?? false)]
            );
        }

        return view('direccion.consulta_inteligente.snapshots_comparar', [
            'snapshots' => $snapshots,
            'idA' => $idA,
            'idB' => $idB,
            'resultado' => $resultado,
            'tablaLista' => \Illuminate\Support\Facades\Schema::hasTable('consulta_inteligente_snapshot'),
        ]);
    }

    public function auditoria(Request $request)
    {
        can(ConsultaInteligentePermisosSupport::LISTAR);

        $puedeGlobal = ConsultaInteligentePermisosSupport::puedeAuditarGlobal();
        $soloMias = $puedeGlobal ? $request->boolean('solo_mias', true) : true;
        $filas = ConsultaInteligenteAuditoriaSupport::recientes(
            80,
            $soloMias ? (int) auth()->id() : null
        );

        return view('direccion.consulta_inteligente.auditoria', [
            'filas' => $filas,
            'soloMias' => $soloMias,
            'puedeGlobal' => $puedeGlobal,
            'tablaLista' => \Illuminate\Support\Facades\Schema::hasTable('consulta_inteligente_auditoria'),
        ]);
    }

    /**
     * Preferí filtros_json del form (conserva QBE anidado); si no, resolvé del request.
     *
     * @return array<string, mixed>
     */
    private function filtrosDesdeRequestOJson(
        \App\Support\ConsultaInteligente\EntidadSemantica $ent,
        Request $request
    ): array {
        $raw = $request->input('filtros_json');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                // Ya viene en formato paraQueryString (desde el explorador).
                return $decoded;
            }
        }

        return $ent->paraQueryString($ent->resolverFiltros($request));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function datosIndex(Request $request, array $extra = []): array
    {
        $modulo = trim((string) $request->input('modulo', $request->query('modulo', '')));
        $q = trim((string) $request->input('q', $request->query('q', '')));

        return array_merge([
            'catalogo' => $this->consultaService->catalogoParaVista(
                $modulo !== '' ? $modulo : null,
                $q !== '' ? $q : null
            ),
            'modulos' => $this->consultaService->modulosDisponibles(),
            'plantillas' => $this->consultaService->plantillasDestacadas(),
            'alertas' => ConsultaInteligenteAlertaSupport::paraUsuario(),
            'filtroModulo' => $modulo,
            'filtroTexto' => $q,
            'puedeConfigurar' => ConsultaInteligentePermisosSupport::puedeConfigurar(),
            'puedeAuditar' => ConsultaInteligentePermisosSupport::puedeAuditarGlobal(),
            'llmHabilitado' => ConsultaInteligenteNlLlmSupport::habilitado(),
            'llmAvisoPrivacidad' => ConsultaInteligenteNlLlmSupport::avisoPrivacidad(),
            'llmCadena' => ConsultaInteligenteNlLlmSupport::cadenaActivaNombres(),
            'nlFavoritos' => ConsultaInteligenteNlFavoritoSupport::listar(),
            'nlHistorial' => ConsultaInteligenteNlFavoritoSupport::historialReciente(8),
            'nlContexto' => ConsultaInteligenteNlContextoSupport::actual(),
            'nlTurnos' => ConsultaInteligenteNlContextoSupport::turnos(),
            'nlCadena' => ConsultaInteligenteNlContextoSupport::cadenaLegible(),
            'nlSugerencias' => ConsultaInteligenteNlSugerenciasSupport::chipsIniciales(6),
            'nlSugerenciasUrl' => route('sugerencias_consulta_inteligente'),
        ], $extra);
    }
}
