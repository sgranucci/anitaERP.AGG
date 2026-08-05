<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\CotizacionTesoreriaListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCotizacionTesoreria;
use App\Models\Caja\CotizacionTesoreria;
use App\Repositories\Caja\CotizacionTesoreriaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\CotizacionTesoreriaAnitaImportService;
use App\Support\Caja\CotizacionTesoreriaListadoFiltros;
use App\Support\Caja\CotizacionTesoreriaMonedasSupport;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CotizacionTesoreriaController extends Controller
{
    public function __construct(
        private readonly CotizacionTesoreriaRepositoryInterface $repository,
        private readonly CotizacionTesoreriaAnitaImportService $anitaImportService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-cotizacion-tesoreria');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeCotizacionTesoreria($filtros, true);
        $monedasColumnas = CotizacionTesoreriaMonedasSupport::monedasParaColumnas();

        return view('caja.cotizacion_tesoreria.index', [
            'datas' => $datas,
            'monedasColumnas' => $monedasColumnas,
            'filtros' => $filtros,
            'filtrosQuery' => CotizacionTesoreriaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => CotizacionTesoreriaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-cotizacion-tesoreria');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $monedasColumnas = CotizacionTesoreriaMonedasSupport::monedasParaColumnas();

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeCotizacionTesoreria($filtros, false);
                $view = \View::make('caja.cotizacion_tesoreria.listado', compact('datas', 'monedasColumnas'))
                    ->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_cotizacion_tesoreria';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new CotizacionTesoreriaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('cotizacion_tesoreria.xlsx');

            case 'CSV':
                return (new CotizacionTesoreriaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('cotizacion_tesoreria.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('cotizacion_tesoreria', CotizacionTesoreriaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-cotizacion-tesoreria');

        $data = new CotizacionTesoreria(['empresa_id' => 1]);
        $monedasColumnas = CotizacionTesoreriaMonedasSupport::monedasParaColumnas();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, CotizacionTesoreriaListadoFiltros::class);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('caja.cotizacion_tesoreria.crear', compact('data', 'monedasColumnas', 'filtrosQuery', 'empresa_query'));
    }

    public function guardar(ValidacionCotizacionTesoreria $request)
    {
        can('crear-cotizacion-tesoreria');

        $this->repository->create($request->validated());

        return redirect()->route('cotizacion_tesoreria', QueryRetornoListado::desdeRequest($request, CotizacionTesoreriaListadoFiltros::class))
            ->with('mensaje', 'Cotización de tesorería creada con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-cotizacion-tesoreria');

        $data = $this->repository->findOrFail($id);
        $monedasColumnas = CotizacionTesoreriaMonedasSupport::monedasParaColumnas();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, CotizacionTesoreriaListadoFiltros::class);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('caja.cotizacion_tesoreria.editar', compact('data', 'monedasColumnas', 'filtrosQuery', 'empresa_query'));
    }

    public function actualizar(ValidacionCotizacionTesoreria $request, $id)
    {
        can('actualizar-cotizacion-tesoreria');

        $this->repository->update($request->validated(), $id);

        return redirect()->route('cotizacion_tesoreria', QueryRetornoListado::desdeRequest($request, CotizacionTesoreriaListadoFiltros::class))
            ->with('mensaje', 'Cotización de tesorería actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-cotizacion-tesoreria');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function sincronizarDesdeAnita(Request $request)
    {
        can('crear-cotizacion-tesoreria');

        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        set_time_limit(0);

        $retornoEmpresa = CotizacionTesoreriaListadoFiltros::paraQueryStringEmpresa(
            $this->resolverFiltrosListado($request)
        );

        try {
            $ret = $this->anitaImportService->importarTodas(
                $request->input('desde'),
                $request->input('hasta'),
            );
            $msg = sprintf(
                'Importación Anita (cotiz_tes, empresas 1/2/3): %d leídos, %d creados, %d actualizados, %d omitidos.',
                $ret['leidos'],
                $ret['creados'],
                $ret['actualizados'],
                $ret['omitidos'],
            );

            return redirect()->route('cotizacion_tesoreria', $retornoEmpresa)->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('CotizacionTesoreria sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('cotizacion_tesoreria', $retornoEmpresa)->with('errores', [
                'No se completó la importación desde Anita. Si fue por tiempo, ejecute: php artisan cotizacion-tesoreria:importar-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaDefault = $this->resolverEmpresaDefaultId($empresaQuery);
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();

        $filtros = CotizacionTesoreriaListadoFiltros::resolverDesdeRequest($request, $busquedaRuta, $empresaDefault);
        $filtros['empresas_asignadas'] = $asignadas;

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if (
            ($filtros['empresa_scope'] ?? 'una') === 'una'
            && $empresaId > 0
            && count($asignadas) >= 1
            && ! in_array($empresaId, $asignadas, true)
        ) {
            $filtros['empresa_id'] = $empresaDefault > 0 ? $empresaDefault : null;
            if ($filtros['empresa_id'] === null) {
                $filtros['empresa_scope'] = 'todas';
            }
        }

        return $filtros;
    }

    private function resolverEmpresaDefaultId($empresaQuery): int
    {
        $first = $empresaQuery->first();

        return $first !== null ? (int) $first->id : 0;
    }
}
