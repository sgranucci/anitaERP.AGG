<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\SussListadoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\Suss\SussReporteService;
use App\Support\Contable\Suss\SussListadoFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class SussReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'suss';

    public function __construct(
        private readonly SussReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-suss');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = SussListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferencias($request, $filtros, $empresaQuery);

        $consultado = false;
        $resultado = null;

        if ($request->boolean('consultar') && SussListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            ]);

            $resultado = $this->reporteService->generarOCache($filtros);
            $consultado = true;
        }

        $filtrosQuery = SussListadoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }

        return view('contable.suss.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'liquidaciones_enum' => SussListadoFiltros::LIQUIDACIONES,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'periodo_texto' => SussListadoFiltros::formatearPeriodoTexto($filtros),
        ]);
    }

    public function exportar(Request $request)
    {
        can('exportar-suss');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = SussListadoFiltros::resolverDesdeRequest($request);
        if (! SussListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('suss');
        }

        $resultado = $this->reporteService->generarOCache($filtros);
        $nombre = (string) ($resultado['nombre_archivo'] ?? 'F2004.txt');

        return Response::make($resultado['archivo_f2004'] ?? '', 200, [
            'Content-Type' => 'text/plain; charset=ISO-8859-1',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    public function listar(Request $request, ?string $formato = null)
    {
        can('exportar-suss');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = SussListadoFiltros::resolverDesdeRequest($request);
        if (! SussListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('suss');
        }

        $resultado = $this->reporteService->generarOCache($filtros);
        $registros = $resultado['registros'] ?? [];
        $totales = $resultado['totales'] ?? [];
        $conciliacion = $resultado['conciliacion'] ?? [];

        $empresa = $this->empresaRepository->allFiltrado()
            ->firstWhere('id', (int) ($filtros['empresa_id'] ?? 0));
        $nombreEmpresa = (string) ($empresa->nombre ?? '');
        $filasParaLogo = [(object) ['nombreempresa' => $nombreEmpresa]];

        $periodo = SussListadoFiltros::formatearPeriodoTexto($filtros);
        $titulo = 'SUSS — F2004 (impuesto 353)';
        $subtitulo = trim($nombreEmpresa.($periodo !== '' ? ' — '.$periodo : ''));

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('contable.suss.listado', [
                    'filasParaLogo' => $filasParaLogo,
                    'registros' => $registros,
                    'totales' => $totales,
                    'conciliacion' => $conciliacion,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_suss_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new SussListadoExport(
                    $filasParaLogo,
                    $registros,
                    $totales,
                    $conciliacion,
                    $titulo,
                    $subtitulo,
                ))->download('listado_suss_'.date('Ymd_His').'.xlsx');

            case 'CSV':
                return (new SussListadoExport(
                    $filasParaLogo,
                    $registros,
                    $totales,
                    $conciliacion,
                    $titulo,
                    $subtitulo,
                ))->download('listado_suss_'.date('Ymd_His').'.csv', \Maatwebsite\Excel\Excel::CSV, [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                ]);

            default:
                return redirect()->route('suss', SussListadoFiltros::paraQueryString($filtros));
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarPreferencias(Request $request, array $filtros, $empresaQuery): array
    {
        if (! $request->boolean('consultar')) {
            if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
                $empresaPref = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
                if ($empresaPref && $empresaQuery->contains('id', $empresaPref)) {
                    $filtros['empresa_id'] = $empresaPref;
                } elseif ($empresaQuery->count() === 1) {
                    $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
                }
            }
        }

        if ((int) ($filtros['empresa_id'] ?? 0) <= 0 && $empresaQuery->count() === 1) {
            $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
        }

        return $filtros;
    }
}
