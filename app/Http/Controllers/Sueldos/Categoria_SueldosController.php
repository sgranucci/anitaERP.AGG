<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\CategoriaSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCategoria_Sueldos;
use App\Models\Sueldos\Categoria_Base_Sueldos;
use App\Models\Sueldos\Nombrebase_Sueldos;
use App\Repositories\Sueldos\Categoria_SueldosRepositoryInterface;
use App\Services\Sueldos\CategoriaBaseSueldosService;
use App\Support\Sueldos\CategoriaOrigenBases;
use App\Support\Sueldos\CategoriaSueldosListadoFiltros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Categoria_SueldosController extends Controller
{
    private Categoria_SueldosRepositoryInterface $repository;

    private CategoriaBaseSueldosService $baseService;

    public function __construct(
        Categoria_SueldosRepositoryInterface $repository,
        CategoriaBaseSueldosService $baseService
    ) {
        $this->repository = $repository;
        $this->baseService = $baseService;
    }

    public function index(Request $request)
    {
        can('listar-categoria-sueldos');

        $filtros = CategoriaSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeCategoria($filtros, true);
        $this->adjuntarBasesVigentes($datas);

        return view('sueldos.categoria.index', [
            'datas' => $datas,
            'origenLabels' => CategoriaOrigenBases::LABELS,
            'filtros' => $filtros,
            'filtrosQuery' => CategoriaSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => CategoriaSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-categoria-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = CategoriaSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $origenLabels = CategoriaOrigenBases::LABELS;

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeCategoria($filtros, false);
                $this->adjuntarBasesVigentes($datas);

                $view = \View::make('sueldos.categoria.listado', compact('datas', 'origenLabels'))
                    ->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_categoria_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new CategoriaSueldosListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('categoria_sueldos.xlsx');

            case 'CSV':
                return (new CategoriaSueldosListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('categoria_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_categoria_sueldos', CategoriaSueldosListadoFiltros::paraQueryString($filtros));
    }

    /**
     * Adjunta a cada categoría la lista de bases vigentes (consulta rápida en el listado).
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection|iterable  $datas
     */
    private function adjuntarBasesVigentes($datas): void
    {
        $items = method_exists($datas, 'items') ? $datas->items() : $datas;
        $ids = [];
        foreach ($items as $d) {
            $ids[] = (int) $d->id;
        }

        $basesPorCategoria = $this->baseService->basesVigentesParaCategorias($ids);

        foreach ($items as $d) {
            $d->bases_vigentes = $basesPorCategoria[(int) $d->id] ?? [];
        }
    }

    public function crear()
    {
        can('crear-categoria-sueldos');

        return view('sueldos.categoria.crear', [
            'origenLabels' => CategoriaOrigenBases::LABELS,
        ]);
    }

    public function guardar(ValidacionCategoria_Sueldos $request)
    {
        can('crear-categoria-sueldos');
        $categoria = $this->repository->create($request->validated());

        return redirect()->route('editar_categoria_sueldos', ['id' => $categoria->id])
            ->with('mensaje', 'Categoría creada con éxito. Cargue las bases de cálculo en la solapa correspondiente.');
    }

    public function editar($id)
    {
        can('editar-categoria-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.categoria.editar', [
            'data' => $data,
            'origenLabels' => CategoriaOrigenBases::LABELS,
            'basesGrilla' => $this->baseService->resumenBasesGrilla((int) $data->id),
            'nombrebases' => Nombrebase_Sueldos::query()->orderBy('codigo')->get(),
            'usaTabla' => CategoriaOrigenBases::usaTablaCategoria($data->origen_bases),
            'puedeBorrarVigencia' => can('borrar-vigencia-categoria-sueldos', false),
        ]);
    }

    public function actualizar(ValidacionCategoria_Sueldos $request, $id)
    {
        can('actualizar-categoria-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect()->route('consultar_categoria_sueldos')
            ->with('mensaje', 'Categoría actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-categoria-sueldos');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function sincronizarAnita(Request $request)
    {
        can('actualizar-categoria-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $r = $this->repository->sincronizarConAnita();

        if (! empty($r['errores'])) {
            return redirect('sueldos/categoria')
                ->with('error', 'No se pudo sincronizar con Anita: '.implode(' | ', $r['errores']));
        }

        return redirect('sueldos/categoria')->with(
            'mensaje',
            'Sincronización con Anita: '.$r['importados'].' categorías nuevas, '.$r['omitidos'].' ya existentes, '
                .$r['bases_importadas'].' bases importadas (de '.$r['en_anita'].' en Anita).'
        );
    }

    // --- Gestión de bases de cálculo (AJAX) ---

    public function guardarBase(Request $request, $id)
    {
        can('actualizar-categoria-sueldos');
        $categoria = $this->repository->findOrFail($id);

        $datos = $request->validate([
            'nombrebase_id' => 'required|integer|exists:nombrebase_sueldos,id',
            'valor' => 'required|numeric',
            'fecha_vigencia' => 'required|date',
        ]);

        $res = $this->baseService->guardarBase(
            (int) $categoria->id,
            (int) $datos['nombrebase_id'],
            (float) $datos['valor'],
            (string) $datos['fecha_vigencia'],
            Auth::id()
        );

        $fecha = \Carbon\Carbon::parse($datos['fecha_vigencia']);
        $esFutura = $fecha->toDateString() > \Carbon\Carbon::today()->toDateString();

        return response()->json([
            'mensaje' => 'ok',
            'creo_version' => $res['creo_version'],
            'es_futura' => $esFutura,
            'fecha_fmt' => $fecha->format('d/m/Y'),
            'nombrebase_id' => (int) $datos['nombrebase_id'],
            'historial' => $this->baseService->historial((int) $categoria->id, (int) $datos['nombrebase_id']),
            'bases' => $this->baseService->resumenBasesGrilla((int) $categoria->id),
        ]);
    }

    public function guardarVigenciasLote(Request $request, $id)
    {
        can('actualizar-categoria-sueldos');
        $categoria = $this->repository->findOrFail($id);

        $datos = $request->validate([
            'nombrebase_id' => 'required|integer|exists:nombrebase_sueldos,id',
            'items' => 'array',
            'items.*.id' => 'nullable|integer',
            'items.*.valor' => 'required_with:items|numeric',
            'items.*.fecha' => 'required_with:items|date',
            'eliminar' => 'array',
            'eliminar.*' => 'integer',
        ]);

        $eliminar = $datos['eliminar'] ?? [];
        if ($eliminar !== [] && ! can('borrar-vigencia-categoria-sueldos', false)) {
            return response()->json(['mensaje' => 'ng', 'error' => 'No tiene permiso para eliminar vigencias.'], 403);
        }

        $res = $this->baseService->guardarVigenciasLote(
            (int) $categoria->id,
            (int) $datos['nombrebase_id'],
            $datos['items'] ?? [],
            $eliminar,
            Auth::id()
        );

        if (empty($res['ok'])) {
            return response()->json(['mensaje' => 'ng', 'error' => $res['error'] ?? 'No se pudo guardar.'], 422);
        }

        return response()->json([
            'mensaje' => 'ok',
            'nombrebase_id' => (int) $datos['nombrebase_id'],
            'historial' => $this->baseService->historial((int) $categoria->id, (int) $datos['nombrebase_id']),
            'bases' => $this->baseService->resumenBasesGrilla((int) $categoria->id),
        ]);
    }

    public function actualizarVigencia(Request $request, $id, $baseId)
    {
        can('actualizar-categoria-sueldos');
        $categoria = $this->repository->findOrFail($id);

        $datos = $request->validate([
            'valor' => 'required|numeric',
            'fecha_vigencia' => 'required|date',
        ]);

        $base = Categoria_Base_Sueldos::query()
            ->where('id', $baseId)
            ->where('categoria_id', $categoria->id)
            ->first();
        if ($base === null) {
            return response()->json(['mensaje' => 'ng', 'error' => 'Vigencia no encontrada.'], 404);
        }

        $res = $this->baseService->actualizarVigencia(
            (int) $baseId,
            (float) $datos['valor'],
            (string) $datos['fecha_vigencia'],
            Auth::id()
        );

        if (empty($res['ok'])) {
            $msg = ($res['error'] ?? '') === 'fecha_duplicada'
                ? 'Ya existe una vigencia con esa fecha para esta base.'
                : 'No se pudo actualizar la vigencia.';

            return response()->json(['mensaje' => 'ng', 'error' => $msg], 422);
        }

        return response()->json([
            'mensaje' => 'ok',
            'historial' => $this->baseService->historial((int) $categoria->id, (int) $base->nombrebase_id),
            'bases' => $this->baseService->resumenBasesGrilla((int) $categoria->id),
        ]);
    }

    public function eliminarBase(Request $request, $id, $baseId)
    {
        can('borrar-vigencia-categoria-sueldos');
        $categoria = $this->repository->findOrFail($id);

        $base = Categoria_Base_Sueldos::query()
            ->where('id', $baseId)
            ->where('categoria_id', $categoria->id)
            ->first();
        $nombrebaseId = $base ? (int) $base->nombrebase_id : 0;

        $ok = $this->baseService->eliminarBase((int) $baseId);

        return response()->json([
            'mensaje' => $ok ? 'ok' : 'ng',
            'nombrebase_id' => $nombrebaseId,
            'historial' => $nombrebaseId > 0
                ? $this->baseService->historial((int) $categoria->id, $nombrebaseId)
                : [],
            'bases' => $this->baseService->resumenBasesGrilla((int) $categoria->id),
        ]);
    }

    public function eliminarBaseCompleta(Request $request, $id, $nombrebaseId)
    {
        can('borrar-vigencia-categoria-sueldos');
        $categoria = $this->repository->findOrFail($id);

        $borradas = $this->baseService->eliminarBaseCompleta((int) $categoria->id, (int) $nombrebaseId);

        return response()->json([
            'mensaje' => $borradas > 0 ? 'ok' : 'ng',
            'borradas' => $borradas,
            'bases' => $this->baseService->resumenBasesGrilla((int) $categoria->id),
        ]);
    }

    public function bases(Request $request, $id)
    {
        can('editar-categoria-sueldos');
        $categoria = $this->repository->findOrFail($id);

        return response()->json([
            'bases' => $this->baseService->resumenBasesGrilla((int) $categoria->id),
        ]);
    }

    public function historialBases(Request $request, $id)
    {
        can('editar-categoria-sueldos');
        $categoria = $this->repository->findOrFail($id);
        $nombrebaseId = (int) $request->query('nombrebase_id', 0);

        return response()->json([
            'historial' => $this->baseService->historial(
                (int) $categoria->id,
                $nombrebaseId > 0 ? $nombrebaseId : null
            ),
        ]);
    }
}
