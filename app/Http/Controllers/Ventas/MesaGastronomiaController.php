<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\MesaGastronomiaListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMesaGastronomia;
use App\Models\Configuracion\Sala;
use App\Models\Ventas\MesaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\MesaGastronomiaRepositoryInterface;
use App\Repositories\Ventas\UbicacionGastronomiaRepositoryInterface;
use App\Services\Ventas\MesaGastronomiaAnitaSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Ventas\MesaGastronomiaListadoFiltros;
use App\Support\Ventas\UsuarioSalaAsignadaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MesaGastronomiaController extends Controller
{
    public function __construct(
        private MesaGastronomiaRepositoryInterface $repository,
        private MesaGastronomiaAnitaSyncService $mesaGastronomiaAnitaSyncService,
        private UbicacionGastronomiaRepositoryInterface $ubicacionGastronomiaRepository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-mesa-gastronomia');

        if (filter_var(env('MESA_GASTRONOMIA_SYNC_ANITA_INDEX', false), FILTER_VALIDATE_BOOLEAN)
            && ! $this->repository->existeRegistro()) {
            $this->mesaGastronomiaAnitaSyncService->sincronizarConAnita();
        }

        $filtros = MesaGastronomiaListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = MesaGastronomiaListadoFiltros::paraQueryString($filtros);
        $datas = $this->repository->leeMesaGastronomia($filtros, true);
        if ($datas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $datas->appends($filtrosQuery);
        }
        $esCrown = EntornoEmpresaSupport::esCrown();
        $sala_query = $esCrown ? $this->salasParaFiltro() : collect();

        return view('ventas.mesa_gastronomia.index', [
            'datas' => $datas,
            'esCrown' => $esCrown,
            'sala_query' => $sala_query,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => MesaGastronomiaListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-mesa-gastronomia');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = MesaGastronomiaListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $esCrown = EntornoEmpresaSupport::esCrown();

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeMesaGastronomia($filtros, false);
                $view = \View::make('ventas.mesa_gastronomia.listado', compact('datas', 'esCrown', 'filtros'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombre_pdf = 'listado_mesa_gastronomia';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new MesaGastronomiaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('mesa_gastronomia.xlsx');

            case 'CSV':
                return (new MesaGastronomiaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('mesa_gastronomia.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_mesa_gastronomia', MesaGastronomiaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-mesa-gastronomia');
        $data = new MesaGastronomia();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $ubicacion_query = $this->ubicacionGastronomiaRepository->listarParaSelect();
        $esCrown = EntornoEmpresaSupport::esCrown();
        $sala_query = $esCrown ? $this->salasParaFiltro() : collect();
        $empresa_default_id = (int) (config('mesa_gastronomia_anita.empresa_default_id') ?: 1);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, MesaGastronomiaListadoFiltros::class);

        return view('ventas.mesa_gastronomia.crear', compact(
            'data', 'empresa_query', 'ubicacion_query', 'sala_query', 'empresa_default_id', 'filtrosQuery'
        ));
    }

    public function guardar(ValidacionMesaGastronomia $request)
    {
        $this->repository->create($request->all());

        return redirect()->route('consultar_mesa_gastronomia', QueryRetornoListado::desdeRequest($request, MesaGastronomiaListadoFiltros::class))
            ->with('mensaje', 'Mesa creada con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-mesa-gastronomia');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $ubicacion_query = $this->ubicacionGastronomiaRepository->listarParaSelect($data->empresa_id);
        $esCrown = EntornoEmpresaSupport::esCrown();
        $sala_query = $esCrown ? $this->salasParaFiltro() : collect();
        $empresa_default_id = (int) ($data->empresa_id ?: (config('mesa_gastronomia_anita.empresa_default_id') ?: 1));
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, MesaGastronomiaListadoFiltros::class);

        return view('ventas.mesa_gastronomia.editar', compact(
            'data', 'empresa_query', 'ubicacion_query', 'sala_query', 'empresa_default_id', 'filtrosQuery'
        ));
    }

    private function salasParaFiltro()
    {
        $q = Sala::query()->orderBy('codigo')->orderBy('nombre');
        $ids = UsuarioSalaAsignadaSupport::idsDesdeSesion();
        if (count($ids) >= 1) {
            $q->whereIn('id', $ids);
        }

        return $q->get(['id', 'nombre', 'codigo']);
    }

    public function actualizar(ValidacionMesaGastronomia $request, $id)
    {
        can('actualizar-mesa-gastronomia');
        $this->repository->update($request->all(), $id);

        return redirect()->route('consultar_mesa_gastronomia', QueryRetornoListado::desdeRequest($request, MesaGastronomiaListadoFiltros::class))
            ->with('mensaje', 'Mesa actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-mesa-gastronomia');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * Importación masiva desde Anita (ApiAnita). Si hay timeout (504), usar:
     * php artisan mesa-gastronomia:sincronizar-anita
     */
    public function sincronizarDesdeAnita(Request $request)
    {
        can('actualizar-mesa-gastronomia');

        if (! config('app.anita_sync_mesa_gastronomia_index')) {
            abort(403);
        }

        if (! $request->isMethod('post')) {
            abort(405);
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        set_time_limit(0);
        ignore_user_abort(true);

        try {
            $ret = $this->mesaGastronomiaAnitaSyncService->sincronizarConAnita();
            $msg = 'Sincronización desde Anita: '.$ret['importados'].' nuevas, '.$ret['actualizadas'].' actualizadas.';
            if (! empty($ret['errores'])) {
                $msg .= ' '.implode(' ', array_slice($ret['errores'], 0, 5));
            }

            return redirect()->route('consultar_mesa_gastronomia')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('MesaGastronomia sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('consultar_mesa_gastronomia')->with('errores', [
                'No se completó la sincronización desde Anita. Si el error fue por tiempo de espera (504), ejecute en el servidor: php artisan mesa-gastronomia:sincronizar-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }
}
