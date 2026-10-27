<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\CajeroCrown;
use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownTipoDeposito;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownAsignacionCatalogo;
use App\Support\Caja\TesoreriaCrownBovedaSupport;
use App\Support\Caja\TesoreriaCrownCierreSupport;
use App\Support\Caja\TesoreriaCrownJornadaListadoColumnas;
use App\Support\Caja\TesoreriaCrownJornadaListadoFiltros;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownMesaSupport;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Caja\TesoreriaCrownWorkbenchSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TesoreriaCrownJornadaController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-jornada');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $armado = $this->workbench($request, $sala ? (int) $sala->id : 0);
        if ($armado instanceof \Illuminate\Http\RedirectResponse) {
            return $armado;
        }
        $jornadas = $this->consulta($armado['filtros'])->paginate(15)->appends($armado['filtrosQuery']);

        return view('caja.tesoreria_crown.jornada.index', array_merge($armado, [
            'salas' => $salas,
            'sala' => $sala,
            'jornadas' => $jornadas,
            'tieneCriterios' => TesoreriaCrownJornadaListadoFiltros::tieneCriteriosAplicados($armado['filtros']),
            'abierta' => TesoreriaCrownJornada::query()
                ->where('sala_id', (int) ($armado['filtros']['sala_id'] ?? 0))
                ->where('estado', TesoreriaCrownJornada::ABIERTA)
                ->first(),
        ]));
    }

    public function previewWorkbench(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-jornada');
        $sala = TesoreriaCrownSalaSupport::pedida($request, TesoreriaCrownSalaSupport::salas());

        return TesoreriaCrownWorkbenchSupport::preview(
            $request,
            TesoreriaCrownJornadaListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, $sala ? (int) $sala->id : 0),
            TesoreriaCrownJornadaListadoColumnas::RECURSO,
            fn (array $filtros) => $this->consulta($filtros),
            [TesoreriaCrownJornadaListadoColumnas::class, 'valorCelda'],
            [TesoreriaCrownJornadaListadoFiltros::class, 'camposOrdenables'],
        );
    }

    public function guardarVistaListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-jornada');
        $salaId = (int) $request->input('sala_id', 0);

        return TesoreriaCrownWorkbenchSupport::guardarVista(
            $request,
            TesoreriaCrownJornadaListadoColumnas::RECURSO,
            'tesoreria_crown_jornada',
            TesoreriaCrownJornadaListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, $salaId),
            [TesoreriaCrownJornadaListadoFiltros::class, 'paraQueryString'],
        );
    }

    public function eliminarVistaListado(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-jornada');
        $ok = ListadoVistaSupport::eliminar($id, TesoreriaCrownJornadaListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('tesoreria_crown_jornada', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'mensaje-error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-jornada');
        $salaId = (int) $request->input('sala_id', 0);

        return TesoreriaCrownWorkbenchSupport::guardarColumnas(
            $request,
            TesoreriaCrownJornadaListadoColumnas::RECURSO,
            'tesoreria_crown_jornada',
            TesoreriaCrownJornadaListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, $salaId),
            [TesoreriaCrownJornadaListadoFiltros::class, 'paraQueryString'],
        );
    }

    public function guardarEtiquetasListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-jornada');
        $etiquetas = $request->input('etiquetas', []);
        ListadoColumnaEtiquetaSupport::guardar(
            TesoreriaCrownJornadaListadoColumnas::RECURSO,
            is_array($etiquetas) ? $etiquetas : [],
            array_keys(TesoreriaCrownJornadaListadoColumnas::catalogoActivo())
        );

        return redirect()->route('tesoreria_crown_jornada', $request->query())->with('mensaje', 'Etiquetas actualizadas.');
    }

    /**
     * @return array<string, mixed>|\Illuminate\Http\RedirectResponse
     */
    private function workbench(Request $request, int $salaId): array|\Illuminate\Http\RedirectResponse
    {
        return TesoreriaCrownWorkbenchSupport::preparar(
            $request,
            TesoreriaCrownJornadaListadoColumnas::RECURSO,
            'tesoreria_crown_jornada',
            TesoreriaCrownJornadaListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, $salaId),
            [TesoreriaCrownJornadaListadoFiltros::class, 'camposOrdenables'],
            [TesoreriaCrownJornadaListadoFiltros::class, 'camposQbe'],
            [TesoreriaCrownJornadaListadoFiltros::class, 'normalizarOperador'],
            [TesoreriaCrownJornadaListadoFiltros::class, 'paraQueryString'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosDe(Request $request, int $salaId): array
    {
        $filtros = TesoreriaCrownJornadaListadoFiltros::resolverDesdeRequest($request);
        $filtros['sala_id'] = $salaId;

        return $filtros;
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-jornada');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtros = TesoreriaCrownJornadaListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $filtros['sala_id'] = (int) ($sala->id ?? 0);
        $coleccion = $this->consulta($filtros)->get();
        foreach ($coleccion as $jornada) {
            $jornada->nombreempresa = $jornada->empresa->nombre ?? '';
        }

        $armado = [
            'titulo' => 'Jornadas de tesorería',
            'subtitulo' => $this->subtitulo($filtros, $sala),
            'archivo' => 'jornadas_tesoreria_crown',
            'titulo_corto' => 'Jornadas tesorería',
            'columnas' => [
                ['label' => 'Fecha', 'ancho' => 14, 'texto' => true],
                ['label' => 'Estado', 'ancho' => 14],
                ['label' => 'Asignaciones', 'ancho' => 16],
                ['label' => 'Movimientos', 'ancho' => 16],
                ['label' => 'Cajeros', 'ancho' => 60, 'wrap' => true],
            ],
            'filas' => $coleccion->map(function (TesoreriaCrownJornada $jornada) {
                return [
                    $jornada->fecha->format('d/m/Y'),
                    $jornada->estaAbierta() ? 'Abierta' : 'Cerrada',
                    (int) $jornada->asignaciones_count,
                    (int) $jornada->comprobantes_count,
                    $this->nombresCajeros($jornada),
                ];
            })->all(),
            'coleccion' => $coleccion,
        ];

        $respuesta = TesoreriaCrownListadoDocumento::responder((string) $formato, $armado);
        if ($respuesta) {
            return $respuesta;
        }

        return redirect()->route(
            'tesoreria_crown_jornada',
            TesoreriaCrownJornadaListadoFiltros::paraQueryString($filtros)
        );
    }

    public function ver(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-jornada');

        $jornada = $this->jornadaEnAlcance($id);
        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::exigida((int) $jornada->sala_id);
        $puedeMesas = can('listar-tesoreria-crown-mesa', false);
        $puedeBoveda = can('listar-tesoreria-crown-boveda', false);
        $panel = (string) $request->query('panel', 'cajeros');
        if (! in_array($panel, ['cajeros', 'mesas', 'boveda'], true)) {
            $panel = 'cajeros';
        }
        $tableroMesas = $puedeMesas
            ? TesoreriaCrownMesaSupport::tablero($jornada)
            : ['mesas' => [], 'movimientos' => collect()];
        $mesaCodigo = strtoupper(trim((string) $request->query('mesa', '')));
        if ($mesaCodigo !== '' && ctype_digit($mesaCodigo)) {
            $mesaCodigo = 'M'.$mesaCodigo;
        }
        $mesaFila = null;
        if ($puedeMesas && $mesaCodigo !== '') {
            $panel = 'mesas';
            $mesaFila = collect($tableroMesas['mesas'])->first(
                fn (array $fila) => strtoupper((string) $fila['mesa']->codigo) === $mesaCodigo
            );
        }
        if (($panel === 'mesas' && ! $puedeMesas) || ($panel === 'boveda' && ! $puedeBoveda)) {
            $panel = 'cajeros';
        }
        $fecha = $jornada->fecha->format('Y-m-d');
        $stock = $mesaFila
            ? TesoreriaCrownMesaSupport::stock((int) $mesaFila['mesa']->id, (int) $sala->id, $fecha)
            : null;

        return view('caja.tesoreria_crown.jornada.dia', [
            'salas' => $salas,
            'sala' => $sala,
            'jornada' => $jornada,
            'asignaciones' => $jornada->asignaciones()->with(['deposito', 'cajero', 'comprobantes', 'cierre'])->orderBy('turno')->orderBy('deposito_id')->get(),
            'recientes' => TesoreriaCrownJornada::query()
                ->where('sala_id', $sala->id)
                ->orderByDesc('fecha')
                ->limit(15)
                ->get(),
            'tareas' => TesoreriaCrownAsignacionCatalogo::TAREAS,
            'turnos' => TesoreriaCrownAsignacionCatalogo::TURNOS,
            'abierta' => TesoreriaCrownJornada::query()
                ->where('sala_id', $sala->id)
                ->where('estado', TesoreriaCrownJornada::ABIERTA)
                ->first(),
            'boveda' => $puedeBoveda ? TesoreriaCrownBovedaSupport::resumenJornada($jornada) : null,
            'puedeMesas' => $puedeMesas,
            'puedeBoveda' => $puedeBoveda,
            'panel' => $panel,
            'tableroMesas' => $tableroMesas,
            'mesaFila' => $mesaFila,
            'aperturaMesa' => $stock ? TesoreriaCrownMesaSupport::filas((int) $sala->id, $stock['apertura']) : [],
            'saldoMesa' => $stock ? TesoreriaCrownMesaSupport::filas((int) $sala->id, $stock['saldo']) : [],
            'estadoFinalMesa' => $stock ? TesoreriaCrownMesaSupport::filas((int) $sala->id, $stock['estado_final']) : [],
            'dropMesa' => $stock ? TesoreriaCrownMesaSupport::filas((int) $sala->id, $stock['drop']) : [],
        ]);
    }

    public function abrir(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-jornada');

        $sala = TesoreriaCrownSalaSupport::exigida((int) $request->input('sala_id'));
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $yaAbierta = TesoreriaCrownJornada::query()
            ->where('sala_id', $sala->id)
            ->where('estado', TesoreriaCrownJornada::ABIERTA)
            ->exists();
        if ($yaAbierta) {
            return back()->with('mensaje-error', 'Esta sala ya tiene una jornada abierta. Cerrala antes de abrir otra.');
        }

        $existe = TesoreriaCrownJornada::query()
            ->where('sala_id', $sala->id)
            ->whereDate('fecha', $datos['fecha'])
            ->exists();
        if ($existe) {
            return back()->with('mensaje-error', 'Esa fecha ya tiene jornada en esta sala.');
        }

        $jornada = TesoreriaCrownJornada::query()->create([
            'sala_id' => $sala->id,
            'empresa_id' => $sala->empresa_id,
            'fecha' => $datos['fecha'],
            'estado' => TesoreriaCrownJornada::ABIERTA,
        ]);

        return redirect()->route('ver_tesoreria_crown_jornada', $jornada->id)->with('mensaje', 'Jornada abierta.');
    }

    public function cerrar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-jornada');

        $jornada = $this->jornadaEnAlcance($id);
        if (! $jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'La jornada ya está cerrada.');
        }
        $sinCierre = $jornada->asignaciones()->with('cierre')->withCount('comprobantes')->get()
            ->contains(function (TesoreriaCrownAsignacion $fila) {
                if ((int) $fila->comprobantes_count === 0) {
                    return false;
                }

                return $fila->cierre === null || ! TesoreriaCrownCierreSupport::terminal((string) $fila->cierre->estado);
            });
        if ($sinCierre) {
            return back()->with('mensaje-error', 'Quedan cajas sin un cierre terminado (conforme, revisada o cierre de auditoría).');
        }
        $jornada->estado = TesoreriaCrownJornada::CERRADA;
        $jornada->save();

        return back()->with('mensaje', 'Jornada cerrada.');
    }

    public function eliminar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-jornada');

        $jornada = $this->jornadaEnAlcance($id);
        $salaId = (int) $jornada->sala_id;
        if ($jornada->comprobantes()->exists()) {
            return redirect()
                ->route('tesoreria_crown_jornada', ['sala_id' => $salaId])
                ->with('mensaje-error', 'La jornada tiene habilitaciones o rendiciones. No se puede borrar.');
        }

        DB::transaction(function () use ($jornada) {
            EloquentAuditDeleteSupport::each(
                TesoreriaCrownAsignacion::query()->where('jornada_id', $jornada->id)
            );
            $jornada->delete();
        });

        return redirect()
            ->route('tesoreria_crown_jornada', ['sala_id' => $salaId])
            ->with('mensaje', 'Jornada eliminada.');
    }

    public function asignar(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-jornada');

        $jornada = $this->jornadaEnAlcance($id);
        if (! $jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'No se asigna un cajero en una jornada cerrada.');
        }

        $datos = $request->validate([
            'deposito_id' => ['required', 'integer'],
            'cajero_crown_id' => ['required', 'integer'],
            'tipo_tarea' => ['required', Rule::in(array_keys(TesoreriaCrownAsignacionCatalogo::TAREAS))],
            'turno' => ['required', Rule::in(array_keys(TesoreriaCrownAsignacionCatalogo::TURNOS))],
        ]);

        $deposito = $this->cajaDeSala((int) $datos['deposito_id'], (int) $jornada->sala_id);
        if (! $deposito) {
            return back()->with('mensaje-error', 'La caja no es de esta sala.')->withInput();
        }
        $cajero = CajeroCrown::query()
            ->where('sala_id', $jornada->sala_id)
            ->where('activo', true)
            ->find((int) $datos['cajero_crown_id']);
        if (! $cajero) {
            return back()->with('mensaje-error', 'El cajero no es de esta sala.')->withInput();
        }

        $ocupada = TesoreriaCrownAsignacion::query()
            ->where('jornada_id', $jornada->id)
            ->where('deposito_id', $deposito->id)
            ->where('turno', $datos['turno'])
            ->exists();
        if ($ocupada) {
            return back()->with('mensaje-error', 'Esa caja ya tiene cajero en ese turno.')->withInput();
        }

        TesoreriaCrownAsignacion::query()->create([
            'sala_id' => $jornada->sala_id,
            'jornada_id' => $jornada->id,
            'deposito_id' => $deposito->id,
            'cajero_crown_id' => $cajero->id,
            'tipo_tarea' => $datos['tipo_tarea'],
            'turno' => $datos['turno'],
        ]);

        return redirect()->route('ver_tesoreria_crown_jornada', $jornada->id)->with('mensaje', 'Cajero asignado.');
    }

    public function quitar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-jornada');

        $asignacion = TesoreriaCrownAsignacion::query()->findOrFail($id);
        $jornada = $this->jornadaEnAlcance((int) $asignacion->jornada_id);
        if (! $jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'No se quita una asignación de una jornada cerrada.');
        }
        if ($asignacion->comprobantes()->exists() || $asignacion->cierre()->exists()) {
            return back()->with('mensaje-error', 'Esta caja ya tiene comprobantes. No se puede quitar.');
        }
        $asignacion->delete();

        return redirect()->route('ver_tesoreria_crown_jornada', $jornada->id)->with('mensaje', 'Asignación quitada.');
    }

    public function resolverDeposito(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-jornada');

        $sala = TesoreriaCrownSalaSupport::exigida((int) $request->input('sala_id'));
        $codigo = trim((string) $request->input('codigo', ''));
        $deposito = $this->cajasDeSala((int) $sala->id)->where('codigo', $codigo)->first();
        if (! $deposito) {
            return response()->json(['mensaje' => 'No hay una caja con ese código en esta sala.'], 404);
        }

        return response()->json($this->filaDeposito($deposito));
    }

    public function consultaDeposito(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-jornada');

        $sala = TesoreriaCrownSalaSupport::exigida((int) $request->input('sala_id'));
        $texto = trim((string) $request->input('q', ''));
        $query = $this->cajasDeSala((int) $sala->id)->orderBy('codigo');
        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('codigo', 'like', '%'.$texto.'%')->orWhere('nombre', 'like', '%'.$texto.'%');
            });
        }

        return response()->json([
            'filas' => $query->limit(30)->get()->map(fn (TesoreriaCrownDeposito $deposito) => $this->filaDeposito($deposito))->values(),
        ]);
    }

    public function resolverCajero(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-jornada');

        $sala = TesoreriaCrownSalaSupport::exigida((int) $request->input('sala_id'));
        $codigo = (int) $request->input('codigo', 0);
        $base = CajeroCrown::query()
            ->where('sala_id', $sala->id)
            ->where('activo', true);
        $cajero = (clone $base)->where('codigo', $codigo)->first();
        if (! $cajero) {
            $porLegajo = (clone $base)->where('legajo', $codigo)->orderBy('codigo')->get();
            if ($porLegajo->count() > 1) {
                return response()->json([
                    'mensaje' => 'Hay varios cajeros con el legajo '.$codigo.'. Elegí el código de cajero.',
                ], 409);
            }
            $cajero = $porLegajo->first();
        }
        if (! $cajero) {
            return response()->json(['mensaje' => 'No hay un cajero activo con ese código en esta sala.'], 404);
        }

        return response()->json($this->filaCajero($cajero));
    }

    public function consultaCajero(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-jornada');

        $sala = TesoreriaCrownSalaSupport::exigida((int) $request->input('sala_id'));
        $texto = trim((string) $request->input('q', ''));
        $query = CajeroCrown::query()
            ->where('sala_id', $sala->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->orderBy('codigo');
        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('nombre', 'like', '%'.$texto.'%');
                if (ctype_digit($texto)) {
                    $q->orWhere('codigo', (int) $texto)->orWhere('legajo', (int) $texto);
                }
            });
        }

        $filas = $query->get()->map(fn (CajeroCrown $cajero) => $this->filaCajero($cajero))->values();

        return response()->json([
            'total' => $filas->count(),
            'filas' => $filas,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function consulta(array $filtros)
    {
        $query = TesoreriaCrownJornada::query()
            ->with(['asignaciones.cajero', 'empresa'])
            ->withCount(['asignaciones', 'comprobantes']);
        TesoreriaCrownJornadaListadoFiltros::aplicar($query, $filtros);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function subtitulo(array $filtros, mixed $sala): string
    {
        $partes = [];
        if ($sala) {
            $partes[] = trim(($sala->codigo ?? '').' '.($sala->nombre ?? ''));
        }
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde !== '' || $hasta !== '') {
            $partes[] = 'Fecha '.($desde !== '' ? $this->fechaDma($desde) : '…').' a '.($hasta !== '' ? $this->fechaDma($hasta) : '…');
        }
        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $partes[] = $estado === TesoreriaCrownJornada::ABIERTA ? 'Abiertas' : 'Cerradas';
        }
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '') {
            $partes[] = 'Cajero: '.$valor;
        }

        return implode(' · ', array_filter($partes));
    }

    private function fechaDma(string $ymd): string
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $ymd);

        return $dt ? $dt->format('d/m/Y') : $ymd;
    }

    private function nombresCajeros(TesoreriaCrownJornada $jornada): string
    {
        return $jornada->asignaciones
            ->map(fn ($fila) => trim((string) ($fila->cajero->nombre ?? '')))
            ->filter()
            ->unique()
            ->implode(', ');
    }

    private function jornadaPedida(Request $request, int $salaId): ?TesoreriaCrownJornada
    {
        $id = (int) $request->input('jornada_id', 0);
        if ($id > 0) {
            $jornada = TesoreriaCrownJornada::query()->where('sala_id', $salaId)->find($id);
            if (! $jornada) {
                abort(404);
            }

            return $jornada;
        }

        return TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->orderByRaw("case when estado = 'abierta' then 0 else 1 end")
            ->orderByDesc('fecha')
            ->first();
    }

    private function jornadaEnAlcance(int $id): TesoreriaCrownJornada
    {
        $jornada = TesoreriaCrownJornada::query()->findOrFail($id);
        TesoreriaCrownSalaSupport::exigida((int) $jornada->sala_id);

        return $jornada;
    }

    private function cajasDeSala(int $salaId)
    {
        $tipoId = TesoreriaCrownTipoDeposito::query()
            ->where('sala_id', $salaId)
            ->where('codigo', 'CAJA')
            ->value('id');

        return TesoreriaCrownDeposito::query()
            ->where('sala_id', $salaId)
            ->where('tipo_deposito_id', (int) $tipoId)
            ->where('activo', true);
    }

    private function cajaDeSala(int $id, int $salaId): ?TesoreriaCrownDeposito
    {
        return $this->cajasDeSala($salaId)->where('id', $id)->first();
    }

    /**
     * @return array{id: int, codigo: string, nombre: string}
     */
    private function filaDeposito(TesoreriaCrownDeposito $deposito): array
    {
        return [
            'id' => (int) $deposito->id,
            'codigo' => (string) $deposito->codigo,
            'nombre' => (string) $deposito->nombre,
        ];
    }

    /**
     * @return array{id: int, codigo: string, nombre: string}
     */
    private function filaCajero(CajeroCrown $cajero): array
    {
        $legajo = (int) ($cajero->legajo ?? 0);

        return [
            'id' => (int) $cajero->id,
            'codigo' => (string) $cajero->codigo,
            'legajo' => $legajo > 0 ? (string) $legajo : '',
            'nombre' => (string) $cajero->nombre,
        ];
    }
}
