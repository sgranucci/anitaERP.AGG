<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\MozoGastronomiaListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMozoGastronomia;
use App\Models\Configuracion\Sala;
use App\Models\Ventas\MozoGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\MozoGastronomiaRepositoryInterface;
use App\Services\Ventas\MozoGastronomiaAnitaSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Ventas\MozoGastronomiaListadoFiltros;
use App\Support\Ventas\UsuarioSalaAsignadaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MozoGastronomiaController extends Controller
{
    public function __construct(
        private MozoGastronomiaRepositoryInterface $repository,
        private MozoGastronomiaAnitaSyncService $mozoGastronomiaAnitaSyncService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-mozo-gastronomia');

        if (filter_var(env('MOZO_GASTRONOMIA_SYNC_ANITA_INDEX', false), FILTER_VALIDATE_BOOLEAN)
            && ! $this->repository->existeRegistro()) {
            $this->mozoGastronomiaAnitaSyncService->sincronizarConAnita();
        }

        $filtros = MozoGastronomiaListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = MozoGastronomiaListadoFiltros::paraQueryString($filtros);
        $datas = $this->repository->leeMozoGastronomia($filtros, true);
        if ($datas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $datas->appends($filtrosQuery);
        }
        $sinMozosCargados = $datas->total() === 0 && ! MozoGastronomiaListadoFiltros::tieneCriteriosAplicados($filtros);
        $esCrown = EntornoEmpresaSupport::esCrown();
        $sala_query = $esCrown ? $this->salasParaFiltro() : collect();

        return view('ventas.mozo_gastronomia.index', [
            'datas' => $datas,
            'sinMozosCargados' => $sinMozosCargados,
            'esCrown' => $esCrown,
            'sala_query' => $sala_query,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => MozoGastronomiaListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-mozo-gastronomia');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = MozoGastronomiaListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $esCrown = EntornoEmpresaSupport::esCrown();

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeMozoGastronomia($filtros, false);
                $view = \View::make('ventas.mozo_gastronomia.listado', compact('datas', 'esCrown', 'filtros'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombre_pdf = 'listado_mozo_gastronomia';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new MozoGastronomiaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('mozo_gastronomia.xlsx');

            case 'CSV':
                return (new MozoGastronomiaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('mozo_gastronomia.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_mozo_gastronomia', MozoGastronomiaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-mozo-gastronomia');
        $data = new MozoGastronomia();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresaIdSugerencia = $this->resolverEmpresaIdSugerenciaCodigo($empresa_query);

        $data->codigo = $this->repository->proximoCodigoLocal($empresaIdSugerencia);
        $esCrown = EntornoEmpresaSupport::esCrown();
        $sala_query = $esCrown ? $this->salasParaFiltro() : collect();
        $empresa_default_id = (int) (config('mozo_gastronomia_anita.empresa_default_id') ?: $empresaIdSugerencia ?: 1);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, MozoGastronomiaListadoFiltros::class);

        return view('ventas.mozo_gastronomia.crear', compact(
            'data', 'empresa_query', 'sala_query', 'empresa_default_id', 'filtrosQuery'
        ));
    }

    public function guardar(ValidacionMozoGastronomia $request)
    {
        $data = $request->all();
        if (trim((string) ($data['codigo'] ?? '')) === '') {
            $empresaId = (int) ($data['empresa_id'] ?? 0);
            if ($empresaId <= 0) {
                return redirect()->back()->withInput()->withErrors([
                    'codigo' => 'Seleccione la empresa para calcular el próximo código.',
                ]);
            }
            $data['codigo'] = $this->repository->proximoCodigoLocal($empresaId);
        }

        $empresaId = (int) ($data['empresa_id'] ?? 0);
        $codigo = trim((string) ($data['codigo'] ?? ''));
        if ($this->repository->codigoExisteEnEmpresa($empresaId, $codigo)) {
            return redirect()->back()->withInput()->withErrors([
                'codigo' => 'El código '.$codigo.' ya está en uso para esta empresa en el ERP.',
            ]);
        }

        $this->repository->create($data);

        return redirect()->route('consultar_mozo_gastronomia', QueryRetornoListado::desdeRequest($request, MozoGastronomiaListadoFiltros::class))
            ->with('mensaje', 'Mozo creado con éxito');
    }

    public function proximoCodigo(Request $request)
    {
        can('crear-mozo-gastronomia');

        $empresaId = (int) $request->get('empresa_id');
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json(['error' => 'Empresa inválida.'], 422);
        }

        return response()->json([
            'codigo' => $this->repository->proximoCodigoLocal($empresaId),
        ]);
    }

    public function editar(Request $request, $id)
    {
        can('editar-mozo-gastronomia');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $esCrown = EntornoEmpresaSupport::esCrown();
        $sala_query = $esCrown ? $this->salasParaFiltro() : collect();
        $empresa_default_id = (int) ($data->empresa_id ?: (config('mozo_gastronomia_anita.empresa_default_id') ?: 1));
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, MozoGastronomiaListadoFiltros::class);

        return view('ventas.mozo_gastronomia.editar', compact(
            'data', 'empresa_query', 'sala_query', 'empresa_default_id', 'filtrosQuery'
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

    public function actualizar(ValidacionMozoGastronomia $request, $id)
    {
        can('actualizar-mozo-gastronomia');

        $data = $request->all();
        $empresaId = (int) ($data['empresa_id'] ?? 0);
        $codigo = trim((string) ($data['codigo'] ?? ''));
        if ($codigo !== '' && $this->repository->codigoExisteEnEmpresa($empresaId, $codigo, (int) $id)) {
            return redirect()->back()->withInput()->withErrors([
                'codigo' => 'El código '.$codigo.' ya está en uso para esta empresa en el ERP.',
            ]);
        }

        $this->repository->update($data, $id);

        return redirect()->route('consultar_mozo_gastronomia', QueryRetornoListado::desdeRequest($request, MozoGastronomiaListadoFiltros::class))
            ->with('mensaje', 'Mozo actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-mozo-gastronomia');

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
     * php artisan mozo-gastronomia:sincronizar-anita
     */
    public function consultaMozo(Request $request)
    {
        $salaId = $request->filled('sala_id') ? (int) $request->get('sala_id') : null;

        if ($request->filled('empresa_id')) {
            can('usar-proceso-facturacion-gastronomia');
            $empresaId = (int) $request->get('empresa_id');

            return $this->repository->consultaMozo(
                (string) ($request->get('consulta') ?? ''),
                $empresaId,
                false,
                $salaId,
            );
        }

        can('listar-mozo-gastronomia');

        return $this->repository->consultaMozo(
            (string) ($request->get('consulta') ?? ''),
            0,
            true,
            $salaId,
        );
    }

    public function leeUnMozoPorCodigo(Request $request, string $codigo)
    {
        can('usar-proceso-facturacion-gastronomia');

        $empresaId = (int) ($request->get('empresa_id') ?: config('cliente.EMPRESA_DEFAULT_ID'));
        $salaId = $request->filled('sala_id') ? (int) $request->get('sala_id') : null;
        $mozo = $this->repository->findPorCodigo($codigo, $empresaId, false, $salaId);

        if (! $mozo) {
            return response()->json(['error' => 'Mozo no encontrado'], 404);
        }

        return response()->json([
            'id' => $mozo->id,
            'codigo' => $mozo->codigo,
            'nombre' => $mozo->nombre,
            'sala_id' => $mozo->sala_id,
        ]);
    }

    public function sincronizarDesdeAnita(Request $request)
    {
        can('actualizar-mozo-gastronomia');

        if (! config('app.anita_sync_mozo_gastronomia_index')) {
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
            $ret = $this->mozoGastronomiaAnitaSyncService->sincronizarConAnita();
            $msg = 'Sincronización desde Anita: '.$ret['importados'].' nuevos, '.$ret['actualizados'].' actualizados.';
            if (! empty($ret['errores'])) {
                $msg .= ' '.implode(' ', array_slice($ret['errores'], 0, 5));
            }

            return redirect()->route('consultar_mozo_gastronomia')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('MozoGastronomia sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('consultar_mozo_gastronomia')->with('errores', [
                'No se completó la sincronización desde Anita. Si el error fue por tiempo de espera (504), ejecute en el servidor: php artisan mozo-gastronomia:sincronizar-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>  $empresaQuery
     */
    private function resolverEmpresaIdSugerenciaCodigo($empresaQuery): int
    {
        if ($empresaQuery->count() === 1) {
            return (int) $empresaQuery->first()->id;
        }

        return (int) config('mozo_gastronomia_anita.empresa_default_id', config('cliente.EMPRESA_DEFAULT_ID', 1));
    }
}
