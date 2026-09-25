<?php

declare(strict_types=1);

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\CierreCajaReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\CierreCajaReporteService;
use App\Support\Caja\CierreCajaReporteFiltros;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CierreCajaReporteController extends Controller
{
    public function __construct(
        private readonly CierreCajaReporteService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-cierre-caja-reporte');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $permitidas = $empresaQuery->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $filtros = CierreCajaReporteFiltros::resolverDesdeRequest($request);
        $consultado = $request->boolean('consultar');

        if (! $request->has('empresa_ids') && $filtros['empresa_ids'] === []) {
            $prefs = ReportePreferenciasUsuario::leerEmpresaIds('cierre_caja_reporte');
            if (is_array($prefs) && $prefs !== []) {
                $filtros['empresa_ids'] = $prefs;
            }
        }

        if ($consultado) {
            $filtros['empresa_ids'] = $this->filtrarEmpresaIdsPermitidas($filtros['empresa_ids'], $permitidas);
            ReportePreferenciasUsuario::persistir('cierre_caja_reporte', [
                'empresa_ids' => $filtros['empresa_ids'],
            ]);
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

        return view('caja.cierre_caja_reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => CierreCajaReporteFiltros::paraQueryString($filtros, $consultado),
            'consultado' => $consultado,
            'empresa_query' => $empresaQuery,
            'cuentas' => $this->service->cuentasFiltro($filtros['empresa_ids'] !== []
                ? $filtros['empresa_ids']
                : $permitidas),
            'resultado' => $resultado,
            'filasPaginadas' => $filasPaginadas,
            'puede_ver_cuentacaja' => can('editar-cuentas-de-caja', false) || can('listar-cuentas-de-caja', false),
            'puede_ver_cheque' => can('editar-cheque', false) || can('listar-cheque', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-cierre-caja-reporte');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = CierreCajaReporteFiltros::resolverDesdeRequest($request);
        $filtros['empresa_ids'] = $this->filtrarEmpresaIdsPermitidas(
            $filtros['empresa_ids'],
            $empresaQuery->pluck('id')->all()
        );

        if ($filtros['empresa_ids'] === []) {
            return redirect()
                ->route('cierre_caja_reporte', CierreCajaReporteFiltros::paraQueryString($filtros))
                ->with('errores', 'Seleccione al menos una empresa para exportar el reporte.');
        }

        $resultado = $this->service->generar($filtros);
        $filas = $resultado['filas'];
        $titulo = 'Cierre de caja';
        $subtitulo = $resultado['subtitulo'];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('caja.cierre_caja_reporte.listado', [
                    'resultado' => $resultado,
                    'filas' => $filas,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'puede_ver_cuentacaja' => false,
                    'puede_ver_cheque' => false,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre = 'reporte_cierre_caja';
                DompdfListadoSupport::guardarLegalLandscape($view, $path.'/'.$nombre.'.pdf', [
                    'titulo_corto' => 'Cierre de caja',
                ]);

                return response()->download($path.'/'.$nombre.'.pdf');

            case 'EXCEL':
                return (new CierreCajaReporteExport($filas, $titulo, $subtitulo, $resultado))
                    ->download('reporte_cierre_caja.xlsx');

            case 'CSV':
                return (new CierreCajaReporteExport($filas, $titulo, $subtitulo, $resultado))
                    ->download('reporte_cierre_caja.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('cierre_caja_reporte', CierreCajaReporteFiltros::paraQueryString($filtros, true));
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
