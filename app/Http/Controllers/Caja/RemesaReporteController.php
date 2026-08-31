<?php

declare(strict_types=1);

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\RemesaReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\Remesa\RemesaReporteService;
use App\Support\Caja\RemesaReporteFiltros;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class RemesaReporteController extends Controller
{
    public function __construct(
        private readonly RemesaReporteService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-remesa-reporte');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $permitidas = $empresaQuery->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $filtros = RemesaReporteFiltros::resolverDesdeRequest($request);
        $consultado = $request->boolean('consultar');

        if ($consultado) {
            $filtros['empresa_ids'] = $this->filtrarEmpresaIdsPermitidas($filtros['empresa_ids'], $permitidas);
        } elseif ($filtros['empresa_ids'] === []) {
            $filtros['empresa_ids'] = $permitidas;
        } else {
            $filtros['empresa_ids'] = $this->filtrarEmpresaIdsPermitidas($filtros['empresa_ids'], $permitidas);
        }

        $resultado = null;
        $filasPaginadas = null;
        $tieneEmpresas = $filtros['empresa_ids'] !== [];

        if ($consultado && $tieneEmpresas) {
            $resultado = $this->service->generar($filtros);
            $page = max(1, (int) $request->input('page', 1));
            $perPage = 50;
            $filas = $resultado['filas'];
            $filasPaginadas = new LengthAwarePaginator(
                array_slice($filas, ($page - 1) * $perPage, $perPage),
                count($filas),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return view('caja.remesa_reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => RemesaReporteFiltros::paraQueryString($filtros, $consultado),
            'consultado' => $consultado,
            'empresa_query' => $empresaQuery,
            'cuentas' => $this->service->cuentasDestino($filtros['empresa_ids'] !== []
                ? $filtros['empresa_ids']
                : $empresaQuery->pluck('id')->all()),
            'resultado' => $resultado,
            'filasPaginadas' => $filasPaginadas,
            'puede_ver_remesa' => can('editar-remesa', false) || can('listar-remesa', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-remesa-reporte');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = RemesaReporteFiltros::resolverDesdeRequest($request);
        $filtros['empresa_ids'] = $this->filtrarEmpresaIdsPermitidas(
            $filtros['empresa_ids'],
            $empresaQuery->pluck('id')->all()
        );

        if ($filtros['empresa_ids'] === []) {
            return redirect()
                ->route('remesa_reporte', RemesaReporteFiltros::paraQueryString($filtros))
                ->with('errores', 'Seleccione al menos una empresa para exportar el reporte.');
        }

        $resultado = $this->service->generar($filtros);
        $filas = $resultado['filas'];
        $titulo = 'Remesas por cuenta de caja';
        $subtitulo = $resultado['subtitulo'];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('caja.remesa_reporte.listado', [
                    'filas' => $filas,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'resultado' => $resultado,
                    'puede_ver_remesa' => false,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre = 'reporte_remesa_cuenta_caja';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre.'.pdf');

                return response()->download($path.'/'.$nombre.'.pdf');

            case 'EXCEL':
                return (new RemesaReporteExport($filas, $titulo, $subtitulo, $resultado))
                    ->download('reporte_remesa_cuenta_caja.xlsx');

            case 'CSV':
                return (new RemesaReporteExport($filas, $titulo, $subtitulo, $resultado))
                    ->download('reporte_remesa_cuenta_caja.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('remesa_reporte', RemesaReporteFiltros::paraQueryString($filtros, true));
    }

    /**
     * @param  list<int>  $solicitados
     * @param  list<int|string>  $permitidos
     * @return list<int>
     */
    private function filtrarEmpresaIdsPermitidas(array $solicitados, array $permitidos): array
    {
        $permitidos = array_values(array_map('intval', $permitidos));
        if ($solicitados === []) {
            return $permitidos;
        }

        $set = array_flip($permitidos);

        return array_values(array_filter(
            $solicitados,
            static fn (int $id) => isset($set[$id])
        ));
    }
}
