<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\CajaListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCaja;
use App\Repositories\Caja\CajaRepositoryInterface;
use App\Repositories\Configuracion\SalaRepositoryInterface;
use App\Support\Caja\CajaListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;

class CajaController extends Controller
{
    public function __construct(
        private CajaRepositoryInterface $repository,
        private SalaRepositoryInterface $salaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-caja');

        $filtros = $this->resolverFiltrosListado($request);
        $this->persistirPreferenciasSala($filtros);

        $datas = $this->repository->leeCaja($filtros, true);
        $filtrosQuery = CajaListadoFiltros::paraQueryString($filtros);

        return view('caja.caja.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => CajaListadoFiltros::CAMPOS,
            'sala_query' => $this->salaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $this->persistirPreferenciasSala($filtros);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeCaja($filtros, false);
                $html = view('caja.caja.listado', compact('datas'))->render();
                $ruta = storage_path('pdf/listados/listado_caja.pdf');
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => 'Cajas',
                ]);

                return response()->download($ruta);

            case 'EXCEL':
                return (new CajaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('cajas.xlsx');

            case 'CSV':
                return (new CajaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('cajas.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consulta_caja', CajaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-caja');
        $sala_query = $this->salaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, CajaListadoFiltros::class);

        return view('caja.caja.crear', compact('sala_query', 'filtrosQuery'));
    }

    public function guardar(ValidacionCaja $request)
    {
        $this->repository->create($request->only(['codigo', 'nombre', 'sala_id']));

        return redirect()
            ->route('consulta_caja', QueryRetornoListado::desdeRequest($request, CajaListadoFiltros::class))
            ->with('mensaje', 'Caja creada con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-caja');
        $data = $this->repository->findOrFail($id);
        $sala_query = $this->salaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, CajaListadoFiltros::class);

        return view('caja.caja.editar', compact('data', 'sala_query', 'filtrosQuery'));
    }

    public function actualizar(ValidacionCaja $request, $id)
    {
        can('actualizar-caja');

        $this->repository->update($request->only(['codigo', 'nombre', 'sala_id']), $id);

        return redirect()
            ->route('consulta_caja', QueryRetornoListado::desdeRequest($request, CajaListadoFiltros::class))
            ->with('mensaje', 'Caja actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-caja');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $salas = $this->salaRepository->allFiltrado();
        $salaIds = $salas->pluck('id')->map(fn ($id) => (int) $id)->all();

        $salaDefault = null;
        $traeSalaEnRequest = $request->filled('sala_id')
            || $request->boolean('sala_todas')
            || $request->boolean('sala_sin')
            || $request->filled('sala_scope');

        if (! $traeSalaEnRequest) {
            $scopePref = ReportePreferenciasUsuario::leerString(
                CajaListadoFiltros::PREFERENCIAS_CLAVE,
                'sala_scope',
                ''
            );
            if ($scopePref === 'sin') {
                $request->merge(['sala_sin' => 1]);
            } elseif ($scopePref === 'todas' || $scopePref === '') {
                // Primera visita o preferencia "todas": sin default de sala concreta.
                $salaDefault = null;
            } elseif ($scopePref === 'una') {
                $idPref = (int) ReportePreferenciasUsuario::leerString(
                    CajaListadoFiltros::PREFERENCIAS_CLAVE,
                    'sala_id',
                    '0'
                );
                if ($idPref > 0) {
                    $salaDefault = $idPref;
                }
            }
        }

        return CajaListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $salaIds,
            $salaDefault
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function persistirPreferenciasSala(array $filtros): void
    {
        $scope = (string) ($filtros['sala_scope'] ?? 'todas');
        ReportePreferenciasUsuario::persistirString(
            CajaListadoFiltros::PREFERENCIAS_CLAVE,
            'sala_scope',
            $scope
        );
        if ($scope === 'una' && ! empty($filtros['sala_id'])) {
            ReportePreferenciasUsuario::persistirString(
                CajaListadoFiltros::PREFERENCIAS_CLAVE,
                'sala_id',
                (string) (int) $filtros['sala_id']
            );
        }
    }
}
