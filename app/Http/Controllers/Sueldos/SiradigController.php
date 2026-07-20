<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\SiradigListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Siradig_Presentacion_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Sueldos\SiradigImportacionService;
use App\Support\Sueldos\SiradigListadoFiltros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SiradigController extends Controller
{
    public function __construct(
        private SiradigImportacionService $importacionService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-siradig-sueldos');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SiradigListadoFiltros::resolverDesdeRequest($request, null, $empresaDefault ? (int) $empresaDefault : null);

        $datas = $this->baseQuery($filtros)->orderByDesc('periodo')
            ->orderBy('empleado_apellido')
            ->orderByDesc('nro_presentacion')
            ->paginate(15);

        return view('sueldos.siradig.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => SiradigListadoFiltros::paraQueryString($filtros),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'periodos' => $this->periodosDisponibles(),
            'puedeVer' => can('ver-siradig-sueldos', false),
            'puedeImportar' => can('importar-siradig-sueldos', false),
            'puedeBorrar' => can('borrar-siradig-sueldos', false),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-siradig-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SiradigListadoFiltros::resolverDesdeRequest($request, $busqueda, $empresaDefault ? (int) $empresaDefault : null);

        switch ($formato) {
            case 'PDF':
                $datas = $this->baseQuery($filtros)->with('conceptos')->orderByDesc('periodo')
                    ->orderBy('empleado_apellido')->get();
                $view = \View::make('sueldos.siradig.listado', compact('datas', 'filtros'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/listado_siradig.pdf');

                return response()->download($path.'/listado_siradig.pdf');

            case 'EXCEL':
                return app(SiradigListadoExport::class)->parametros($filtros)->download('siradig_f572.xlsx');

            case 'CSV':
                return app(SiradigListadoExport::class)->parametros($filtros)
                    ->download('siradig_f572.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_siradig_sueldos', SiradigListadoFiltros::paraQueryString($filtros));
    }

    public function importar(Request $request)
    {
        can('importar-siradig-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $request->validate([
            'empresa_id' => 'required|integer|exists:empresa,id',
            'archivo' => 'required|file|max:20480|mimes:xml,zip,txt',
        ], [], ['archivo' => 'archivo SiRADIG']);

        if (! $this->empresaRepository->allFiltrado()->pluck('id')->contains((int) $request->input('empresa_id'))) {
            return back()->with('error', 'No tiene acceso a la empresa seleccionada.');
        }

        $empresaId = (int) $request->input('empresa_id');
        $archivo = $request->file('archivo');
        $extension = strtolower((string) $archivo->getClientOriginalExtension());

        try {
            if ($extension === 'zip') {
                $resultado = $this->importacionService->importarZip($archivo->getRealPath(), $empresaId, Auth::id());
                $importadas = count($resultado['importadas']);
                $omitidas = count($resultado['omitidas']);
                $errores = $resultado['errores'];

                $mensaje = "Importación SiRADIG: {$importadas} presentación(es) importada(s), {$omitidas} omitida(s) por duplicado.";
                if ($errores !== []) {
                    $detalle = collect($errores)->map(fn ($m, $f) => "{$f}: {$m}")->take(5)->implode(' | ');

                    return redirect()->route('consultar_siradig_sueldos')
                        ->with('mensaje', $mensaje)
                        ->with('error', 'Con errores en: '.$detalle);
                }

                return redirect()->route('consultar_siradig_sueldos')->with('mensaje', $mensaje);
            }

            $contenido = (string) file_get_contents($archivo->getRealPath());
            $presentacion = $this->importacionService->importarXml(
                $contenido,
                $empresaId,
                $archivo->getClientOriginalName(),
                Auth::id()
            );

            if ($presentacion === null) {
                return redirect()->route('consultar_siradig_sueldos')
                    ->with('mensaje', 'El archivo ya había sido importado (mismo contenido). No se generaron duplicados.');
            }

            return redirect()->route('ver_siradig_sueldos', ['id' => $presentacion->id])
                ->with('mensaje', 'F572 importado: '.$presentacion->empleado_apellido.' '.$presentacion->empleado_nombre
                    .' — período '.$presentacion->periodo.' (sección '.$presentacion->seccion.').');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo importar el archivo: '.$e->getMessage());
        }
    }

    public function ver($id)
    {
        can('ver-siradig-sueldos');

        $presentacion = Siradig_Presentacion_Sueldos::query()
            ->with([
                'empresa',
                'empleado',
                'cargasFamilia',
                'otrosEmpleadores.meses.detalles',
                'conceptos.periodos',
                'conceptos.detalles',
                'datosAdicionales',
            ])
            ->findOrFail($id);

        if (! empty($presentacion->empresa_id)
            && ! $this->empresaRepository->allFiltrado()->pluck('id')->contains((int) $presentacion->empresa_id)) {
            abort(403, 'No tiene acceso a la empresa de esta presentación.');
        }

        return view('sueldos.siradig.ver', [
            'p' => $presentacion,
            'puedeBorrar' => can('borrar-siradig-sueldos', false),
        ]);
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-siradig-sueldos');
        $presentacion = Siradig_Presentacion_Sueldos::findOrFail($id);

        $empresaId = (int) $presentacion->empresa_id;
        $cuit = (string) $presentacion->empleado_cuit;
        $periodo = (int) $presentacion->periodo;
        $seccion = (string) $presentacion->seccion;

        DB::transaction(function () use ($presentacion, $empresaId, $cuit, $periodo, $seccion) {
            $presentacion->delete();
            $this->importacionService->recalcularVigenciaClaves($empresaId, $cuit, $periodo, $seccion);
        });

        if ($request->ajax()) {
            return response()->json(['mensaje' => 'ok']);
        }

        return redirect()->route('consultar_siradig_sueldos')
            ->with('mensaje', 'Presentación eliminada.');
    }

    /**
     * Panel SiRADIG embebido en la solapa del CRUD de empleados (carga AJAX).
     */
    public function panelEmpleado(Request $request, $empleado)
    {
        can('listar-siradig-sueldos');
        $emp = Empleado_Sueldos::findOrFail($empleado);

        $cuit = preg_replace('/\D+/', '', (string) $emp->cuil) ?? '';

        $presentaciones = Siradig_Presentacion_Sueldos::query()
            ->with(['cargasFamilia', 'conceptos.periodos', 'otrosEmpleadores'])
            ->where('empresa_id', $emp->empresa_id)
            ->where(function ($q) use ($emp, $cuit) {
                $q->where('empleado_id', $emp->id);
                if ($cuit !== '') {
                    $q->orWhere('empleado_cuit', $cuit);
                }
            })
            ->orderByDesc('periodo')
            ->orderBy('seccion')
            ->orderByDesc('nro_presentacion')
            ->get();

        $html = view('sueldos.empleado.partials.siradig', [
            'empleado' => $emp,
            'presentaciones' => $presentaciones,
            'puedeVer' => can('ver-siradig-sueldos', false),
        ])->render();

        return response()->json(['html' => $html]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Database\Eloquent\Builder<Siradig_Presentacion_Sueldos>
     */
    private function baseQuery(array $filtros)
    {
        $query = Siradig_Presentacion_Sueldos::query()->with(['empresa', 'empleado']);

        if (($filtros['empresa_scope'] ?? 'una') === 'todas' || empty($filtros['empresa_id'])) {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'siradig_presentacion_sueldos.empresa_id');
        }

        SiradigListadoFiltros::aplicar($query, $filtros);

        return $query;
    }

    /**
     * @return list<int>
     */
    private function periodosDisponibles(): array
    {
        $periodos = Siradig_Presentacion_Sueldos::query()
            ->select('periodo')->distinct()->orderByDesc('periodo')->pluck('periodo')
            ->map(fn ($p) => (int) $p)->all();

        $anio = (int) date('Y');
        if (! in_array($anio, $periodos, true)) {
            array_unshift($periodos, $anio);
        }

        return $periodos;
    }
}
