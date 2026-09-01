<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\CanonEntidadesListadoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\CanonEntidades\CanonEntidadesReporteService;
use App\Support\Contable\CanonEntidades\CanonEntidadesListadoFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;

class CanonEntidadesReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'canon_entidades';

    public function __construct(
        private readonly CanonEntidadesReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-canon-entidades');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = CanonEntidadesListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferencias($request, $filtros, $empresaQuery);

        $consultado = false;
        $resultado = null;

        if ($request->boolean('consultar') && CanonEntidadesListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            ]);

            $resultado = $this->reporteService->generarOCache($filtros);
            $consultado = true;
        }

        $filtrosQuery = CanonEntidadesListadoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }

        return view('contable.canon_entidades.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'periodo_texto' => CanonEntidadesListadoFiltros::formatearPeriodoTexto($filtros),
        ]);
    }

    public function listar(Request $request, ?string $formato = null)
    {
        can('exportar-canon-entidades');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = CanonEntidadesListadoFiltros::resolverDesdeRequest($request);
        if (! CanonEntidadesListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('canon_entidades');
        }

        $resultado = $this->reporteService->generarOCache($filtros);
        $identidad = $resultado['identidad'] ?? [];
        $nombreEmpresa = (string) ($identidad['nombre'] ?? '');
        $filasParaLogo = [(object) ['nombreempresa' => $nombreEmpresa]];
        $periodo = CanonEntidadesListadoFiltros::formatearPeriodoTexto($filtros);
        $titulo = 'F2015 · Canon entidades de bien público';
        $subtitulo = trim($nombreEmpresa.($periodo !== '' ? ' — '.$periodo : ''));

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('contable.canon_entidades.listado', [
                    'filasParaLogo' => $filasParaLogo,
                    'resultado' => $resultado,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_canon_entidades_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new CanonEntidadesListadoExport(
                    $filasParaLogo,
                    $resultado,
                    $titulo,
                    $subtitulo,
                ))->download('listado_canon_entidades_'.date('Ymd_His').'.xlsx');

            case 'CSV':
                return (new CanonEntidadesListadoExport(
                    $filasParaLogo,
                    $resultado,
                    $titulo,
                    $subtitulo,
                    true,
                ))->download('listado_canon_entidades_'.date('Ymd_His').'.csv', \Maatwebsite\Excel\Excel::CSV, [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                ]);

            default:
                return redirect()->route('canon_entidades', CanonEntidadesListadoFiltros::paraQueryString($filtros));
        }
    }

    public function exportarFormulario(Request $request)
    {
        can('exportar-canon-entidades');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = CanonEntidadesListadoFiltros::resolverDesdeRequest($request);
        if (! CanonEntidadesListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('canon_entidades');
        }

        $resultado = $this->reporteService->generarOCache($filtros);
        $identidad = $resultado['identidad'] ?? [];
        $slug = preg_replace('/\W+/', '_', (string) ($identidad['nombre'] ?? 'canon')) ?: 'canon';
        $periodo = (string) ($filtros['periodo'] ?? date('Ym'));

        $view = \View::make('contable.canon_entidades.formulario', [
            'resultado' => $resultado,
            'filtros' => $filtros,
            'periodo_texto' => CanonEntidadesListadoFiltros::formatearPeriodoTexto($filtros),
            'fecha_emision' => now(),
        ])->render();

        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
        $nombrePdf = 'formulario_canon_entidades_'.$slug.'_'.$periodo;
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf');
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
