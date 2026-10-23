<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\NovedadSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionNovedad_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sueldos\Novedad_SueldosRepositoryInterface;
use App\Services\Sueldos\NovedadSueldosImportService;
use App\Support\Sueldos\NovedadSueldosCatalogo;
use App\Support\Sueldos\NovedadSueldosImportColumnasSupport;
use App\Support\Sueldos\NovedadSueldosListadoFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class Novedad_SueldosController extends Controller
{
    public function __construct(
        private Novedad_SueldosRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
        private NovedadSueldosImportService $importService,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-novedad-sueldos');

        $filtros = NovedadSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeNovedad($filtros, true);

        return view('sueldos.novedad.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => NovedadSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => NovedadSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-novedad-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = NovedadSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeNovedad($filtros, false);
                $view = \View::make('sueldos.novedad.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_novedad_sueldos';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(NovedadSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('novedad_sueldos.xlsx');

            case 'CSV':
                return app(NovedadSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('novedad_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_novedad_sueldos', NovedadSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-novedad-sueldos');

        return view('sueldos.novedad.crear', $this->datosFormulario($request));
    }

    public function guardar(ValidacionNovedad_Sueldos $request)
    {
        can('crear-novedad-sueldos');
        $data = $request->validated();
        $data['origen'] = $data['origen'] ?? NovedadSueldosCatalogo::ORIGEN_MANUAL;
        $this->repository->create($data);

        if ($request->filled('retorno_liquidacion_id')) {
            return redirect()
                ->route('novedades_liquidacion_sueldos', ['id' => (int) $request->input('retorno_liquidacion_id')])
                ->with('mensaje', 'Novedad creada con éxito');
        }

        return redirect('sueldos/novedad')->with('mensaje', 'Novedad creada con éxito');
    }

    public function editar($id)
    {
        can('editar-novedad-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.novedad.editar', array_merge(
            $this->datosFormulario(request(), $data),
            compact('data')
        ));
    }

    public function actualizar(ValidacionNovedad_Sueldos $request, $id)
    {
        can('actualizar-novedad-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/novedad')->with('mensaje', 'Novedad actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-novedad-sueldos');

        if ($request->ajax()) {
            return response()->json(['mensaje' => $this->repository->delete($id) ? 'ok' : 'ng']);
        }
        abort(404);
    }

    public function sincronizarAnita(Request $request)
    {
        can('crear-novedad-sueldos');

        $r = $this->repository->sincronizarConAnita();
        $msg = 'Anita: '.$r['en_anita']
            .' · Importadas: '.$r['importados']
            .' · Actualizadas: '.($r['actualizados'] ?? 0)
            .' · Eliminadas: '.($r['eliminados'] ?? 0)
            .' · Omitidas: '.$r['omitidos'];
        if ($r['errores'] !== []) {
            $msg .= ' · Errores: '.implode('; ', array_slice($r['errores'], 0, 3));
        }

        return redirect()->route('consultar_novedad_sueldos')->with('mensaje', $msg);
    }

    public function importarForm()
    {
        can('crear-novedad-sueldos');

        return view('sueldos.novedad.importar', [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function plantillaImportacion()
    {
        can('crear-novedad-sueldos');

        $csv = "\xEF\xBB\xBF".implode(';', NovedadSueldosImportColumnasSupport::campos())."\r\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_novedades_sueldos.csv"',
        ]);
    }

    public function previewImportacion(Request $request)
    {
        can('crear-novedad-sueldos');

        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'empresa_id' => 'nullable|integer',
            'fila_encabezado' => 'nullable|integer|min:1|max:50',
            'hoja_indice' => 'nullable|integer|min:1|max:50',
            'mapping' => 'nullable|array',
        ]);

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '120');

        $empresaId = (int) $request->input('empresa_id', 0);

        return response()->json($this->importService->preview(
            $request->file('archivo'),
            is_array($request->input('mapping')) ? $request->input('mapping') : [],
            $empresaId > 0 ? $empresaId : null,
            $request->filled('hoja_indice') ? (int) $request->input('hoja_indice') : null,
            $request->filled('fila_encabezado') ? (int) $request->input('fila_encabezado') : null
        ));
    }

    public function importar(Request $request)
    {
        can('crear-novedad-sueldos');

        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'empresa_id' => 'nullable|integer',
            'fila_encabezado' => 'nullable|integer|min:1|max:50',
            'hoja_indice' => 'nullable|integer|min:1|max:50',
            'mapping' => 'nullable|array',
        ]);

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '300');

        $empresaId = (int) $request->input('empresa_id', 0);
        $r = $this->importService->import(
            $request->file('archivo'),
            is_array($request->input('mapping')) ? $request->input('mapping') : [],
            $empresaId > 0 ? $empresaId : null,
            $request->filled('hoja_indice') ? (int) $request->input('hoja_indice') : null,
            $request->filled('fila_encabezado') ? (int) $request->input('fila_encabezado') : null
        );

        $msg = 'Importadas: '.$r['importados'].' · Omitidas: '.$r['omitidos'].' · Con error: '.$r['errores'];
        if ($r['detalle_errores'] !== []) {
            $primero = $r['detalle_errores'][0];
            $msg .= ' (fila '.$primero['fila'].': '.$primero['mensaje'].')';
        }

        return redirect()->route('consultar_novedad_sueldos')->with('mensaje', $msg);
    }

    public function liquidacion(Request $request, $id)
    {
        can('listar-novedad-sueldos');
        $liquidacion = Liquidacion_Sueldos::with('empresa')->findOrFail($id);

        $filtros = NovedadSueldosListadoFiltros::resolverDesdeRequest($request);
        $filtros['liquidacion_id'] = (int) $liquidacion->id;
        $datas = $this->repository->leeNovedad($filtros, true);

        return view('sueldos.novedad.liquidacion', [
            'liquidacion' => $liquidacion,
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => NovedadSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => NovedadSueldosListadoFiltros::CAMPOS,
            'puedeCrear' => can('crear-novedad-sueldos', false),
        ]);
    }

    public function empleadosPorEmpresa(Request $request)
    {
        can('listar-novedad-sueldos');
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0) {
            return response()->json([]);
        }

        $items = Empleado_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->orderBy('legajo')
            ->limit(500)
            ->get(['id', 'legajo', 'nombre'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'texto' => $e->legajo.' — '.$e->nombre,
            ]);

        return response()->json($items);
    }

    public function liquidacionesPorEmpresa(Request $request)
    {
        can('listar-novedad-sueldos');
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0) {
            return response()->json([]);
        }

        $items = Liquidacion_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->orderByDesc('periodo')
            ->orderByDesc('numero')
            ->limit(100)
            ->get(['id', 'numero', 'descripcion', 'periodo', 'estado'])
            ->map(fn ($l) => [
                'id' => $l->id,
                'texto' => 'N° '.$l->numero.' · '.$l->periodo.' · '.$l->descripcion.' ('.$l->estado.')',
            ]);

        return response()->json($items);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request, $data = null): array
    {
        $empresas = $this->empresaRepository->allFiltrado();
        $preLiquidacionId = (int) $request->input('liquidacion_id', $data->liquidacion_id ?? 0);
        $liquidacion = $preLiquidacionId > 0
            ? Liquidacion_Sueldos::with('empresa')->find($preLiquidacionId)
            : null;

        $empresaId = (int) old('empresa_id', $data->empresa_id ?? ($liquidacion->empresa_id ?? 0));
        $liquidaciones = $empresaId > 0
            ? Liquidacion_Sueldos::query()->where('empresa_id', $empresaId)->orderByDesc('numero')->limit(100)->get(['id', 'numero', 'descripcion', 'periodo', 'estado'])
            : collect();

        $empleadoId = (int) old('empleado_id', $data->empleado_id ?? 0);
        $empleadoSeleccionado = null;
        if ($empleadoId > 0) {
            if ($data !== null && (int) $data->empleado_id === $empleadoId) {
                $empleadoSeleccionado = $data->relationLoaded('empleado')
                    ? $data->empleado
                    : $data->empleado()->first();
            }
            if ($empleadoSeleccionado === null) {
                $empleadoSeleccionado = Empleado_Sueldos::query()->find($empleadoId);
            }
        }

        return [
            'empresas' => $empresas,
            'empleadoSeleccionado' => $empleadoSeleccionado,
            'liquidaciones' => $liquidaciones,
            'liquidacionPrefill' => $liquidacion,
            'estados' => NovedadSueldosCatalogo::ESTADOS,
            'origenes' => NovedadSueldosCatalogo::ORIGENES,
        ];
    }
}
